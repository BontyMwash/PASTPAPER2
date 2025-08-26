/**
 * Student Dashboard JavaScript
 */

$(document).ready(function() {
    // Initialize Bootstrap tooltips and popovers
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // Toggle sidebar
    $('#sidebarToggle').on('click', function() {
        $('body').toggleClass('sidebar-collapsed');
        $('.sidebar').toggleClass('collapsed');
        $('.content').toggleClass('expanded');
    });

    // Handle favorite button clicks
    $('.btn-favorite').on('click', function() {
        const paperId = $(this).data('paper-id');
        const $button = $(this);
        const isFavorite = $button.hasClass('active');
        
        // AJAX call to add/remove favorite
        $.ajax({
            url: 'ajax/toggle_favorite.php',
            type: 'POST',
            data: {
                paper_id: paperId,
                action: isFavorite ? 'remove' : 'add'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    if (isFavorite) {
                        // Remove from favorites
                        $button.removeClass('active');
                        $button.find('i').removeClass('fas').addClass('far');
                        $button.attr('title', 'Add to favorites');
                        $button.attr('data-bs-original-title', 'Add to favorites');
                        
                        // If we're on the favorites page, remove the item
                        if (window.location.pathname.includes('favorites.php')) {
                            $button.closest('.favorite-item').fadeOut(300, function() {
                                $(this).remove();
                                
                                // Check if there are no more favorites
                                if ($('#favorites-container').children().length === 0) {
                                    location.reload(); // Reload to show empty state
                                }
                            });
                        }
                        
                        showToast('Removed from favorites', 'success');
                    } else {
                        // Add to favorites
                        $button.addClass('active');
                        $button.find('i').removeClass('far').addClass('fas');
                        $button.attr('title', 'Remove from favorites');
                        $button.attr('data-bs-original-title', 'Remove from favorites');
                        showToast('Added to favorites', 'success');
                    }
                    
                    // Reinitialize tooltip
                    var tooltip = bootstrap.Tooltip.getInstance($button[0]);
                    if (tooltip) {
                        tooltip.dispose();
                    }
                    new bootstrap.Tooltip($button[0]);
                } else {
                    showToast(response.message || 'An error occurred', 'danger');
                }
            },
            error: function() {
                showToast('An error occurred', 'danger');
            }
        });
    });

    // Paper filters
    $('#subject_id, #year').on('change', function() {
        if ($(this).val()) {
            $('#paperFilterForm').submit();
        }
    });

    // Clear search
    $('#clearSearch').on('click', function() {
        const url = new URL(window.location.href);
        url.searchParams.delete('search');
        window.location.href = url.toString();
    });
});

/**
 * Show a toast notification
 * @param {string} message - The message to display
 * @param {string} type - The type of toast (success, danger, warning, info)
 */
function showToast(message, type = 'success') {
    // Create toast container if it doesn't exist
    if ($('#toastContainer').length === 0) {
        $('body').append('<div id="toastContainer" class="toast-container position-fixed bottom-0 end-0 p-3"></div>');
    }
    
    // Create a unique ID for the toast
    const toastId = 'toast-' + Date.now();
    
    // Create toast HTML
    const toast = `
        <div id="${toastId}" class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;
    
    // Add toast to container
    $('#toastContainer').append(toast);
    
    // Initialize and show the toast
    const toastEl = document.getElementById(toastId);
    const bsToast = new bootstrap.Toast(toastEl, {
        autohide: true,
        delay: 3000
    });
    bsToast.show();
    
    // Remove toast from DOM after it's hidden
    $(toastEl).on('hidden.bs.toast', function() {
        $(this).remove();
    });
}

/**
 * Format a date string
 * @param {string} dateString - The date string to format
 * @returns {string} - The formatted date
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return date.toLocaleDateString('en-US', options);
}