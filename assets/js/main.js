// Main JavaScript for Njumbi High School Past Papers Repository

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // File upload preview
    const fileInput = document.getElementById('fileUpload');
    const filePreview = document.getElementById('filePreview');
    const fileNameDisplay = document.getElementById('fileName');
    
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                fileNameDisplay.textContent = file.name;
                
                // Show file details
                if (filePreview) {
                    filePreview.classList.remove('d-none');
                    
                    // Display file type icon based on extension
                    const fileIcon = document.getElementById('fileIcon');
                    const fileExt = file.name.split('.').pop().toLowerCase();
                    
                    if (fileIcon) {
                        if (fileExt === 'pdf') {
                            fileIcon.className = 'fas fa-file-pdf text-danger fa-3x';
                        } else if (['doc', 'docx'].includes(fileExt)) {
                            fileIcon.className = 'fas fa-file-word text-primary fa-3x';
                        } else if (['xls', 'xlsx'].includes(fileExt)) {
                            fileIcon.className = 'fas fa-file-excel text-success fa-3x';
                        } else if (['ppt', 'pptx'].includes(fileExt)) {
                            fileIcon.className = 'fas fa-file-powerpoint text-warning fa-3x';
                        } else {
                            fileIcon.className = 'fas fa-file-alt text-secondary fa-3x';
                        }
                    }
                    
                    // Display file size
                    const fileSize = document.getElementById('fileSize');
                    if (fileSize) {
                        const size = file.size;
                        let sizeStr = '';
                        
                        if (size < 1024) {
                            sizeStr = size + ' bytes';
                        } else if (size < 1024 * 1024) {
                            sizeStr = (size / 1024).toFixed(2) + ' KB';
                        } else {
                            sizeStr = (size / (1024 * 1024)).toFixed(2) + ' MB';
                        }
                        
                        fileSize.textContent = sizeStr;
                    }
                }
            }
        });
    }
    
    // Search functionality
    const searchInput = document.getElementById('searchInput');
    const searchResults = document.getElementById('searchResults');
    
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.trim().toLowerCase();
            
            if (query.length < 2) {
                if (searchResults) {
                    searchResults.classList.add('d-none');
                }
                return;
            }
            
            // In a real application, this would be an AJAX call to the server
            // For now, we'll simulate a search response
            setTimeout(() => {
                if (searchResults) {
                    searchResults.classList.remove('d-none');
                    searchResults.innerHTML = '<div class="p-3">Searching for "' + query + '"...</div>';
                    
                    // In a real app, this would be replaced with actual search results
                    // from the server
                }
            }, 300);
        });
    }
    
    // Department filter
    const departmentFilter = document.getElementById('departmentFilter');
    const subjectFilter = document.getElementById('subjectFilter');
    
    if (departmentFilter) {
        departmentFilter.addEventListener('change', function() {
            const selectedDept = this.value;
            
            // In a real app, this would update the subject dropdown based on the selected department
            // For now, we'll just log the selection
            console.log('Department selected:', selectedDept);
            
            // Enable the subject filter if a department is selected
            if (subjectFilter) {
                subjectFilter.disabled = selectedDept === '';
            }
        });
    }
    
    // Confirm delete
    const deleteButtons = document.querySelectorAll('.btn-delete');
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });
});