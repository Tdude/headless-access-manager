/**
 * Assessment Manager JavaScript
 *
 * Handles the functionality for the assessment management interface.
 */
(function($) {
    'use strict';

    /**
     * Initialize the assessment manager.
     */
    function initAssessmentManager() {
        // Initialize filters
        initFilters();

        initSchoolAvatars();

        // Initialize modal
        initModal();

        // Initialize section tabs
        initSectionTabs();

        // Initialize delete buttons
        initDeleteButtons();

        // Initialize statistics charts (only runs on stats page)
        initStatsCharts();

        // Enable WP-native collapsible/reorderable panels on stats page
        initStatsPostboxes();
    }

    function initStatsPostboxes() {
        if (typeof window.postboxes === 'undefined') {
            return;
        }

        const holders = Array.from(document.querySelectorAll('.ham-stats-postboxes'));
        if (holders.length === 0) {
            return;
        }

        // Ensure WP-native collapse indicator exists in each postbox header.
        // WP normally renders this markup, but our custom templates omit it.
        try {
            holders.forEach((holder) => {
                const postboxes = Array.from(holder.querySelectorAll('.postbox'));
                postboxes.forEach((pb) => {
                    const header = pb.querySelector('.postbox-header');
                    if (!header) {
                        return;
                    }
                    if (header.querySelector('button.handlediv')) {
                        return;
                    }

                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'handlediv';
                    btn.setAttribute('aria-expanded', pb.classList.contains('closed') ? 'false' : 'true');

                    const span = document.createElement('span');
                    span.className = 'toggle-indicator';
                    span.setAttribute('aria-hidden', 'true');
                    btn.appendChild(span);

                    header.insertBefore(btn, header.firstChild);
                });
            });
        } catch (e) {
        }

        const page = holders[0].getAttribute('data-page')
            ? String(holders[0].getAttribute('data-page'))
            : 'ham-assessment-stats';

        // WP expects sortable containers to have stable IDs to persist order.
        // Our templates render `.meta-box-sortables` without IDs, so `save_order()`
        // can silently fail. Assign deterministic IDs based on the page key.
        holders.forEach((holder, holderIdx) => {
            const sortables = Array.from(holder.querySelectorAll('.meta-box-sortables'));
            sortables.forEach((el, idx) => {
                if (!el.id) {
                    el.id = `${page}-sortables-${holderIdx}-${idx}`;
                }
            });
        });

        try {
            // Some WP builds use this property when persisting closed state.
            window.postboxes.page = page;
            window.postboxes.add_postbox_toggles(page);
        } catch (e) {
        }

        function resizeCharts() {
            if (!window.Chart) {
                return;
            }
            const canvases = holders.flatMap((h) => Array.from(h.querySelectorAll('canvas')));
            canvases.forEach((c) => {
                const chart = window.Chart.getChart(c);
                if (chart) {
                    try {
                        chart.resize();
                        chart.update();
                    } catch (e) {
                    }
                }
            });
        }

        // Resize charts when a postbox is toggled.
        $(document).on('postbox-toggled', () => {
            setTimeout(resizeCharts, 50);
        });

        // Enable drag reorder within the container.
        $('.ham-stats-postboxes .meta-box-sortables').sortable({
            placeholder: 'sortable-placeholder',
            connectWith: '.meta-box-sortables',
            items: '.postbox',
            handle: '.hndle, .handlediv, .postbox-header',
            tolerance: 'pointer',
            forcePlaceholderSize: true,
            start: function() {
                $('.meta-box-sortables').addClass('sorting');
            },
            stop: function() {
                $('.meta-box-sortables').removeClass('sorting');
                try {
                    window.postboxes.save_order(page);
                } catch (e) {
                }
                setTimeout(resizeCharts, 50);
            },
        });
    }

    function initSchoolAvatars() {
        const avatars = document.querySelectorAll('.ham-school-avatar[data-school-name]');
        if (!avatars.length) {
            return;
        }

        const palette = [
            '#cde1fcff',
            '#d2fae0ff',
            '#f8f3baff',
            '#f8d6d8ff',
            '#d4ddf9ff',
            '#d7baf6ff',
            '#bff6fbff',
            '#efccf9ff',
        ];

        function hashString(input) {
            let hash = 0;
            for (let i = 0; i < input.length; i++) {
                hash = ((hash << 5) - hash) + input.charCodeAt(i);
                hash |= 0;
            }
            return Math.abs(hash);
        }

        avatars.forEach(function(el) {
            const name = String(el.getAttribute('data-school-name') || '').trim();
            if (!name) {
                return;
            }
            const idx = hashString(name.toLowerCase()) % palette.length;
            el.style.backgroundColor = palette[idx];
        });
    }

    function initStatsCharts() {
        if (!window.Chart) {
            return;
        }

        const overview = window.hamAssessmentOverview || null;
        const stats = window.hamAssessmentStats || null;

        const dateRange = {
            fromMonth: null,
            toMonth: null,
        };

        const fullDateRange = {
            minMonth: null,
            maxMonth: null,
        };

        const dateRangeListeners = [];

        function registerDateRangeListener(fn) {
            if (typeof fn !== 'function') {
                return;
            }
            dateRangeListeners.push(fn);
        }

        function initBucketToggle(options) {
            const { buttons, defaultKey, isKeyAvailable, onChange } = options || {};

            const initialButtons = Array.isArray(buttons) ? buttons : [];
            let btns = [];
            const btnSet = new Set();

            if (initialButtons.length === 0 || typeof onChange !== 'function') {
                return null;
            }

            function setActive(key) {
                btns.forEach((b) => {
                    const isActive = b.getAttribute('data-bucket') === key;
                    b.classList.toggle('button-primary', isActive);
                    b.classList.toggle('button-secondary', !isActive);
                    b.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                });
            }

            let activeKey = defaultKey;
            if (typeof isKeyAvailable === 'function' && activeKey && !isKeyAvailable(activeKey)) {
                activeKey = null;
            }

            if (!activeKey) {
                for (let i = 0; i < initialButtons.length; i++) {
                    const k = initialButtons[i] ? initialButtons[i].getAttribute('data-bucket') : null;
                    if (!k) {
                        continue;
                    }
                    if (typeof isKeyAvailable !== 'function' || isKeyAvailable(k)) {
                        activeKey = k;
                        break;
                    }
                }
            }

            if (!activeKey) {
                activeKey = (initialButtons[0] && initialButtons[0].getAttribute('data-bucket')) || defaultKey;
            }

            function setBucket(bucketKey) {
                if (!bucketKey) {
                    return;
                }
                activeKey = bucketKey;
                setActive(bucketKey);
                onChange(bucketKey);
            }

            function wireButtons(newButtons) {
                const incoming = Array.isArray(newButtons) ? newButtons : [];
                incoming.forEach((b) => {
                    if (!b || btnSet.has(b)) {
                        return;
                    }
                    btnSet.add(b);
                    btns.push(b);
                    b.addEventListener('click', () => {
                        setBucket(b.getAttribute('data-bucket'));
                    });
                });
                setActive(activeKey);
            }

            wireButtons(initialButtons);
            setBucket(activeKey);

            return {
                getActiveKey: () => activeKey,
                setBucket,
                addButtons: wireButtons,
            };
        }

        let studentBucketController = null;
        const studentBucketHandlers = [];

        function registerStudentBucketHandler(fn) {
            if (typeof fn !== 'function') {
                return;
            }
            studentBucketHandlers.push(fn);
        }

        function ensureStudentBucketController(buttons, defaultKey, isKeyAvailable) {
            if (studentBucketController) {
                return studentBucketController;
            }

            studentBucketController = initBucketToggle({
                buttons,
                defaultKey,
                isKeyAvailable,
                onChange: (key) => {
                    studentBucketHandlers.forEach((handler) => {
                        try {
                            handler(key);
                        } catch (e) {
                        }
                    });
                },
            });

            if (studentBucketController) {
                const active = studentBucketController.getActiveKey();
                studentBucketHandlers.forEach((handler) => {
                    try {
                        handler(active);
                    } catch (e) {
                    }
                });
            }

            return studentBucketController;
        }

        const CHART_ANIMATION_DURATION_MS = 300;
        const CHART_ANIMATION_EASING = 'easeInOutQuad';
        const CHART_BASE_HUE = 205;
        const CHART_HUE_STEP = 28;
        const CHART_BORDER_WIDTH = 2;
        const CHART_POINT_RADIUS = 2;
        const CHART_LINE_TENSION = 0.55;
        const CHART_LINE_POINT_RADIUS = 3;
        const CHART_FILL_ALPHA = 0.70;
        const CHART_OVERLAY_DASH = [6, 4];
        const CHART_TABLE_INSET_BORDER_PX = (function() {
            try {
                const raw = getComputedStyle(document.documentElement).getPropertyValue('--ham-table-inset-border-px');
                const px = parseInt(String(raw).trim().replace('px', ''), 10);
                return Number.isFinite(px) ? px : 4;
            } catch (e) {
                return 4;
            }
        })();
        const CHART_RADAR_ANGLE_LINE_COLOR = 'rgba(0,0,0,0.08)';

        const CHART_FONT_SIZE_TICKS = 11;
        const CHART_FONT_SIZE_POINT_LABELS = 12;
        const CHART_FONT_SIZE_TITLE = 14;
        const CHART_FONT_SIZE_LEGEND = 14;

        const CHART_TARGET_SCORE = 3;
        const CHART_TARGET_COLOR = 'rgba(245, 158, 11, 0.3)';

        const t = (window.hamAssessment && window.hamAssessment.texts) ? window.hamAssessment.texts : {};
        const labelMonth = t.month || 'Month';
        const labelTerm = t.term || 'Förra terminen';
        const labelSchoolYear = t.schoolYear || 'Skolår';
        const labelHogstadium = t.hogstadium || 'L/M/H-stadium';
        const labelRadar = t.radar || 'Radar';
        const labelOption1 = t.option1 || 'Option 1';
        const labelOption2 = t.option2 || 'Option 2';
        const labelOption3 = t.option3 || 'Option 3';
        const labelOption4 = t.option4 || 'Option 4';
        const labelOption5 = t.option5 || 'Option 5';

        if (!overview && !stats) {
            return;
        }

        function buildTargetDataset(labelCount) {
            const n = Math.max(0, Number.isFinite(labelCount) ? labelCount : 0);
            return {
                label: t.targetScore || 'Målnivå 3',
                data: Array.from({ length: n }, () => CHART_TARGET_SCORE),
                borderColor: CHART_TARGET_COLOR,
                backgroundColor: 'rgba(0,0,0,0)',
                borderWidth: 2,
                pointRadius: 0,
                fill: false,
                borderDash: [],
            };
        }

        function isMonthKey(val) {
            return typeof val === 'string' && /^\d{4}-\d{2}$/.test(val);
        }

        function scanForMonthKeys(root) {
            const found = [];
            const stack = [root];
            const seen = new Set();

            while (stack.length > 0) {
                const cur = stack.pop();
                if (!cur || (typeof cur !== 'object' && !Array.isArray(cur))) {
                    continue;
                }
                if (seen.has(cur)) {
                    continue;
                }
                seen.add(cur);

                if (Array.isArray(cur)) {
                    cur.forEach((v) => stack.push(v));
                    continue;
                }

                Object.keys(cur).forEach((k) => {
                    const v = cur[k];
                    if (k === 'key' && isMonthKey(v)) {
                        found.push(v);
                    } else if (isMonthKey(v)) {
                        found.push(v);
                    } else if (v && (typeof v === 'object' || Array.isArray(v))) {
                        stack.push(v);
                    }
                });
            }

            return found;
        }

        function initFullDateRange() {
            const months = [];
            if (overview) {
                months.push(...scanForMonthKeys(overview));
            }
            if (stats) {
                months.push(...scanForMonthKeys(stats));
            }

            const uniq = Array.from(new Set(months.filter((m) => isMonthKey(m)))).sort();
            if (uniq.length === 0) {
                return;
            }

            fullDateRange.minMonth = uniq[0];
            fullDateRange.maxMonth = uniq[uniq.length - 1];
        }

        initFullDateRange();

        if (fullDateRange.minMonth && fullDateRange.maxMonth) {
            dateRange.fromMonth = fullDateRange.minMonth;
            dateRange.toMonth = fullDateRange.maxMonth;
        }

        function monthToTimestampStart(month) {
            if (!month || typeof month !== 'string' || !/^\d{4}-\d{2}$/.test(month)) {
                return null;
            }
            const y = parseInt(month.slice(0, 4), 10);
            const m = parseInt(month.slice(5, 7), 10);
            if (!Number.isFinite(y) || !Number.isFinite(m) || m < 1 || m > 12) {
                return null;
            }
            return Date.UTC(y, m - 1, 1, 0, 0, 0, 0);
        }

        function monthToTimestampEnd(month) {
            if (!month || typeof month !== 'string' || !/^\d{4}-\d{2}$/.test(month)) {
                return null;
            }
            const y = parseInt(month.slice(0, 4), 10);
            const m = parseInt(month.slice(5, 7), 10);
            if (!Number.isFinite(y) || !Number.isFinite(m) || m < 1 || m > 12) {
                return null;
            }
            return Date.UTC(y, m, 0, 23, 59, 59, 999);
        }

        function getSchoolYearStartFromTimestamp(ts) {
            const d = new Date(ts);
            const monthNum = d.getUTCMonth() + 1;
            const yearNum = d.getUTCFullYear();
            if (monthNum >= 8) {
                return yearNum;
            }
            if (monthNum <= 6) {
                return yearNum - 1;
            }
            return yearNum;
        }

        function bucketKeyToRange(bucketType, bucketKey) {
            const key = bucketKey == null ? '' : String(bucketKey);
            if (bucketType === 'month') {
                const start = monthToTimestampStart(key);
                const end = monthToTimestampEnd(key);
                return start != null && end != null ? { start, end } : null;
            }

            if (bucketType === 'school_year') {
                const y = parseInt(key, 10);
                if (!Number.isFinite(y)) {
                    return null;
                }
                const start = Date.UTC(y, 7, 1, 0, 0, 0, 0);
                const end = Date.UTC(y + 1, 6, 30, 23, 59, 59, 999);
                return { start, end };
            }

            if (bucketType === 'term') {
                if (!key.includes('-')) {
                    return null;
                }
                const parts = key.split('-');
                const y = parseInt(parts[0], 10);
                const term = String(parts[1] || '').toUpperCase();
                if (!Number.isFinite(y) || (term !== 'VT' && term !== 'HT')) {
                    return null;
                }
                if (term === 'VT') {
                    return {
                        start: Date.UTC(y + 1, 0, 1, 0, 0, 0, 0),
                        end: Date.UTC(y + 1, 5, 30, 23, 59, 59, 999),
                    };
                }
                return {
                    start: Date.UTC(y, 7, 1, 0, 0, 0, 0),
                    end: Date.UTC(y, 11, 31, 23, 59, 59, 999),
                };
            }

            if (bucketType === 'hogstadium') {
                const base = parseInt(key, 10);
                if (!Number.isFinite(base)) {
                    return null;
                }
                return {
                    start: Date.UTC(base, 7, 1, 0, 0, 0, 0),
                    end: Date.UTC(base + 3, 6, 30, 23, 59, 59, 999),
                };
            }

            return null;
        }

        function getEffectiveRange() {
            if (
                fullDateRange.minMonth &&
                fullDateRange.maxMonth &&
                dateRange.fromMonth === fullDateRange.minMonth &&
                dateRange.toMonth === fullDateRange.maxMonth
            ) {
                return { fromTs: null, toTs: null };
            }
            const fromTs = monthToTimestampStart(dateRange.fromMonth);
            const toTs = monthToTimestampEnd(dateRange.toMonth);
            if (fromTs != null && toTs != null && fromTs > toTs) {
                return { fromTs: toTs, toTs: fromTs };
            }
            return { fromTs, toTs };
        }

        function bucketIntersectsRange(bucketType, bucketKey) {
            const r = getEffectiveRange();
            if (r.fromTs == null && r.toTs == null) {
                return true;
            }
            const br = bucketKeyToRange(bucketType, bucketKey);
            if (!br) {
                return true;
            }

            if (r.fromTs != null && br.end < r.fromTs) {
                return false;
            }
            if (r.toTs != null && br.start > r.toTs) {
                return false;
            }
            return true;
        }

        function pickLatestBucket(buckets) {
            if (!Array.isArray(buckets) || buckets.length === 0) {
                return null;
            }
            return buckets[buckets.length - 1];
        }

        function pickBucketForControl(bucketKey, buckets) {
            if (!Array.isArray(buckets) || buckets.length === 0) {
                return null;
            }

            // Term control represents the previous term.
            if (bucketKey === 'term' && buckets.length >= 2) {
                return buckets[buckets.length - 2];
            }

            return pickLatestBucket(buckets);
        }

        function filterBuckets(bucketType, buckets) {
            const arr = Array.isArray(buckets) ? buckets : [];
            return arr.filter((b) => b && bucketIntersectsRange(bucketType, b.key));
        }

        function filterSeries(bucketType, series) {
            const arr = Array.isArray(series) ? series : [];
            return arr.filter((b) => {
                if (!b) {
                    return false;
                }
                if (bucketType === 'month') {
                    return bucketIntersectsRange('month', b.key);
                }

                if (bucketType === 'school_year') {
                    return bucketIntersectsRange('school_year', b.key);
                }

                if (bucketType === 'term') {
                    return bucketIntersectsRange('term', b.key);
                }

                if (bucketType === 'hogstadium') {
                    return bucketIntersectsRange('hogstadium', b.key);
                }

                return true;
            });
        }

        function getSemesterKeyFromTimestamp(ts) {
            const d = new Date(ts);
            const year = d.getUTCFullYear();
            const month = d.getUTCMonth() + 1;
            const semester = month <= 6 ? 'spring' : 'fall';
            return `${year}-${semester}`;
        }

        function filterSemesterSeries(series) {
            const arr = Array.isArray(series) ? series : [];
            const r = getEffectiveRange();
            if (r.fromTs == null && r.toTs == null) {
                return arr;
            }

            const fromKey = r.fromTs != null ? getSemesterKeyFromTimestamp(r.fromTs) : null;
            const toKey = r.toTs != null ? getSemesterKeyFromTimestamp(r.toTs) : null;

            return arr.filter((b) => {
                const k = b && (b.semester_key || b.key) ? String(b.semester_key || b.key) : '';
                if (!k) {
                    return false;
                }
                if (fromKey && k < fromKey) {
                    return false;
                }
                if (toKey && k > toKey) {
                    return false;
                }
                return true;
            });
        }

        function initDateRangeControls() {
            const fromInputs = Array.from(document.querySelectorAll('input.ham-date-from'));
            const toInputs = Array.from(document.querySelectorAll('input.ham-date-to'));
            const clearBtns = Array.from(document.querySelectorAll('.ham-date-clear'));
            const summaryEls = Array.from(document.querySelectorAll('.ham-date-summary'));

            if (fromInputs.length === 0 && toInputs.length === 0 && clearBtns.length === 0) {
                return;
            }

            function formatMonthLabel(month) {
                const start = monthToTimestampStart(month);
                if (start == null) {
                    return '';
                }
                try {
                    return new Date(start).toLocaleDateString('sv-SE', { year: 'numeric', month: 'short' });
                } catch (e) {
                    return String(month);
                }
            }

            function summaryText() {
                const from = dateRange.fromMonth;
                const to = dateRange.toMonth;
                const allDatesLabel = (function() {
                    const first = summaryEls.length > 0 ? summaryEls[0] : null;
                    if (!first) return 'All Dates';
                    const attr = first.getAttribute('data-all-dates');
                    if (attr) return String(attr);
                    const txt = (first.textContent || '').trim();
                    return txt || 'All Dates';
                })();
                if (!from && !to) {
                    return allDatesLabel;
                }
                if (
                    fullDateRange.minMonth &&
                    fullDateRange.maxMonth &&
                    from === fullDateRange.minMonth &&
                    to === fullDateRange.maxMonth
                ) {
                    return allDatesLabel;
                }
                if (from && to) {
                    return `${formatMonthLabel(from)} – ${formatMonthLabel(to)}`;
                }
                if (from) {
                    return `${formatMonthLabel(from)} –`;
                }
                return `– ${formatMonthLabel(to)}`;
            }

            function sync() {
                fromInputs.forEach((el) => {
                    el.value = dateRange.fromMonth || '';
                });
                toInputs.forEach((el) => {
                    el.value = dateRange.toMonth || '';
                });
                summaryEls.forEach((el) => {
                    el.textContent = summaryText();
                });
            }

            function notify() {
                dateRangeListeners.forEach((fn) => {
                    try {
                        fn();
                    } catch (e) {
                    }
                });
            }

            fromInputs.forEach((el) => {
                el.addEventListener('change', () => {
                    dateRange.fromMonth = el.value ? String(el.value) : null;
                    sync();
                    notify();
                });
            });

            toInputs.forEach((el) => {
                el.addEventListener('change', () => {
                    dateRange.toMonth = el.value ? String(el.value) : null;
                    sync();
                    notify();
                });
            });

            clearBtns.forEach((btn) => {
                btn.addEventListener('click', () => {
                    if (fullDateRange.minMonth && fullDateRange.maxMonth) {
                        dateRange.fromMonth = fullDateRange.minMonth;
                        dateRange.toMonth = fullDateRange.maxMonth;
                    } else {
                        dateRange.fromMonth = null;
                        dateRange.toMonth = null;
                    }
                    sync();
                    notify();
                });
            });

            sync();
        }

        function clampNumber(val, min, max) {
            const num = typeof val === 'number' ? val : (val == null ? null : Number(val));
            if (num == null || Number.isNaN(num)) {
                return null;
            }
            return Math.max(min, Math.min(max, num));
        }

        function escapeHtml(str) {
            const s = str == null ? '' : String(str);
            return s
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function hslColor(h, s, l, a) {
            if (typeof a === 'number') {
                return `hsla(${h}, ${s}%, ${l}%, ${a})`;
            }
            return `hsl(${h}, ${s}%, ${l}%)`;
        }

        function datasetColor(idx) {
            const hue = (CHART_BASE_HUE + (idx * CHART_HUE_STEP)) % 360;
            return {
                border: hslColor(hue, 70, 45),
                fill: hslColor(hue, 70, 55, CHART_FILL_ALPHA),
            };
        }

        // Keep dataset colors stable across bucket toggles by assigning a persistent
        // color index per dataset key (we use the dataset label as identity).
        const datasetColorIndexByKey = new Map();
        let nextDatasetColorIndex = 0;

        const datasetHiddenByKey = new Map();

        function normalizeDatasetLabel(label) {
            const s = label == null ? '' : String(label);
            // Remove trailing count suffixes like "(23)", "(23st)", "(23 students)".
            // This keeps colors stable even when counts change between buckets.
            return s.replace(/\s*\(\s*\d+[^)]*\)\s*$/i, '').trim();
        }

        function datasetKey(ds, fallbackIdx) {
            if (ds && ds.label) {
                const normalized = normalizeDatasetLabel(ds.label);
                return normalized || String(ds.label);
            }
            return `__dataset_${fallbackIdx}`;
        }

        function stableDatasetColor(ds, fallbackIdx) {
            const key = datasetKey(ds, fallbackIdx);
            if (!datasetColorIndexByKey.has(key)) {
                datasetColorIndexByKey.set(key, nextDatasetColorIndex++);
            }
            return datasetColor(datasetColorIndexByKey.get(key));
        }

        function buildClickableLegend(containerId, chart, datasets) {
            const el = document.getElementById(containerId);
            if (!el || !chart || !datasets || !Array.isArray(datasets)) {
                return;
            }

            const rows = [];
            datasets.forEach((ds, idx) => {
                if (!ds) {
                    return;
                }
                // Don't include the target ring in the legend.
                if (ds.label && String(ds.label) === String(t.targetScore || 'Målnivå 3')) {
                    return;
                }

                const key = datasetKey(ds, idx);
                const hidden = Boolean(datasetHiddenByKey.get(key));
                const c = stableDatasetColor(ds, idx);

                const displayLabel = ds.label ? String(ds.label) : `${t.evaluation || 'Evaluation'} ${idx + 1}`;
                const safeLabel = escapeHtml(displayLabel);
                const ariaPressed = hidden ? 'false' : 'true';
                const opacity = hidden ? '0.35' : '1';

                rows.push(
                    `<button type="button" class="ham-legend-item button-link" data-key="${escapeHtml(key)}" data-index="${idx}" aria-pressed="${ariaPressed}" style="display:flex;align-items:center;gap:6px;opacity:${opacity};padding:0;border:0;background:none;cursor:pointer;">`
                    + `<span class="ham-legend-color" style="background:${c.border};"></span>`
                    + `<span class="ham-legend-label">${safeLabel}</span>`
                    + `</button>`
                );
            });

            el.innerHTML = rows.length > 0 ? rows.join('') : '';

            el.querySelectorAll('.ham-legend-item').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const key = btn.getAttribute('data-key') || '';
                    const idx = Number(btn.getAttribute('data-index'));
                    if (!Number.isFinite(idx) || !chart.data || !Array.isArray(chart.data.datasets) || !chart.data.datasets[idx]) {
                        return;
                    }

                    const nextHidden = !Boolean(datasetHiddenByKey.get(key));
                    datasetHiddenByKey.set(key, nextHidden);
                    chart.data.datasets[idx].hidden = nextHidden;
                    chart.update();

                    btn.style.opacity = nextHidden ? '0.35' : '1';
                    btn.setAttribute('aria-pressed', nextHidden ? 'false' : 'true');
                });
            });
        }

        function buildLineChart(canvasId, series, label) {
            const el = document.getElementById(canvasId);
            if (!el || !series || !Array.isArray(series) || series.length === 0) {
                return;
            }

            const existing = Chart.getChart(el);
            if (existing) {
                existing.destroy();
            }

            const labels = series.map((p) => p.label);
            const data = series.map((p) => clampNumber(p.overall_avg, 1, 5));

            const c = datasetColor(0);
            new Chart(el.getContext('2d'), {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        label,
                        data,
                        borderColor: c.border,
                        backgroundColor: c.fill,
                        fill: true,
                        tension: CHART_LINE_TENSION,
                        pointRadius: 3,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: CHART_ANIMATION_DURATION_MS,
                        easing: CHART_ANIMATION_EASING,
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: { enabled: true },
                    },
                    scales: {
                        y: {
                            min: 1,
                            max: 5,
                            ticks: { stepSize: 1 },
                        },
                    },
                },
            });

            return chart;
        }

        function renderGroupRadarValuesTable(containerId, bucketGroup, options) {
            const el = document.getElementById(containerId);
            if (!el) {
                return;
            }

            if (stats && stats.level === 'schools' && containerId === 'ham-group-radar-table') {
                renderGroupRadarValuesMiniLine(containerId, bucketGroup, options);
                return;
            }

            const mode = options && options.mode ? String(options.mode) : 'counts';

            if (!bucketGroup || !bucketGroup.buckets) {
                el.innerHTML = '';
                return;
            }

            const labels = Array.isArray(bucketGroup.labels) ? bucketGroup.labels : [];
            const bucket = pickLatestBucket(bucketGroup.buckets);

            if (!bucket || !Array.isArray(bucket.datasets) || bucket.datasets.length === 0) {
                el.innerHTML = '';
                return;
            }

            const datasets = bucket.datasets;

            let html = '';
            html += `<div class="ham-radar-values-header">${bucket.label ? String(bucket.label) : ''}</div>`;
            html += '<div class="ham-radar-values-scroll">';
            html += '<table class="widefat fixed striped ham-radar-values-table">';
            html += '<thead><tr>';
            html += `<th class="ham-radar-values-th-question">${escapeHtml(t.question || 'Question')}</th>`;

            datasets.forEach((ds, idx) => {
                const c = stableDatasetColor(ds, idx);
                const safeLabel = escapeHtml(ds.label || `${labelRadar} ${idx + 1}`);
                html += `<th class="ham-radar-values-th-eval" style="box-shadow: inset ${CHART_TABLE_INSET_BORDER_PX}px 0 0 ${c.border};">${safeLabel}</th>`;
            });

            html += '</tr></thead>';
            html += '<tbody>';

            for (let qi = 0; qi < labels.length; qi++) {
                html += '<tr>';
                html += `<td class="ham-radar-values-td-question">${escapeHtml(labels[qi] || '')}</td>`;
                datasets.forEach((ds, idx) => {
                    const c = stableDatasetColor(ds, idx);
                    const v = Array.isArray(ds.values) ? ds.values[qi] : null;
                    const vv = v == null ? null : Number(v);

                    let out = '—';
                    if (vv != null && !Number.isNaN(vv)) {
                        if (mode === 'avg') {
                            out = vv.toFixed(1);
                        } else {
                            out = String(Math.round(vv));
                        }
                    }

                    html += `<td class="ham-radar-values-td-eval" style="box-shadow: inset ${CHART_TABLE_INSET_BORDER_PX}px 0 0 ${c.border};">${out}</td>`;
                });
                html += '</tr>';
            }

            html += '</tbody></table></div>';
            el.innerHTML = html;
        }

        function renderGroupRadarValuesMiniLine(containerId, bucketGroup, options) {
            const el = document.getElementById(containerId);
            if (!el) {
                return;
            }

            const buckets = bucketGroup && Array.isArray(bucketGroup.buckets) ? bucketGroup.buckets : [];
            const labels = bucketGroup && Array.isArray(bucketGroup.labels) ? bucketGroup.labels : [];
            const bucket = buckets.length > 0 ? buckets[buckets.length - 1] : null;

            if (!bucket || !bucket.datasets || !Array.isArray(bucket.datasets) || bucket.datasets.length === 0) {
                el.innerHTML = '';
                return;
            }

            const ds = bucket.datasets[0];
            const values = Array.isArray(ds.values) ? ds.values : [];

            const n = Math.min(labels.length, values.length);
            if (n === 0) {
                el.innerHTML = '';
                return;
            }

            const w = 100;
            const row = 22;
            const padY = 12;
            const h = padY * 2 + Math.max(0, (n - 1)) * row;
            const padX = 14;
            const xMin = padX;
            const xMax = w - padX;
            const minVal = 1;
            const maxVal = 5;

            const points = [];
            const nodes = [];
            for (let i = 0; i < n; i++) {
                const y = padY + i * row;

                const raw = Number(values[i]);
                const v = Number.isFinite(raw) ? raw : null;
                const clamped = v == null ? null : Math.max(minVal, Math.min(maxVal, v));
                const ratio = clamped == null ? 0.5 : ((clamped - minVal) / (maxVal - minVal));
                const x = xMin + ratio * (xMax - xMin);

                const text = v == null ? '' : (Number.isInteger(v) ? String(v) : v.toFixed(1));

                points.push(`${x},${y}`);
                nodes.push({
                    x,
                    y,
                    label: String(labels[i] || ''),
                    value: text,
                });
            }

            let html = '';
            html += `<div class="ham-radar-values-header">${bucket.label ? String(bucket.label) : ''}</div>`;
            html += '<div class="ham-radar-values-scroll" style="overflow: visible;">';
            html += '<div style="display:flex; gap: 10px; align-items: flex-start;">';
            html += `<div style="flex: 1 1 auto; min-width: 180px;">`;
            html += `<div style="display:flex; flex-direction:column; gap: 0;">`;
            nodes.forEach((node) => {
                html += `<div style="height:${row}px; display:flex; align-items:center; font-size:12px; color:#1d2327;">${escapeHtml(node.label)}</div>`;
            });
            html += `</div>`;
            html += '</div>';

            html += `<div style="flex: 0 1 260px; max-width: 100%;">`;
            html += `<svg class="ham-mini-line" viewBox="0 0 ${w} ${h}" preserveAspectRatio="xMidYMin meet" style="display:block; height: ${h}px; width: auto; max-width: 100%; overflow: visible;">`;
            html += `<line x1="${xMin}" y1="${padY}" x2="${xMin}" y2="${h - padY}" stroke="#dcdcde" stroke-width="1" />`;
            html += `<line x1="${xMax}" y1="${padY}" x2="${xMax}" y2="${h - padY}" stroke="#dcdcde" stroke-width="1" />`;
            html += `<polyline fill="none" stroke="#0073aa" stroke-width="2" points="${points.join(' ')}" />`;

            nodes.forEach((node) => {
                html += `<g>`;
                html += `<title>${escapeHtml(node.label)}${node.value ? ': ' + escapeHtml(node.value) : ''}</title>`;
                html += `<circle cx="${node.x}" cy="${node.y}" r="11" fill="#ffffff" stroke="#0073aa" stroke-width="1" />`;
                html += `</g>`;
            });

            html += '</svg>';
            html += '</div>';
            html += '</div>';
            html += '</div>';

            el.innerHTML = html;
        }

        function buildGroupRadarToggle() {
            const canvas = document.getElementById('ham-group-radar');
            const btns = Array.from(document.querySelectorAll('.ham-group-radar-toggle-btn'));

            if (!canvas || btns.length === 0 || !stats || (stats.level !== 'schools' && stats.level !== 'school' && stats.level !== 'class') || !stats.group_radar || !stats.group_radar.buckets) {
                return;
            }

            const labels = Array.isArray(stats.group_radar.labels) ? stats.group_radar.labels : [];
            const bucketsByKey = stats.group_radar.buckets;
            const mode = stats.group_radar && stats.group_radar.mode ? String(stats.group_radar.mode) : 'counts';

            const titleByKey = {
                month: labelMonth,
                term: labelTerm,
                school_year: labelSchoolYear,
                hogstadium: labelHogstadium,
            };

            function computeDynamicMax(datasets) {
                if (mode === 'avg') {
                    return 5;
                }

                let maxN = 0;
                (datasets || []).forEach((ds) => {
                    const n = Number(ds && ds.student_count);
                    if (Number.isFinite(n) && n > maxN) {
                        maxN = n;
                    }
                });

                (datasets || []).forEach((ds) => {
                    if (!ds || !Array.isArray(ds.values)) {
                        return;
                    }
                    ds.values.forEach((v) => {
                        const vv = Number(v);
                        if (Number.isFinite(vv) && vv > maxN) {
                            maxN = vv;
                        }
                    });
                });

                if (maxN < 1) {
                    maxN = 1;
                }
                return maxN;
            }

            function buildDatasetsForBucket(bucketKey) {
                const rawBuckets = bucketsByKey && Array.isArray(bucketsByKey[bucketKey]) ? bucketsByKey[bucketKey] : [];
                const buckets = filterBuckets(bucketKey, rawBuckets);
                const bucket = pickBucketForControl(bucketKey, buckets);
                if (!bucket || !Array.isArray(bucket.datasets) || bucket.datasets.length === 0) {
                    return { title: titleByKey[bucketKey] || labelRadar, datasets: [], max: 1, bucketGroup: { labels, buckets: [] } };
                }

                const max = computeDynamicMax(bucket.datasets);

                const datasets = bucket.datasets.map((ds, idx) => {
                    const c = stableDatasetColor(ds, idx);
                    return {
                        label: ds.label,
                        data: Array.isArray(ds.values)
                            ? ds.values.map((v) => {
                                if (mode === 'avg') {
                                    return clampNumber(v, 1, 5);
                                }
                                return clampNumber(v, 0, max);
                            })
                            : [],
                        borderColor: c.border,
                        backgroundColor: c.fill,
                        borderWidth: CHART_BORDER_WIDTH,
                        pointRadius: CHART_POINT_RADIUS,
                        fill: true,
                        borderDash: idx === 0 ? [] : CHART_OVERLAY_DASH,
                    };
                });

                if (mode === 'avg') {
                    datasets.unshift(buildTargetDataset(labels.length));
                }

                return {
                    title: bucket.label || (titleByKey[bucketKey] || labelRadar),
                    datasets,
                    max,
                    bucketGroup: {
                        labels,
                        buckets,
                    },
                };
            }

            const existing = Chart.getChart(canvas);
            if (existing) {
                existing.destroy();
            }

            const initial = buildDatasetsForBucket('term');
            const chart = new Chart(canvas.getContext('2d'), {
                type: 'radar',
                data: {
                    labels,
                    datasets: initial.datasets,
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: CHART_ANIMATION_DURATION_MS,
                        easing: CHART_ANIMATION_EASING,
                    },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                font: { size: CHART_FONT_SIZE_LEGEND },
                            },
                        },
                        title: {
                            display: true,
                            text: initial.title,
                            font: { size: CHART_FONT_SIZE_TITLE },
                        },
                    },
                    scales: {
                        r: {
                            min: 0,
                            max: initial.max,
                            ticks: {
                                stepSize: mode === 'avg' ? 1 : Math.max(1, Math.ceil(initial.max / 5)),
                                showLabelBackdrop: false,
                                callback: function(value) {
                                    if (mode === 'avg' && value === 0) {
                                        return '';
                                    }
                                    return value;
                                },
                                font: { size: CHART_FONT_SIZE_TICKS },
                            },
                            pointLabels: {
                                font: { size: CHART_FONT_SIZE_POINT_LABELS },
                            },
                            grid: {
                                circular: false,
                            },
                            angleLines: {
                                color: CHART_RADAR_ANGLE_LINE_COLOR,
                            },
                        },
                    },
                },
            });

            function updateChart(bucketKey) {
                const next = buildDatasetsForBucket(bucketKey);
                chart.data.datasets = next.datasets;
                chart.options.plugins.title.text = next.title;
                chart.options.scales.r.max = next.max;
                chart.options.scales.r.ticks.stepSize = mode === 'avg' ? 1 : Math.max(1, Math.ceil(next.max / 5));
                chart.update();
                renderGroupRadarValuesTable('ham-group-radar-table', next.bucketGroup, { mode });
            }

            const controller = initBucketToggle({
                buttons: btns,
                defaultKey: 'term',
                isKeyAvailable: (key) => Array.isArray(bucketsByKey[key]) && bucketsByKey[key].length > 0,
                onChange: updateChart,
            });

            if (controller) {
                updateChart(controller.getActiveKey());
            }

            registerDateRangeListener(() => {
                if (controller) {
                    updateChart(controller.getActiveKey());
                }
            });
        }

        function buildStudentAvgProgressToggle() {
            const canvas = document.getElementById('ham-avg-progress-student');
            const btns = Array.from(document.querySelectorAll('.ham-progress-toggle-btn'));
            const radarBtns = Array.from(document.querySelectorAll('.ham-radar-toggle-btn'));
            const answerBtns = Array.from(document.querySelectorAll('.ham-answer-toggle-btn'));
            const allBtns = btns.concat(radarBtns).concat(answerBtns);

            if (!canvas || btns.length === 0 || !stats || stats.level !== 'student' || !stats.avg_progress) {
                return;
            }

            const seriesByKey = stats.avg_progress;

            function buildFallbackFromSemesterSeries() {
                if (!Array.isArray(stats.series)) {
                    return [];
                }
                return stats.series
                    .filter((b) => b && (b.semester_label || b.semester_key))
                    .map((b) => ({
                        label: b.semester_label || b.semester_key,
                        overall_avg: b.overall_avg,
                        count: b.count,
                    }));
            }

            // Student buckets can be very sparse (often collapsing to a single point).
            // If so, fall back to the richer semester series to avoid single-dot charts.
            if (Array.isArray(stats.series)) {
                ['month', 'term', 'school_year', 'hogstadium'].forEach((key) => {
                    if (!Array.isArray(seriesByKey[key]) || seriesByKey[key].length <= 1) {
                        seriesByKey[key] = buildFallbackFromSemesterSeries();
                    }
                });
            }

            const titleByKey = {
                month: labelMonth,
                term: labelTerm,
                school_year: labelSchoolYear,
                hogstadium: labelHogstadium,
            };

            function buildDataForBucket(bucketKey) {
                const raw = seriesByKey && Array.isArray(seriesByKey[bucketKey]) ? seriesByKey[bucketKey] : [];
                const series = filterSeries(bucketKey, raw);
                const labels = series.map((p) => p.label);
                const data = series.map((p) => clampNumber(p.overall_avg, 1, 5));
                return { labels, data, title: titleByKey[bucketKey] || labelRadar };
            }

            const existing = Chart.getChart(canvas);
            if (existing) {
                existing.destroy();
            }

            const initial = buildDataForBucket('month');
            const c = datasetColor(0);
            const chart = new Chart(canvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: initial.labels,
                    datasets: [{
                        label: initial.title,
                        data: initial.data,
                        borderColor: c.border,
                        backgroundColor: c.fill,
                        fill: true,
                        tension: CHART_LINE_TENSION,
                        pointRadius: CHART_LINE_POINT_RADIUS,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: CHART_ANIMATION_DURATION_MS,
                        easing: CHART_ANIMATION_EASING,
                    },
                    plugins: {
                        legend: { display: false },
                        title: {
                            display: true,
                            text: initial.title,
                        },
                        tooltip: { enabled: true },
                    },
                    scales: {
                        y: {
                            min: 1,
                            max: 5,
                            ticks: { stepSize: 1 },
                        },
                    },
                },
            });

            function updateChart(bucketKey) {
                const next = buildDataForBucket(bucketKey);
                chart.data.labels = next.labels;
                chart.data.datasets[0].data = next.data;
                chart.data.datasets[0].label = next.title;
                chart.options.plugins.title.text = next.title;
                chart.update();
            }

            registerStudentBucketHandler(updateChart);

            registerDateRangeListener(() => {
                if (studentBucketController) {
                    updateChart(studentBucketController.getActiveKey());
                }
            });

            ensureStudentBucketController(
                allBtns,
                'month',
                (key) => {
                    const progressOk = Array.isArray(seriesByKey[key]) && seriesByKey[key].length > 0;
                    const radarOk = stats.student_radar && stats.student_radar.buckets && Array.isArray(stats.student_radar.buckets[key]) && stats.student_radar.buckets[key].length > 0;
                    return progressOk || radarOk;
                }
            );
        }

        function buildStudentRadarToggle() {
            const canvas = document.getElementById('ham-student-radar');
            const btns = Array.from(document.querySelectorAll('.ham-radar-toggle-btn'));
            const legendEl = document.getElementById('ham-student-radar-legend');

            if (!canvas || btns.length === 0 || !stats || stats.level !== 'student' || !stats.student_radar || !stats.student_radar.buckets) {
                return;
            }

            const labels = Array.isArray(stats.student_radar.labels) ? stats.student_radar.labels : [];
            const bucketsByKey = stats.student_radar.buckets;

            const titleByKey = {
                month: labelMonth,
                term: labelTerm,
                school_year: labelSchoolYear,
                hogstadium: labelHogstadium,
            };

            function buildBucketGroup(bucketKey) {
                const rawBuckets = bucketsByKey && Array.isArray(bucketsByKey[bucketKey]) ? bucketsByKey[bucketKey] : [];
                const buckets = filterBuckets(bucketKey, rawBuckets);
                return { labels, buckets };
            }

            function updateChart(bucketKey) {
                const bucketGroup = buildBucketGroup(bucketKey);
                const chart = buildRadarChart('ham-student-radar', bucketGroup, titleByKey[bucketKey] || labelRadar, bucketKey);
                if (legendEl) {
                    // Student radar now uses the built-in Chart.js legend (same as ham-group-radar).
                    // Keep the DOM node empty to avoid a double legend.
                    legendEl.innerHTML = '';
                }
                renderRadarValuesTable('ham-student-radar-table', bucketGroup, bucketKey);
                renderAnswerAlternativesTable('ham-answer-alternatives', stats.radar_questions, bucketGroup, bucketKey);
            }

            registerStudentBucketHandler(updateChart);

            registerDateRangeListener(() => {
                if (studentBucketController) {
                    updateChart(studentBucketController.getActiveKey());
                }
            });

            const controller = ensureStudentBucketController(
                btns,
                'month',
                (key) => Array.isArray(bucketsByKey[key]) && bucketsByKey[key].length > 0
            );

            if (controller) {
                updateChart(controller.getActiveKey());
            }
        }

        function buildDrilldownAvgProgressToggle() {
            const canvas = document.getElementById('ham-avg-progress-drilldown');
            const btnRoot = canvas ? canvas.closest('.ham-stats-panel') : null;
            const btns = Array.from((btnRoot || document).querySelectorAll('.ham-progress-toggle-btn'));

            if (!canvas || btns.length === 0 || !stats || (stats.level !== 'schools' && stats.level !== 'school' && stats.level !== 'class') || !stats.avg_progress) {
                return;
            }

            const seriesByKey = stats.avg_progress;
            const titleByKey = {
                month: labelMonth,
                term: labelTerm,
                school_year: labelSchoolYear,
                hogstadium: labelHogstadium,
            };

            function buildDataForBucket(bucketKey) {
                const raw = seriesByKey && Array.isArray(seriesByKey[bucketKey]) ? seriesByKey[bucketKey] : [];
                const series = filterSeries(bucketKey, raw);
                const labels = series.map((p) => p.label);
                const data = series.map((p) => clampNumber(p.overall_avg, 1, 5));
                return { labels, data, title: titleByKey[bucketKey] || labelRadar };
            }

            const existing = Chart.getChart(canvas);
            if (existing) {
                existing.destroy();
            }

            const initial = buildDataForBucket('month');
            const c = datasetColor(0);
            const chart = new Chart(canvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: initial.labels,
                    datasets: [{
                        label: initial.title,
                        data: initial.data,
                        borderColor: c.border,
                        backgroundColor: c.fill,
                        fill: true,
                        tension: CHART_LINE_TENSION,
                        pointRadius: CHART_LINE_POINT_RADIUS,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: CHART_ANIMATION_DURATION_MS,
                        easing: CHART_ANIMATION_EASING,
                    },
                    plugins: {
                        legend: { display: false },
                        title: {
                            display: true,
                            text: initial.title,
                        },
                        tooltip: { enabled: true },
                    },
                    scales: {
                        y: {
                            min: 1,
                            max: 5,
                            ticks: { stepSize: 1 },
                        },
                    },
                },
            });

            function updateChart(bucketKey) {
                const next = buildDataForBucket(bucketKey);
                chart.data.labels = next.labels;
                chart.data.datasets[0].data = next.data;
                chart.data.datasets[0].label = next.title;
                chart.options.plugins.title.text = next.title;
                chart.update();
            }

            const controller = initBucketToggle({
                buttons: btns,
                defaultKey: 'month',
                isKeyAvailable: (key) => Array.isArray(seriesByKey[key]) && seriesByKey[key].length > 0,
                onChange: updateChart,
            });

            registerDateRangeListener(() => {
                if (controller) {
                    updateChart(controller.getActiveKey());
                }
            });
        }

        function pickLatestBucket(buckets) {
            if (!Array.isArray(buckets) || buckets.length === 0) {
                return null;
            }
            return buckets[buckets.length - 1];
        }

        function renderAnswerAlternativesTable(containerId, radarQuestions, bucketGroup, bucketKey) {
            const el = document.getElementById(containerId);
            if (!el) {
                return;
            }

            if (!radarQuestions || !Array.isArray(radarQuestions) || radarQuestions.length === 0) {
                el.innerHTML = '';
                return;
            }

            if (!bucketGroup || !Array.isArray(bucketGroup.labels) || !Array.isArray(bucketGroup.buckets)) {
                el.innerHTML = '';
                return;
            }

            const bucket = pickBucketForControl(bucketKey || '', bucketGroup.buckets);
            if (!bucket || !Array.isArray(bucket.datasets) || bucket.datasets.length === 0) {
                el.innerHTML = '';
                return;
            }

            const datasets = bucket.datasets;

            // Fade older datasets in the answer alternatives table. We assume datasets
            // Datasets are ordered oldest -> newest in PHP, so newest is the last index.
            function datasetRecencyAlpha(datasetIndex, totalDatasets) {
                const idx = Math.max(0, Number.isFinite(datasetIndex) ? datasetIndex : 0);
                const total = Math.max(1, Number.isFinite(totalDatasets) ? totalDatasets : 1);
                const ageIdx = Math.max(0, (total - 1) - idx);
                const latestAlpha = 0.70;
                const decay = 0.65;
                const minAlpha = 0.10;
                const a = latestAlpha * Math.pow(decay, ageIdx);
                return Math.max(minAlpha, a);
            }

            function datasetRecencyStrength(datasetIndex, totalDatasets) {
                const idx = Math.max(0, Number.isFinite(datasetIndex) ? datasetIndex : 0);
                const total = Math.max(1, Number.isFinite(totalDatasets) ? totalDatasets : 1);
                const ageIdx = Math.max(0, (total - 1) - idx);
                const decay = 0.65;
                const minStrength = 0.12;
                const s = Math.pow(decay, ageIdx);
                return Math.max(minStrength, Math.min(1, s));
            }

            function selectionAlpha(selectedDatasetIndexes, totalDatasets) {
                if (!Array.isArray(selectedDatasetIndexes) || selectedDatasetIndexes.length === 0) {
                    return 0;
                }
                let maxA = 0;
                selectedDatasetIndexes.forEach((di) => {
                    maxA = Math.max(maxA, datasetRecencyAlpha(di, totalDatasets));
                });
                // If multiple datasets share the same choice, bump slightly, but cap.
                const bump = Math.min(0.10, Math.max(0, selectedDatasetIndexes.length - 1) * 0.03);
                return Math.min(0.80, maxA + bump);
            }

            function selectionStrength(selectedDatasetIndexes, totalDatasets) {
                if (!Array.isArray(selectedDatasetIndexes) || selectedDatasetIndexes.length === 0) {
                    return 0;
                }
                // Newest dataset is the largest index (datasets oldest -> newest).
                const newestIdx = Math.max.apply(null, selectedDatasetIndexes.map((di) => (Number.isFinite(di) ? di : 0)));
                return datasetRecencyStrength(newestIdx, totalDatasets);
            }

            function optionBgColor(optionIndex, alpha, strength) {
                const a = typeof alpha === 'number' ? alpha : 0.22;
                const s = typeof strength === 'number' ? strength : 1;
                // 1..5 => red -> orange -> yellow -> light green -> green
                const hues = [0, 28, 50, 90, 120];
                const hue = hues[Math.max(0, Math.min(4, optionIndex))];
                const sat = Math.round(85 * s);
                const light = Math.round(60 + ((1 - s) * 22));
                return `hsla(${hue}, ${sat}%, ${light}%, ${a})`;
            }

            let html = '';
            html += '<div class="ham-radar-values-scroll">';
            html += '<table class="wp-list-table widefat fixed striped ham-answer-alternatives-table">';
            html += '<thead><tr>';
            html += `<th>${escapeHtml(t.question || 'Question')}</th>`;
            html += `<th>${escapeHtml(labelOption1)}</th>`;
            html += `<th>${escapeHtml(labelOption2)}</th>`;
            html += `<th>${escapeHtml(labelOption3)}</th>`;
            html += `<th>${escapeHtml(labelOption4)}</th>`;
            html += `<th>${escapeHtml(labelOption5)}</th>`;
            html += '</tr></thead>';
            html += '<tbody>';

            for (let qi = 0; qi < radarQuestions.length; qi++) {
                const q = radarQuestions[qi] || {};
                const section = q.section ? String(q.section) : '';
                const text = q.text ? String(q.text) : '';
                const key = q.key ? String(q.key) : '';
                const label = (section && text) ? `${section}: ${text}` : (text || key);
                const options = Array.isArray(q.options) ? q.options : [];

                const sectionKey = section.trim().toLowerCase();
                let rowClass = '';
                if (sectionKey === 'anknytning') {
                    rowClass = 'ham-answer-row--anknytning';
                } else if (sectionKey === 'ansvar') {
                    rowClass = 'ham-answer-row--ansvar';
                }

                // optionSelections[optionIndex] => [datasetIndex, datasetIndex, ...]
                const optionSelections = [[], [], [], [], []];
                datasets.forEach((ds, di) => {
                    const v = Array.isArray(ds.values) ? ds.values[qi] : null;
                    const vv = clampNumber(v, 1, 5);
                    if (vv == null) {
                        return;
                    }
                    // Answers are discrete 1-5; if stored as float, round to nearest.
                    const optIdx = Math.max(0, Math.min(4, Math.round(vv) - 1));
                    optionSelections[optIdx].push(di);
                });

                html += rowClass ? `<tr class="${rowClass}">` : '<tr>';
                html += `<td>${escapeHtml(label)}</td>`;

                for (let oi = 0; oi < 5; oi++) {
                    const optText = options[oi] ? String(options[oi]) : '';
                    const sel = optionSelections[oi];

                    let style = '';
                    let cls = 'ham-answer-choice';

                    if (sel.length > 0) {
                        cls += ' ham-answer-choice--selected';
                        const alpha = selectionAlpha(sel, datasets.length);
                        const strength = selectionStrength(sel, datasets.length);
                        style = ` style="background-color: ${optionBgColor(oi, alpha, strength)};"`;
                    }

                    html += `<td class="${cls}"${style}>${escapeHtml(optText)}</td>`;
                }

                html += '</tr>';
            }

            html += '</tbody></table></div>';
            el.innerHTML = html;
        }

        function renderRadarValuesTable(containerId, bucketGroup, bucketKey) {
            const el = document.getElementById(containerId);
            if (!el) {
                return;
            }

            if (!bucketGroup || !Array.isArray(bucketGroup.labels) || !Array.isArray(bucketGroup.buckets)) {
                el.innerHTML = '';
                return;
            }

            const bucket = pickBucketForControl(bucketKey || '', bucketGroup.buckets);
            if (!bucket || !Array.isArray(bucket.datasets) || bucket.datasets.length === 0) {
                el.innerHTML = '';
                return;
            }

            const qLabels = bucketGroup.labels;
            const datasets = bucket.datasets;

            let html = '';
            html += `<div class="ham-radar-values-header">${bucket.label ? String(bucket.label) : ''}</div>`;
            html += '<div class="ham-radar-values-scroll">';
            html += '<table class="widefat fixed striped ham-radar-values-table">';
            html += '<thead><tr>';
            html += `<th class="ham-radar-values-th-question">${escapeHtml(t.question || 'Question')}</th>`;

            datasets.forEach((ds, idx) => {
                const c = stableDatasetColor(ds, idx);
                const safeLabel = escapeHtml(ds.label || `${labelRadar} ${idx + 1}`);
                html += `<th class="ham-radar-values-th-eval" style="box-shadow: inset ${CHART_TABLE_INSET_BORDER_PX}px 0 0 ${c.border};">${safeLabel}</th>`;
            });

            html += '</tr></thead>';
            html += '<tbody>';

            for (let qi = 0; qi < qLabels.length; qi++) {
                html += '<tr>';
                html += `<td class="ham-radar-values-td-question">${escapeHtml(qLabels[qi] || '')}</td>`;
                datasets.forEach((ds, idx) => {
                    const c = stableDatasetColor(ds, idx);
                    const v = Array.isArray(ds.values) ? ds.values[qi] : null;
                    const vv = clampNumber(v, 1, 5);
                    let out = '—';
                    if (vv != null && Number.isFinite(vv)) {
                        out = Number.isInteger(vv) ? String(vv) : vv.toFixed(1);
                    }
                    html += `<td class="ham-radar-values-td-eval" style="box-shadow: inset ${CHART_TABLE_INSET_BORDER_PX}px 0 0 ${c.border};">${out}</td>`;
                });
                html += '</tr>';
            }

            html += '</tbody></table></div>';
            el.innerHTML = html;
        }

        function buildRadarChart(canvasId, bucketGroup, title, bucketKey) {
            const el = document.getElementById(canvasId);
            if (!el || !bucketGroup || !bucketGroup.buckets) {
                return null;
            }

            const existing = Chart.getChart(el);
            if (existing) {
                existing.destroy();
            }

            const bucket = pickBucketForControl(bucketKey || '', bucketGroup.buckets);
            if (!bucket || !Array.isArray(bucket.datasets) || bucket.datasets.length === 0) {
                return null;
            }

            const labels = Array.isArray(bucketGroup.labels) ? bucketGroup.labels : [];

            const datasets = bucket.datasets.map((ds, idx) => {
                const c = stableDatasetColor(ds, idx);
                const key = datasetKey(ds, idx);
                return {
                    label: ds.label,
                    data: Array.isArray(ds.values) ? ds.values.map((v) => clampNumber(v, 1, 5)) : [],
                    borderColor: c.border,
                    backgroundColor: c.fill,
                    borderWidth: CHART_BORDER_WIDTH,
                    pointRadius: CHART_POINT_RADIUS,
                    fill: true,
                    borderDash: idx === 0 ? [] : CHART_OVERLAY_DASH,
                    hidden: Boolean(datasetHiddenByKey.get(key)),
                };
            });

            datasets.push(buildTargetDataset(labels.length));

            const chart = new Chart(el.getContext('2d'), {
                type: 'radar',
                data: {
                    labels,
                    datasets,
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: CHART_ANIMATION_DURATION_MS,
                        easing: CHART_ANIMATION_EASING,
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom',
                            labels: {
                                font: { size: CHART_FONT_SIZE_LEGEND },
                                filter: function(item, data) {
                                    // Hide the target ring from the legend.
                                    const lbl = item && item.text ? String(item.text) : '';
                                    return lbl !== String(t.targetScore || 'Målnivå 3');
                                },
                            },
                            onClick: function(e, item, legend) {
                                // Mirror default toggling, but also persist it across bucket switches.
                                const chart = legend && legend.chart ? legend.chart : null;
                                const idx = item && Number.isFinite(item.datasetIndex) ? item.datasetIndex : null;
                                if (!chart || idx == null || !chart.data || !Array.isArray(chart.data.datasets) || !chart.data.datasets[idx]) {
                                    return;
                                }

                                const ds = chart.data.datasets[idx];
                                if (ds && ds.label && String(ds.label) === String(t.targetScore || 'Målnivå 3')) {
                                    return;
                                }

                                const key = datasetKey(ds, idx);
                                const nextHidden = !Boolean(datasetHiddenByKey.get(key));
                                datasetHiddenByKey.set(key, nextHidden);
                                chart.data.datasets[idx].hidden = nextHidden;
                                chart.update();
                            },
                        },
                        title: {
                            display: Boolean(title),
                            text: title,
                            font: { size: CHART_FONT_SIZE_TITLE },
                        },
                    },
                    scales: {
                        r: {
                            min: 0,
                            max: 5,
                            ticks: {
                                stepSize: 1,
                                callback: function(value) {
                                    if (value === 0) {
                                        return '';
                                    }
                                    return value;
                                },
                                font: { size: CHART_FONT_SIZE_TICKS },
                            },
                            pointLabels: {
                                font: { size: CHART_FONT_SIZE_POINT_LABELS },
                            },
                            grid: {
                                circular: false,
                            },
                            angleLines: {
                                color: CHART_RADAR_ANGLE_LINE_COLOR,
                            },
                        },
                    },
                },
            });

            return chart;
        }

        function buildOverviewRadarChart(canvasId, radar) {
            const el = document.getElementById(canvasId);
            if (!el || !radar) {
                return;
            }

            const labels = Array.isArray(radar.labels) ? radar.labels : [];
            const values = Array.isArray(radar.values) ? radar.values : [];
            if (labels.length === 0 || values.length === 0) {
                return;
            }

            const existing = Chart.getChart(el);
            if (existing) {
                existing.destroy();
            }

            const title = radar.title ? String(radar.title) : '';
            const c = datasetColor(0);

            const datasets = [
                {
                    label: title || labelRadar,
                    data: values.map((v) => clampNumber(v, 1, 5)),
                    borderColor: c.border,
                    backgroundColor: c.fill,
                    borderWidth: CHART_BORDER_WIDTH,
                    pointRadius: CHART_POINT_RADIUS,
                    fill: true,
                },
                buildTargetDataset(labels.length),
            ];

            new Chart(el.getContext('2d'), {
                type: 'radar',
                data: { labels, datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: CHART_ANIMATION_DURATION_MS,
                        easing: CHART_ANIMATION_EASING,
                    },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                font: { size: CHART_FONT_SIZE_LEGEND },
                            },
                        },
                        title: {
                            display: Boolean(title),
                            text: title,
                            font: { size: CHART_FONT_SIZE_TITLE },
                        },
                    },
                    scales: {
                        r: {
                            min: 0,
                            max: 5,
                            ticks: {
                                stepSize: 1,
                                showLabelBackdrop: false,
                                callback: function(value) {
                                    if (value === 0) {
                                        return '';
                                    }
                                    return value;
                                },
                                font: { size: CHART_FONT_SIZE_TICKS },
                            },
                            pointLabels: {
                                font: { size: CHART_FONT_SIZE_POINT_LABELS },
                            },
                            grid: {
                                circular: false,
                            },
                            angleLines: {
                                color: CHART_RADAR_ANGLE_LINE_COLOR,
                            },
                        },
                    },
                },
            });
        }

        // Student avg progress toggle
        if (stats && stats.level === 'student' && stats.avg_progress) {
            buildStudentAvgProgressToggle();
        }

        // Student radar chart + bucket toggle
        if (stats && stats.level === 'student' && stats.student_radar && stats.student_radar.buckets) {
            buildStudentRadarToggle();
        }

        // Schools/school/class radar chart + bucket toggle
        if (stats && (stats.level === 'schools' || stats.level === 'school' || stats.level === 'class') && stats.group_radar && stats.group_radar.buckets) {
            buildGroupRadarToggle();
        }

        // Schools/school/class drilldown avg progress toggle
        if (stats && (stats.level === 'schools' || stats.level === 'school' || stats.level === 'class') && stats.avg_progress) {
            buildDrilldownAvgProgressToggle();
        }

        initDateRangeControls();

        if (stats && Array.isArray(stats.series)) {
            stats.series = filterSemesterSeries(stats.series);
        }

        // Overview radar chart
        if (overview && overview.radar) {
            buildOverviewRadarChart('ham-overview-radar', overview.radar);
        }
    }

    /**
     * Initialize the filters functionality.
     */
    function initFilters() {
        const $filterStudent = $('#ham-filter-student');
        const $filterDate = $('#ham-filter-date');
        const $filterCompletion = $('#ham-filter-completion');
        const $filterReset = $('#ham-filter-reset');
        const $assessmentRows = $('.ham-assessments-table tbody tr');

        const sortState = {
            key: '',
            dir: 'asc',
        };

        if ($filterStudent.length && $.fn.select2 && window.hamAssessment && hamAssessment.studentSearchNonce) {
            $filterStudent.select2({
                ajax: {
                    url: hamAssessment.ajaxUrl,
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            action: 'ham_search_students',
                            q: params.term,
                            nonce: hamAssessment.studentSearchNonce,
                        };
                    },
                    processResults: function(data) {
                        return { results: data };
                    },
                    cache: true,
                },
                minimumInputLength: 2,
                width: 'resolve',
                allowClear: true,
            });
        }

        // Handle student filter
        $filterStudent.on('change', applyFilters);

        // Handle date filter
        $filterDate.on('change', applyFilters);

        // Handle completion filter
        $filterCompletion.on('change', applyFilters);

        // Handle reset button
        $('#ham-reset-filters').on('click', function(e) {
            e.preventDefault();
            resetFilters();
        });

        // Click-to-sort headers
        $('.ham-assessments-table thead').on('click', '.ham-sort', function(e) {
            e.preventDefault();
            const key = String($(this).data('sortKey') || '');
            if (!key) {
                return;
            }
            if (sortState.key === key) {
                sortState.dir = sortState.dir === 'asc' ? 'desc' : 'asc';
            } else {
                sortState.key = key;
                sortState.dir = 'asc';
            }
            sortRows();
            applyFilters();
        });

        /**
         * Apply all active filters.
         */
        function applyFilters() {
            const studentFilter = $filterStudent.val();
            const dateFilter = $filterDate.val();
            const completionFilter = $filterCompletion.val();

            $assessmentRows.each(function() {
                const $row = $(this);
                let show = true;

                // Apply student filter
                if (studentFilter && String($row.data('student')) !== String(studentFilter)) {
                    show = false;
                }

                // Apply date filter
                if (dateFilter) {
                    const assessmentDate = new Date($row.data('date-raw'));
                    const today = new Date();
                    const yesterday = new Date();
                    yesterday.setDate(yesterday.getDate() - 1);

                    // Check if date matches filter
                    if (dateFilter === 'today') {
                        if (assessmentDate.toDateString() !== today.toDateString()) {
                            show = false;
                        }
                    } else if (dateFilter === 'yesterday') {
                        if (assessmentDate.toDateString() !== yesterday.toDateString()) {
                            show = false;
                        }
                    } else if (dateFilter === 'week') {
                        // Get start of week (Monday)
                        const startOfWeek = new Date();
                        const dayOfWeek = startOfWeek.getDay() || 7; // Convert Sunday (0) to 7
                        startOfWeek.setDate(startOfWeek.getDate() - dayOfWeek + 1);
                        startOfWeek.setHours(0, 0, 0, 0);

                        if (assessmentDate < startOfWeek) {
                            show = false;
                        }
                    } else if (dateFilter === 'month') {
                        // Start of current month
                        const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);

                        if (assessmentDate < startOfMonth) {
                            show = false;
                        }
                    } else if (dateFilter === 'semester') {
                        // Determine current semester (Jan-Jun or Jul-Dec)
                        const currentMonth = today.getMonth() + 1; // 1-12
                        let startOfSemester;

                        if (currentMonth >= 1 && currentMonth <= 6) {
                            // Spring semester (Jan-Jun)
                            startOfSemester = new Date(today.getFullYear(), 0, 1); // Jan 1
                        } else {
                            // Fall semester (Jul-Dec)
                            startOfSemester = new Date(today.getFullYear(), 6, 1); // Jul 1
                        }

                        if (assessmentDate < startOfSemester) {
                            show = false;
                        }
                    } else if (dateFilter === 'schoolyear') {
                        // School year starts in August and ends in June
                        const currentMonth = today.getMonth() + 1; // 1-12
                        let startOfSchoolYear;

                        if (currentMonth >= 8) {
                            // Current school year started this August
                            startOfSchoolYear = new Date(today.getFullYear(), 7, 1); // Aug 1
                        } else {
                            // Current school year started last August
                            startOfSchoolYear = new Date(today.getFullYear() - 1, 7, 1); // Aug 1 of last year
                        }

                        if (assessmentDate < startOfSchoolYear) {
                            show = false;
                        }
                    }
                }

                // Apply stage filter
                if (completionFilter) {
                    const stage = $row.data('stage');

                    if (completionFilter === 'full' && stage !== 'full') {
                        show = false;
                    } else if (completionFilter === 'transition' && stage !== 'trans') {
                        show = false;
                    } else if (completionFilter === 'not' && stage !== 'not') {
                        show = false;
                    }
                }

                // Show or hide the row
                $row.toggle(show);
            });

            // Show message if no results
            const $visibleRows = $assessmentRows.filter(':visible');
            const $noResults = $('#ham-no-results');

            if ($visibleRows.length === 0) {
                if ($noResults.length === 0) {
                    const colCount = $('.ham-assessments-table thead th').length || 1;
                    $('<tr id="ham-no-results"><td colspan="' + colCount + '">' + hamAssessment.texts.noData + '</td></tr>').appendTo('.ham-assessments-table tbody');
                }
            } else {
                $noResults.remove();
            }
        }

        function getSortValue($row, key) {
            if (key === 'date') {
                return String($row.data('date') || '');
            }
            if (key === 'status') {
                return String($row.data('stage') || '');
            }

            const val = $row.data(key);
            if (typeof val !== 'undefined') {
                return String(val);
            }

            const $cell = $row.find('.column-' + key);
            return $cell.length ? $cell.text().trim() : '';
        }

        function sortRows() {
            if (!sortState.key) {
                return;
            }

            const $tbody = $('.ham-assessments-table tbody');
            const $rows = $tbody.children('tr').not('#ham-no-results');
            const dirFactor = sortState.dir === 'desc' ? -1 : 1;

            const rowsArray = $rows.get();
            rowsArray.sort(function(a, b) {
                const $a = $(a);
                const $b = $(b);
                const av = getSortValue($a, sortState.key).toLowerCase();
                const bv = getSortValue($b, sortState.key).toLowerCase();
                if (av < bv) {
                    return -1 * dirFactor;
                }
                if (av > bv) {
                    return 1 * dirFactor;
                }
                return 0;
            });

            $tbody.append(rowsArray);
        }

        /**
         * Reset all filters.
         */
        function resetFilters() {
            $filterStudent.val(null).trigger('change');
            $filterDate.val('');
            $filterCompletion.val('');
            applyFilters();
        }
    }

    /**
     * Initialize the modal functionality.
     */
    function initModal() {
        const $modal = $('#ham-assessment-modal');
        const $modalClose = $('.ham-modal-close');
        const $viewButtons = $('.ham-view-assessment');

        const tooltipState = {
            timer: null,
            $tooltip: null,
            $target: null,
            lastEvent: null,
        };

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function decodeHtml(value) {
            const textarea = document.createElement('textarea');
            textarea.innerHTML = String(value);
            return textarea.value;
        }

        function hideTooltip() {
            if (tooltipState.timer) {
                clearTimeout(tooltipState.timer);
                tooltipState.timer = null;
            }
            tooltipState.$target = null;
            tooltipState.lastEvent = null;
            if (tooltipState.$tooltip) {
                tooltipState.$tooltip.remove();
                tooltipState.$tooltip = null;
            }
        }

        function positionTooltip() {
            if (!tooltipState.$tooltip || !tooltipState.lastEvent) {
                return;
            }

            const padding = 12;
            let left = tooltipState.lastEvent.pageX + 12;
            let top = tooltipState.lastEvent.pageY + 12;

            tooltipState.$tooltip.css({ left: left + 'px', top: top + 'px' });

            const $w = $(window);
            const maxLeft = $w.scrollLeft() + $w.width() - tooltipState.$tooltip.outerWidth() - padding;
            const maxTop = $w.scrollTop() + $w.height() - tooltipState.$tooltip.outerHeight() - padding;

            if (left > maxLeft) {
                left = maxLeft;
            }
            if (top > maxTop) {
                top = maxTop;
            }

            tooltipState.$tooltip.css({ left: left + 'px', top: top + 'px' });
        }

        function showTooltipFromTarget($target) {
            const attrOptions = $target.attr('data-options');
            const attrHtml = $target.attr('data-full-html');
            const attrText = $target.attr('data-full-text');

            if (!attrOptions && !attrHtml && !attrText) {
                return;
            }

            let options;
            if (attrOptions) {
                try {
                    options = JSON.parse(decodeHtml(attrOptions));
                } catch (e) {
                    options = null;
                }
            }

            const html = attrHtml ? decodeHtml(attrHtml) : '';
            const text = attrText ? decodeHtml(attrText) : '';

            let tooltipHtml = '';
            if (Array.isArray(options) && options.length) {
                const itemsHtml = options
                    .map(opt => {
                        if (!opt || !opt.label) {
                            return '';
                        }
                        const optValue = opt.value !== undefined && opt.value !== null ? String(opt.value) : '';
                        const optLabel = String(opt.label);
                        const safeOptValue = escapeHtml(optValue);
                        const safeOptLabel = escapeHtml(optLabel);
                        const valuePrefix = optValue ? `<span class="ham-tooltip-option-value">${safeOptValue}</span>` : '';
                        return `<li>${valuePrefix}${safeOptLabel}</li>`;
                    })
                    .filter(Boolean)
                    .join('');

                if (itemsHtml) {
                    tooltipHtml = `<div class="ham-tooltip-label">${escapeHtml(hamAssessment.texts.answerAlternatives || 'Answer alternatives')}</div><ul class="ham-tooltip-list">${itemsHtml}</ul>`;
                }
            }

            if (!tooltipState.$tooltip) {
                tooltipState.$tooltip = $('<div class="ham-tooltip" role="tooltip"></div>');
                $('body').append(tooltipState.$tooltip);
            }

            if (tooltipHtml) {
                tooltipState.$tooltip.html(tooltipHtml);
            } else if (attrHtml) {
                tooltipState.$tooltip.html(html);
            } else {
                tooltipState.$tooltip.text(text);
            }
            positionTooltip();
        }

        $modal.on('mouseenter', '.ham-tooltip-target', function(e) {
            hideTooltip();
            tooltipState.$target = $(this);
            tooltipState.lastEvent = e;
            tooltipState.timer = setTimeout(function() {
                if (tooltipState.$target) {
                    showTooltipFromTarget(tooltipState.$target);
                }
            }, 1000);
        });

        $modal.on('mousemove', '.ham-tooltip-target', function(e) {
            tooltipState.lastEvent = e;
            positionTooltip();
        });

        $modal.on('mouseleave', '.ham-tooltip-target', function() {
            hideTooltip();
        });

        $modal.on('scroll', function() {
            hideTooltip();
        });

        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                hideTooltip();
            }
        });

        // Open modal when view button is clicked
        $viewButtons.on('click', function(e) {
            e.preventDefault();
            // Check for both possible attribute names
            let assessmentId = $(this).data('assessment-id');
            if (assessmentId === undefined) {
                assessmentId = $(this).data('id');
            }
            //console.log('Opening modal for assessment ID:', assessmentId);
            fetchAssessmentDetails(assessmentId);
        });

        // Close modal when close button or outside is clicked
        $modalClose.on('click', closeModal);

        $(window).on('click', function(event) {
            if ($(event.target).is($modal)) {
                closeModal();
            }
        });

        /**
         * Fetch and display assessment details.
         *
         * @param {number} assessmentId Assessment ID.
         */
        function fetchAssessmentDetails(assessmentId) {
            // Show modal
            $modal.css('display', 'block');

            $('#ham-assessment-loading').show();
            $('#ham-assessment-error, #ham-assessment-details').hide();

            const data = {
                action: 'ham_get_assessment_details',
                nonce: hamAssessment.nonce,
                assessment_id: assessmentId
            };

            //console.log('Fetching assessment details with data:', data);

            $.post(hamAssessment.ajaxUrl, data, function(response) {
                $('#ham-assessment-loading').hide();

                if (response.success && response.data) {
                    // Log the full response for debugging
                    //console.log('Assessment details response (FULL):', response.data);

                    // Display the assessment details
                    displayAssessmentDetails(response.data);

                    // Show the details container
                    $('#ham-assessment-details').show();
                } else {
                    $('#ham-assessment-error').show();
                    //console.error('Error fetching assessment details:', response);
                }
            }).fail(function(xhr, status, error) {
                $('#ham-assessment-loading').hide();
                $('#ham-assessment-error').show();
                //console.error('AJAX error:', status, error);
            });
        }

        /**
         * Close the modal.
         */
        function closeModal() {
            $modal.css('display', 'none');
        }

        /**
         * Display assessment details in the modal.
         *
         * @param {Object} data Assessment data.
         */
        function displayAssessmentDetails(data) {
            // console.log('Displaying assessment details:', data);

            // =============================
            // COMPREHENSIVE DATA STRUCTURE LOG
            // =============================
            // console.log('%c ========= ASSESSMENT DATA STRUCTURE ANALYSIS =========', 'background: #222; color: #bada55; font-size: 16px');
// 
            // // Log key data structure components
            // console.log('Raw Question Structure:', JSON.stringify(data.questions_structure, null, 2));
            // console.log('Raw Assessment Data:', JSON.stringify(data.assessment_data, null, 2));
// 
            // // Critical check: How are the question keys formatted?
            // if (data.questions_structure && data.questions_structure.anknytning && data.questions_structure.anknytning.questions) {
            //     console.log('Question keys in structure:', Object.keys(data.questions_structure.anknytning.questions));
            // }
// 
            // // Critical check: How are the answer keys formatted?
            // if (data.assessment_data && data.assessment_data.anknytning && data.assessment_data.anknytning.questions) {
            //     console.log('Answer keys in data:', Object.keys(data.assessment_data.anknytning.questions));
            // }

            console.log('%c =================================================', 'background: #222; color: #bada55; font-size: 16px');
            // =============================

            // Set student name and date
            //console.log('SETTING STUDENT NAME:', data.student_name);
            //console.log('CURRENT STUDENT ELEMENT:', $('#ham-assessment-student').length, $('#ham-assessment-student').text());
            $('#ham-assessment-student').text(data.student_name);
            //console.log('AFTER SETTING:', $('#ham-assessment-student').text());
            $('#ham-assessment-date').text(data.date);
            $('#ham-assessment-author').text(data.author_name);

            // Clear existing questions
            $('#ham-anknytning-questions, #ham-ansvar-questions').empty();

            // DIRECT APPROACH: Create a simplified function to render the assessment
            renderAssessmentSection('anknytning', data);
            renderAssessmentSection('ansvar', data);

            // Set comments for each section (per-question)
            function renderSectionComments(sectionName, data) {
                const section = data.assessment_data[sectionName];
                const structure = data.questions_structure[sectionName];
                const $commentsContainer = $(`#ham-${sectionName}-comments`);
                $commentsContainer.empty();
                if (section && section.comments && typeof section.comments === 'object') {
                    // Comments keyed by question ID
                    Object.entries(section.comments).forEach(([qKey, comment]) => {
                        // Try to get question text from structure
                        let questionText = (structure && structure.questions && structure.questions[qKey] && structure.questions[qKey].text) || qKey;
                        if (comment && comment.trim() !== '') {
                            $commentsContainer.append(`<div class="ham-question-comment"><strong>${questionText}:</strong> ${comment}</div>`);
                        }
                    });
                } else if (typeof section?.comments === 'string' && section.comments.trim() !== '') {
                    // Fallback: single string
                    $commentsContainer.append(`<div class="ham-question-comment">${section.comments}</div>`);
                } else {
                    $commentsContainer.append(`<div class="ham-question-comment ham-no-comment">${hamAssessment.texts.noComments || 'No comments.'}</div>`);
                }
            }
            renderSectionComments('anknytning', data);
            renderSectionComments('ansvar', data);

            // Set comments
            $('#ham-comments').text(data.assessment_data.comments || '');

            /**
             * Render an assessment section directly with minimal processing
             */
            function renderAssessmentSection(sectionName, data) {
                const $container = $(`#ham-${sectionName}-questions`);
                const sectionData = data.assessment_data[sectionName];
                const sectionStructure = data.questions_structure[sectionName];

                // Skip if no structure is available
                if (!sectionStructure || !sectionStructure.questions) {
                    //console.log(`Missing structure for section: ${sectionName}`);
                    $container.html(`<tr><td colspan="3">${hamAssessment.texts.noQuestions || 'No questions configured.'}</td></tr>`);
                    return;
                }

                const answeredQuestions = (sectionData && sectionData.questions) ? sectionData.questions : {};
                const structureQuestions = sectionStructure.questions;
                
                // FIX: Iterate over the keys from the STRUCTURE to maintain the correct order
                const questionKeysInOrder = Object.keys(structureQuestions);
                //console.log(`Processing ${sectionName} questions in order from structure:`, questionKeysInOrder);

                if (questionKeysInOrder.length === 0) {
                    $container.html(`<tr><td colspan="3">${hamAssessment.texts.noQuestions || 'No questions configured.'}</td></tr>`);
                    return;
                }

                // Process each question based on the structure's order
                questionKeysInOrder.forEach(qKey => {
                    // Find the corresponding answered key case-insensitively
                    const answeredKey = Object.keys(answeredQuestions).find(
                        key => key.toLowerCase() === qKey.toLowerCase()
                    );
                    const answerData = answeredKey ? answeredQuestions[answeredKey] : undefined;
                    
                    const questionStructure = structureQuestions[qKey];
                    const questionTextFromStructure = questionStructure && typeof questionStructure.text === 'string' ? questionStructure.text.trim() : '';

                    let questionTextFromAnswer = '';
                    if (answerData && typeof answerData === 'object' && answerData !== null && typeof answerData.text === 'string') {
                        questionTextFromAnswer = answerData.text.trim();
                    }

                    const questionText = (questionTextFromAnswer || questionTextFromStructure || String(qKey)).trim();

                    let answerValue;
                    let stage;

                    if (typeof answerData === 'object' && answerData !== null) {
                        answerValue = answerData.value !== undefined ? answerData.value : answerData.selected;
                        stage = answerData.stage || '';
                    } else {
                        answerValue = answerData;
                        stage = '';
                    }

                    let answerLabel = '—'; // Default for unanswered

                    if (answerValue !== undefined && answerValue !== null) {
                        answerLabel = answerValue; // Fallback to raw value
                        if (questionStructure.options && Array.isArray(questionStructure.options)) {
                            const matchingOption = questionStructure.options.find(
                                opt => String(opt.value) === String(answerValue)
                            );

                            if (matchingOption) {
                                answerLabel = matchingOption.label || answerValue;
                                if (!stage && matchingOption.stage) {
                                    stage = matchingOption.stage;
                                }
                            }
                        }
                    }

                    let answerTooltipText = '';
                    if (questionStructure.options && Array.isArray(questionStructure.options) && questionStructure.options.length) {
                        const optionLines = questionStructure.options
                            .map(opt => {
                                const optValue = opt && opt.value !== undefined ? String(opt.value) : '';
                                const optLabel = opt && opt.label ? String(opt.label) : '';
                                if (!optLabel) {
                                    return '';
                                }
                                return optValue ? `${optValue}. ${optLabel}` : optLabel;
                            })
                            .filter(Boolean);

                        if (optionLines.length) {
                            answerTooltipText = optionLines.join('\n');
                        }
                    }

                    if (!answerTooltipText) {
                        answerTooltipText = answerLabel;
                    }

                    let answerTooltipOptions = [];
                    if (questionStructure.options && Array.isArray(questionStructure.options) && questionStructure.options.length) {
                        answerTooltipOptions = questionStructure.options
                            .map(opt => ({
                                value: opt && opt.value !== undefined ? opt.value : null,
                                label: opt && opt.label ? String(opt.label) : '',
                            }))
                            .filter(opt => opt && opt.label);
                    }

                    // Set stage badge
                    let stageClass = '';
                    let stageText = '';

                    switch(stage) {
                        case 'ej':
                            stageClass = 'ham-stage-not';
                            stageText = 'Ej etablerad';
                            break;
                        case 'trans':
                            stageClass = 'ham-stage-trans';
                            stageText = 'Utvecklas';
                            break;
                        case 'full':
                            stageClass = 'ham-stage-full';
                            stageText = 'Etablerad';
                            break;
                    }

                    const stageBadge = stage ? `<span class="ham-stage-badge ${stageClass}">${stageText}</span>` : '';

                    const safeQuestionText = escapeHtml(questionText);
                    const safeAnswerLabel = escapeHtml(answerLabel);
                    const safeAnswerTooltipText = escapeHtml(answerTooltipText);
                    const safeAnswerTooltipOptions = answerTooltipOptions.length ? escapeHtml(JSON.stringify(answerTooltipOptions)) : '';

                    // Create table row
                    const tableRow = `
                        <tr>
                            <td>${safeQuestionText}</td>
                            <td><span class="ham-tooltip-target" ${safeAnswerTooltipOptions ? `data-options="${safeAnswerTooltipOptions}"` : `data-full-text="${safeAnswerTooltipText}"`}>${safeAnswerLabel}</span></td>
                            <td>${stageBadge}</td>
                        </tr>
                    `;

                    // Append to container
                    $container.append(tableRow);
                });


            }
        }
    }

    /**
     * Initialize the section tabs.
     */
    function initSectionTabs() {
        const $sectionTabs = $('.ham-section-tab');
        const $sectionContents = $('.ham-section-content');

        $sectionTabs.on('click', function() {
            const section = $(this).data('section');

            // Update active tab
            $sectionTabs.removeClass('active');
            $(this).addClass('active');

            // Update active content
            $sectionContents.removeClass('active');
            $('.ham-section-content[data-section="' + section + '"]').addClass('active');
        });
    }

    /**
     * Initialize delete butts, should you needem.
     */
    function initDeleteButtons() {
        $('.ham-delete-assessment').on('click', function(e) {
            e.preventDefault();

            const $button = $(this);
            const assessmentId = $button.data('id');

            if (!assessmentId) {
                console.error('No assessment ID found');
                return;
            }

            if (!confirm('Är du säker på att du vill ta bort denna elevobservation? Detta går inte att ångra.')) {
                return;
            }

            $button.prop('disabled', true);

            $.ajax({
                url: hamAssessment.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ham_delete_assessment',
                    assessment_id: assessmentId,
                    nonce: hamAssessment.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Remove the table row with animation
                        $button.closest('tr').fadeOut(400, function() {
                            $(this).remove();
                        });
                    } else {
                        alert(response.data.message || 'Ett fel uppstod när observationen skulle tas bort.');
                        $button.prop('disabled', false);
                    }
                },
                error: function() {
                    alert('Ett fel uppstod när observationen skulle tas bort.');
                    $button.prop('disabled', false);
                }
            });
        });
    }
    // Initialize when document is ready
    $(document).ready(initAssessmentManager);

})(jQuery);
