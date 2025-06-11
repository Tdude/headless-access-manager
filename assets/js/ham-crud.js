// Generic AJAX CRUD utility for CPTs in WP Admin
window.hamCrudPost = function(postType, actionType, postId, data, callback) {
    jQuery.ajax({
        url: hamGlobals.ajaxUrl,
        method: 'POST',
        data: {
            action: 'ham_crud_post',
            post_type: postType,
            action_type: actionType,
            post_id: postId,
            data: data
        },
        success: callback,
        error: function(xhr, status, error) {
            alert('AJAX error: ' + error);
        }
    });
};

(function($) {
    // Utility to get current CPT from body class
    function getCurrentCPT() {
        var match = document.body.className.match(/post-type-([a-zA-Z0-9_]+)/);
        return match ? match[1] : null;
    }

    // Open modal and populate for add/edit
    function openCrudModal(mode, postId, postTitle) {
        var $modal = $('#ham-crud-modal');
        var modalTitle = (mode === 'edit') ? 'Edit' : 'Add New';
        var value = postTitle || '';
        var html = '';
        html += '<h2>' + modalTitle + ' ' + getCurrentCPT().charAt(0).toUpperCase() + getCurrentCPT().slice(1) + '</h2>';
        html += '<form id="ham-crud-form">';
        html += '<label>Title<br><input type="text" name="post_title" value="' + value.replace(/"/g, '&quot;') + '" required></label><br><br>';
        html += '<input type="hidden" name="post_id" value="' + (postId || '') + '">';
        html += '<button type="submit" class="button button-primary">' + (mode === 'edit' ? 'Save' : 'Create') + '</button>';
        html += '</form>';
        $('#ham-crud-modal-content').html(html);
        $modal.fadeIn(150);
    }

    // Close modal
    function closeCrudModal() {
        $('#ham-crud-modal').fadeOut(100);
    }

    // Get post title from row (for edit)
    function getRowTitle($btn) {
        // Try to find the title cell in the same row
        var $row = $btn.closest('tr');
        var $titleCell = $row.find('.row-title');
        return $titleCell.length ? $titleCell.text().trim() : '';
    }

    // Add New button handler
    $(document).on('click', '#ham-crud-add-new', function(e) {
        e.preventDefault();
        openCrudModal('add');
    });

    // Edit button handler
    $(document).on('click', '.ham-crud-edit', function(e) {
        e.preventDefault();
        var postId = $(this).data('id');
        var postTitle = getRowTitle($(this));
        openCrudModal('edit', postId, postTitle);
    });

    // Close modal handler
    $(document).on('click', '#ham-crud-modal-close', function(e) {
        e.preventDefault();
        closeCrudModal();
    });

    // Form submit handler (Add/Edit)
    $(document).on('submit', '#ham-crud-form', function(e) {
        e.preventDefault();
        var $form = $(this);
        var postType = getCurrentCPT();
        var postId = $form.find('[name="post_id"]').val();
        var title = $form.find('[name="post_title"]').val();
        var actionType = postId ? 'update' : 'create';
        window.hamCrudPost(postType, actionType, postId, {post_title: title}, function(resp) {
            if (resp && resp.success) {
                location.reload();
            } else {
                alert('Error: ' + (resp && resp.data ? resp.data : 'Unknown error'));
            }
        });
    });

    // Delete button handler
    $(document).on('click', '.ham-crud-delete', function(e) {
        e.preventDefault();
        var postId = $(this).data('id');
        var postType = getCurrentCPT();
        if (confirm('Are you sure you want to delete this item?')) {
            window.hamCrudPost(postType, 'delete', postId, {}, function(resp) {
                if (resp && resp.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (resp && resp.data ? resp.data : 'Unknown error'));
                }
            });
        }
    });

    // Hide modal on overlay click
    $(document).on('click', '#ham-crud-modal', function(e) {
        if (e.target === this) closeCrudModal();
    });
})(jQuery);
