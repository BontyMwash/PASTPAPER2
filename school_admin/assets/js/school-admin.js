/**
 * School Admin Dashboard JavaScript
 * Provides interactive functionality for the school admin interface
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips and popovers
    initTooltipsAndPopovers();
    
    // Initialize delete confirmations
    initDeleteConfirmations();
    
    // Initialize status toggles
    initStatusToggles();
    
    // Initialize form validations
    initFormValidations();
    
    // Initialize file inputs
    initCustomFileInputs();
});

/**
 * Initialize Bootstrap tooltips and popovers
 */
function initTooltipsAndPopovers() {
    // Tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function(popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
}

/**
 * Initialize delete confirmation dialogs
 */
function initDeleteConfirmations() {
    document.querySelectorAll('.delete-btn').forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            var targetUrl = this.getAttribute('href');
            var itemName = this.getAttribute('data-item-name') || 'item';
            
            if (confirm('Are you sure you want to delete this ' + itemName + '? This action cannot be undone.')) {
                window.location.href = targetUrl;
            }
        });
    });
}

/**
 * Initialize status toggle switches
 */
function initStatusToggles() {
    document.querySelectorAll('.status-toggle').forEach(function(toggle) {
        toggle.addEventListener('change', function() {
            var itemId = this.getAttribute('data-id');
            var itemType = this.getAttribute('data-type');
            var status = this.checked ? 'active' : 'inactive';
            var url = 'ajax/update_status.php';
            
            // Show loading indicator
            var loadingIndicator = document.getElementById('loading-indicator');
            if (loadingIndicator) loadingIndicator.style.display = 'block';
            
            // Send AJAX request to update status
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'id=' + itemId + '&type=' + itemType + '&status=' + status
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    showNotification('Status updated successfully', 'success');
                } else {
                    // Show error message and revert toggle
                    showNotification('Failed to update status: ' + data.message, 'danger');
                    toggle.checked = !toggle.checked;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred while updating status', 'danger');
                toggle.checked = !toggle.checked;
            })
            .finally(() => {
                // Hide loading indicator
                if (loadingIndicator) loadingIndicator.style.display = 'none';
            });
        });
    });
}

/**
 * Initialize form validations
 */
function initFormValidations() {
    // Get all forms with the 'needs-validation' class
    var forms = document.querySelectorAll('.needs-validation');
    
    // Loop over them and prevent submission
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
    
    // Password confirmation validation
    var passwordField = document.getElementById('password');
    var confirmPasswordField = document.getElementById('confirm_password');
    
    if (passwordField && confirmPasswordField) {
        confirmPasswordField.addEventListener('input', function() {
            if (passwordField.value !== confirmPasswordField.value) {
                confirmPasswordField.setCustomValidity('Passwords do not match');
            } else {
                confirmPasswordField.setCustomValidity('');
            }
        });
        
        passwordField.addEventListener('input', function() {
            if (confirmPasswordField.value !== '') {
                if (passwordField.value !== confirmPasswordField.value) {
                    confirmPasswordField.setCustomValidity('Passwords do not match');
                } else {
                    confirmPasswordField.setCustomValidity('');
                }
            }
        });
    }
}

/**
 * Initialize custom file inputs
 */
function initCustomFileInputs() {
    document.querySelectorAll('.custom-file-input').forEach(function(input) {
        input.addEventListener('change', function() {
            var fileName = this.files[0].name;
            var label = this.nextElementSibling;
            label.textContent = fileName;
        });
    });
}

/**
 * Show notification message
 * @param {string} message - The message to display
 * @param {string} type - The type of notification (success, danger, warning, info)
 */
function showNotification(message, type = 'info') {
    // Create notification container if it doesn't exist
    var container = document.getElementById('notification-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'notification-container';
        container.style.position = 'fixed';
        container.style.top = '20px';
        container.style.right = '20px';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }
    
    // Create notification element
    var notification = document.createElement('div');
    notification.className = 'alert alert-' + type + ' alert-dismissible fade show';
    notification.role = 'alert';
    notification.innerHTML = message + 
        '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    
    // Add notification to container
    container.appendChild(notification);
    
    // Initialize the Bootstrap alert
    var alert = new bootstrap.Alert(notification);
    
    // Auto-dismiss after 5 seconds
    setTimeout(function() {
        alert.close();
    }, 5000);
}

/**
 * Handle school selection for admin assignment
 */
function handleSchoolSelection() {
    var schoolSelect = document.getElementById('school_id');
    var adminSelect = document.getElementById('admin_id');
    
    if (schoolSelect && adminSelect) {
        schoolSelect.addEventListener('change', function() {
            var schoolId = this.value;
            
            if (schoolId) {
                // Show loading indicator
                adminSelect.innerHTML = '<option value="">Loading...</option>';
                adminSelect.disabled = true;
                
                // Fetch available admins for the selected school
                fetch('ajax/get_school_admins.php?school_id=' + schoolId)
                    .then(response => response.json())
                    .then(data => {
                        adminSelect.innerHTML = '<option value="">Select Admin</option>';
                        
                        if (data.success && data.admins.length > 0) {
                            data.admins.forEach(function(admin) {
                                var option = document.createElement('option');
                                option.value = admin.id;
                                option.textContent = admin.name + ' (' + admin.email + ')';
                                adminSelect.appendChild(option);
                            });
                            adminSelect.disabled = false;
                        } else {
                            adminSelect.innerHTML = '<option value="">No admins available</option>';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        adminSelect.innerHTML = '<option value="">Error loading admins</option>';
                    });
            } else {
                adminSelect.innerHTML = '<option value="">Select school first</option>';
                adminSelect.disabled = true;
            }
        });
    }
}