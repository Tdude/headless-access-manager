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

        const CHART_ANIMATION_DURATION_MS = 300;
        const CHART_ANIMATION_EASING = 'easeInOutQuad';
        const CHART_BASE_HUE = 205;
        const CHART_HUE_STEP = 28;
        const CHART_BORDER_WIDTH = 2;
        const CHART_POINT_RADIUS = 2;
        const CHART_LINE_TENSION = 0.25;
        const CHART_LINE_POINT_RADIUS = 3;
        const CHART_FILL_ALPHA = 0.80;
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
        const CHART_FONT_SIZE_LEGEND = 12;

        const t = (window.hamAssessment && window.hamAssessment.texts) ? window.hamAssessment.texts : {};
        const labelMonth = t.month || 'Month';
        const labelTerm = t.term || 'Term';
        const labelSchoolYear = t.schoolYear || 'School year';
        const labelHogstadium = t.hogstadium || 'Högstadium';
        const labelRadar = t.radar || 'Radar';
        const labelOption1 = t.option1 || 'Option 1';
        const labelOption2 = t.option2 || 'Option 2';
        const labelOption3 = t.option3 || 'Option 3';
        const labelOption4 = t.option4 || 'Option 4';
        const labelOption5 = t.option5 || 'Option 5';

        if (!overview && !stats) {
            return;
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

        function datasetKey(ds, fallbackIdx) {
            if (ds && ds.label) {
                return String(ds.label);
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
        }

        function renderGroupRadarValuesTable(containerId, bucketGroup) {
            const el = document.getElementById(containerId);
            if (!el) {
                return;
            }

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
                    html += `<td class="ham-radar-values-td-eval" style="box-shadow: inset ${CHART_TABLE_INSET_BORDER_PX}px 0 0 ${c.border};">${vv == null || Number.isNaN(vv) ? '—' : String(Math.round(vv))}</td>`;
                });
                html += '</tr>';
            }

            html += '</tbody></table></div>';
            el.innerHTML = html;
        }

        function buildGroupRadarToggle() {
            const canvas = document.getElementById('ham-group-radar');
            const btns = Array.from(document.querySelectorAll('.ham-group-radar-toggle-btn'));

            if (!canvas || btns.length === 0 || !stats || (stats.level !== 'school' && stats.level !== 'class') || !stats.group_radar || !stats.group_radar.buckets) {
                return;
            }

            const labels = Array.isArray(stats.group_radar.labels) ? stats.group_radar.labels : [];
            const bucketsByKey = stats.group_radar.buckets;

            const titleByKey = {
                month: labelMonth,
                term: labelTerm,
                school_year: labelSchoolYear,
                hogstadium: labelHogstadium,
            };

            function computeDynamicMax(datasets) {
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
                const buckets = bucketsByKey && Array.isArray(bucketsByKey[bucketKey]) ? bucketsByKey[bucketKey] : [];
                const bucket = pickLatestBucket(buckets);
                if (!bucket || !Array.isArray(bucket.datasets) || bucket.datasets.length === 0) {
                    return { title: titleByKey[bucketKey] || labelRadar, datasets: [], max: 1, bucketGroup: { labels, buckets: [] } };
                }

                const max = computeDynamicMax(bucket.datasets);

                const datasets = bucket.datasets.map((ds, idx) => {
                    const c = stableDatasetColor(ds, idx);
                    return {
                        label: ds.label,
                        data: Array.isArray(ds.values) ? ds.values.map((v) => clampNumber(v, 0, max)) : [],
                        borderColor: c.border,
                        backgroundColor: c.fill,
                        borderWidth: CHART_BORDER_WIDTH,
                        pointRadius: CHART_POINT_RADIUS,
                        fill: true,
                        borderDash: idx === 0 ? [] : CHART_OVERLAY_DASH,
                    };
                });

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
                                stepSize: Math.max(1, Math.ceil(initial.max / 5)),
                                showLabelBackdrop: false,
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
                chart.options.scales.r.ticks.stepSize = Math.max(1, Math.ceil(next.max / 5));
                chart.update();
                renderGroupRadarValuesTable('ham-group-radar-table', next.bucketGroup);
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
        }

        function initBucketToggle(options) {
            const {
                buttons,
                defaultKey,
                isKeyAvailable,
                onChange,
            } = options || {};

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
            if (typeof isKeyAvailable === 'function' && !isKeyAvailable(activeKey)) {
                activeKey = initialButtons[0].getAttribute('data-bucket') || defaultKey;
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
                // Keep UI consistent for any newly added buttons.
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

        // Student drilldown: we want multiple button groups (progress/radar/answers)
        // to share one bucket state and stay in sync.
        let studentBucketController = null;
        const studentBucketHandlers = [];

        function registerStudentBucketHandler(handler) {
            if (typeof handler !== 'function') {
                return;
            }
            studentBucketHandlers.push(handler);
            if (studentBucketController) {
                handler(studentBucketController.getActiveKey());
            }
        }

        function ensureStudentBucketController(buttons, defaultKey, isKeyAvailable) {
            if (studentBucketController) {
                if (typeof studentBucketController.addButtons === 'function') {
                    studentBucketController.addButtons(buttons);
                }
                return studentBucketController;
            }

            studentBucketController = initBucketToggle({
                buttons,
                defaultKey,
                isKeyAvailable,
                onChange: (bucketKey) => {
                    studentBucketHandlers.forEach((h) => h(bucketKey));
                },
            });

            return studentBucketController;
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
                const series = seriesByKey && Array.isArray(seriesByKey[bucketKey]) ? seriesByKey[bucketKey] : [];
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

        function buildDrilldownAvgProgressToggle() {
            const canvas = document.getElementById('ham-avg-progress-drilldown');
            const btnRoot = canvas ? canvas.closest('.ham-stats-panel') : null;
            const btns = Array.from((btnRoot || document).querySelectorAll('.ham-progress-toggle-btn'));

            if (!canvas || btns.length === 0 || !stats || (stats.level !== 'school' && stats.level !== 'class') || !stats.avg_progress) {
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
                const series = seriesByKey && Array.isArray(seriesByKey[bucketKey]) ? seriesByKey[bucketKey] : [];
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

            initBucketToggle({
                buttons: btns,
                defaultKey: 'month',
                isKeyAvailable: (key) => Array.isArray(seriesByKey[key]) && seriesByKey[key].length > 0,
                onChange: updateChart,
            });
        }

        function pickLatestBucket(buckets) {
            if (!Array.isArray(buckets) || buckets.length === 0) {
                return null;
            }
            return buckets[buckets.length - 1];
        }

        function renderAnswerAlternativesTable(containerId, radarQuestions, bucketGroup) {
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

            const bucket = pickLatestBucket(bucketGroup.buckets);
            if (!bucket || !Array.isArray(bucket.datasets) || bucket.datasets.length === 0) {
                el.innerHTML = '';
                return;
            }

            const datasets = bucket.datasets;

            // Fade older datasets in the answer alternatives table. We assume datasets
            // are ordered newest -> oldest (idx 0 is most recent).
            function datasetRecencyAlpha(datasetIndex) {
                const idx = Math.max(0, Number.isFinite(datasetIndex) ? datasetIndex : 0);
                const latestAlpha = 0.70;
                const decay = 0.65;
                const minAlpha = 0.10;
                const a = latestAlpha * Math.pow(decay, idx);
                return Math.max(minAlpha, a);
            }

            function datasetRecencyStrength(datasetIndex) {
                const idx = Math.max(0, Number.isFinite(datasetIndex) ? datasetIndex : 0);
                const decay = 0.65;
                const minStrength = 0.12;
                const s = Math.pow(decay, idx);
                return Math.max(minStrength, Math.min(1, s));
            }

            function selectionAlpha(selectedDatasetIndexes) {
                if (!Array.isArray(selectedDatasetIndexes) || selectedDatasetIndexes.length === 0) {
                    return 0;
                }
                let maxA = 0;
                selectedDatasetIndexes.forEach((di) => {
                    maxA = Math.max(maxA, datasetRecencyAlpha(di));
                });
                // If multiple datasets share the same choice, bump slightly, but cap.
                const bump = Math.min(0.10, Math.max(0, selectedDatasetIndexes.length - 1) * 0.03);
                return Math.min(0.80, maxA + bump);
            }

            function selectionStrength(selectedDatasetIndexes) {
                if (!Array.isArray(selectedDatasetIndexes) || selectedDatasetIndexes.length === 0) {
                    return 0;
                }
                // Newest dataset is idx 0, so the selection "age" is the smallest index.
                const newestIdx = Math.min.apply(null, selectedDatasetIndexes.map((di) => (Number.isFinite(di) ? di : 0)));
                return datasetRecencyStrength(newestIdx);
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
                        const alpha = selectionAlpha(sel);
                        const strength = selectionStrength(sel);
                        style = ` style="background-color: ${optionBgColor(oi, alpha, strength)};"`;
                    }

                    html += `<td class="${cls}"${style}>${escapeHtml(optText)}</td>`;
                }

                html += '</tr>';
            }

            html += '</tbody></table></div>';
            el.innerHTML = html;
        }

        function renderRadarValuesTable(containerId, bucketGroup) {
            const el = document.getElementById(containerId);
            if (!el) {
                return;
            }

            if (!bucketGroup || !Array.isArray(bucketGroup.labels) || !Array.isArray(bucketGroup.buckets)) {
                el.innerHTML = '';
                return;
            }

            const bucket = pickLatestBucket(bucketGroup.buckets);
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
                    html += `<td class="ham-radar-values-td-eval" style="box-shadow: inset ${CHART_TABLE_INSET_BORDER_PX}px 0 0 ${c.border};">${vv == null ? '—' : vv.toFixed(1)}</td>`;
                });
                html += '</tr>';
            }

            html += '</tbody></table></div>';
            el.innerHTML = html;
        }

        function buildRadarChart(canvasId, bucketGroup, title) {
            const el = document.getElementById(canvasId);
            if (!el || !bucketGroup || !bucketGroup.buckets) {
                return;
            }

            const existing = Chart.getChart(el);
            if (existing) {
                existing.destroy();
            }

            const bucket = pickLatestBucket(bucketGroup.buckets);
            if (!bucket || !Array.isArray(bucket.datasets) || bucket.datasets.length === 0) {
                return;
            }

            const labels = Array.isArray(bucketGroup.labels) ? bucketGroup.labels : [];

            const datasets = bucket.datasets.map((ds, idx) => {
                const c = stableDatasetColor(ds, idx);
                return {
                    label: ds.label,
                    data: Array.isArray(ds.values) ? ds.values.map((v) => clampNumber(v, 1, 5)) : [],
                    borderColor: c.border,
                    backgroundColor: c.fill,
                    borderWidth: CHART_BORDER_WIDTH,
                    pointRadius: CHART_POINT_RADIUS,
                    fill: true,
                    borderDash: idx === 0 ? [] : CHART_OVERLAY_DASH,
                };
            });

            new Chart(el.getContext('2d'), {
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
                            position: 'bottom',
                            labels: {
                                font: { size: CHART_FONT_SIZE_LEGEND },
                            },
                        },
                        title: {
                            display: Boolean(bucket.label || title),
                            text: bucket.label || title,
                            font: { size: CHART_FONT_SIZE_TITLE },
                        },
                    },
                    scales: {
                        r: {
                            min: 1,
                            max: 5,
                            ticks: {
                                stepSize: 1,
                                showLabelBackdrop: false,
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

        function buildStudentRadarToggle() {
            const canvas = document.getElementById('ham-student-radar');
            const btns = Array.from(document.querySelectorAll('.ham-radar-toggle-btn'));
            const answerBtns = Array.from(document.querySelectorAll('.ham-answer-toggle-btn'));
            const progressBtns = Array.from(document.querySelectorAll('.ham-progress-toggle-btn'));
            const allBtns = btns.concat(answerBtns).concat(progressBtns);

            if (!canvas || allBtns.length === 0 || !stats || stats.level !== 'student' || !stats.student_radar || !stats.student_radar.buckets) {
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

            function buildDatasetsForBucket(bucketKey) {
                const buckets = bucketsByKey && bucketsByKey[bucketKey] ? bucketsByKey[bucketKey] : [];
                const bucket = pickLatestBucket(buckets);
                if (!bucket || !Array.isArray(bucket.datasets) || bucket.datasets.length === 0) {
                    return { title: titleByKey[bucketKey] || labelRadar, datasets: [] };
                }

                const datasets = bucket.datasets.map((ds, idx) => {
                    const c = stableDatasetColor(ds, idx);
                    return {
                        label: ds.label,
                        data: Array.isArray(ds.values) ? ds.values.map((v) => clampNumber(v, 1, 5)) : [],
                        borderColor: c.border,
                        backgroundColor: c.fill,
                        borderWidth: CHART_BORDER_WIDTH,
                        pointRadius: CHART_POINT_RADIUS,
                        fill: true,
                        borderDash: idx === 0 ? [] : CHART_OVERLAY_DASH,
                    };
                });

                return { title: bucket.label || (titleByKey[bucketKey] || labelRadar), datasets };
            }

            function renderTableForBucket(bucketKey) {
                renderRadarValuesTable('ham-student-radar-table', {
                    labels,
                    buckets: bucketsByKey[bucketKey] || [],
                });

                renderAnswerAlternativesTable('ham-answer-alternatives', stats.radar_questions || [], {
                    labels,
                    buckets: bucketsByKey[bucketKey] || [],
                });
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
                            min: 1,
                            max: 5,
                            ticks: {
                                stepSize: 1,
                                showLabelBackdrop: false,
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
                chart.update();
                renderTableForBucket(bucketKey);
            }

            registerStudentBucketHandler(updateChart);

            ensureStudentBucketController(
                allBtns,
                'month',
                (key) => {
                    const radarOk = Array.isArray(bucketsByKey[key]) && bucketsByKey[key].length > 0;
                    const progressOk = stats.avg_progress && Array.isArray(stats.avg_progress[key]) && stats.avg_progress[key].length > 0;
                    return radarOk || progressOk;
                }
            );
        }

        function buildOverviewRadarChart(canvasId, radar) {
            const el = document.getElementById(canvasId);
            if (!el || !radar || !Array.isArray(radar.labels) || !Array.isArray(radar.values) || radar.labels.length === 0) {
                return;
            }

            const existing = Chart.getChart(el);
            if (existing) {
                existing.destroy();
            }

            const c = datasetColor(0);
            new Chart(el.getContext('2d'), {
                type: 'radar',
                data: {
                    labels: radar.labels,
                    datasets: [{
                        label: radar.title || labelRadar,
                        data: radar.values.map((v) => clampNumber(v, 1, 5)),
                        borderColor: c.border,
                        backgroundColor: c.fill,
                        borderWidth: CHART_BORDER_WIDTH,
                        pointRadius: CHART_POINT_RADIUS,
                        fill: true,
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
                            display: Boolean(radar.title),
                            text: radar.title,
                            font: { size: CHART_FONT_SIZE_TITLE },
                        },
                    },
                    scales: {
                        r: {
                            min: 1,
                            max: 5,
                            ticks: {
                                stepSize: 1,
                                showLabelBackdrop: false,
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

        // School/class radar chart + bucket toggle (counts mode)
        if (stats && (stats.level === 'school' || stats.level === 'class') && stats.group_radar && stats.group_radar.buckets) {
            buildGroupRadarToggle();
        }

        // School/class drilldown avg progress toggle
        if (stats && (stats.level === 'school' || stats.level === 'class') && stats.avg_progress) {
            buildDrilldownAvgProgressToggle();
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

            if (!confirm('Är du säker på att du vill ta bort denna bedömning? Detta går inte att ångra.')) {
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
                        alert(response.data.message || 'Ett fel uppstod när bedömningen skulle tas bort.');
                        $button.prop('disabled', false);
                    }
                },
                error: function() {
                    alert('Ett fel uppstod när bedömningen skulle tas bort.');
                    $button.prop('disabled', false);
                }
            });
        });
    }
    // Initialize when document is ready
    $(document).ready(initAssessmentManager);

})(jQuery);
