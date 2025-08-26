</main>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery (required for some Bootstrap components) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="assets/js/student.js"></script>
    
    <script>
        // Initialize tooltips and popovers
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
            var popoverList = popoverTriggerList.map(function(popoverTriggerEl) {
                return new bootstrap.Popover(popoverTriggerEl);
            });
        });
        
        // Sidebar toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarCollapse = document.getElementById('sidebarCollapse');
            const sidebar = document.getElementById('sidebar');
            const content = document.getElementById('content');
            const sidebarToggleMobile = document.querySelector('.sidebar-toggle-mobile');
            
            if (sidebarCollapse) {
                sidebarCollapse.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                    content.classList.toggle('active');
                });
            }
            
            if (sidebarToggleMobile) {
                sidebarToggleMobile.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                    content.classList.toggle('active');
                });
            }
            
            // Close sidebar on mobile when clicking outside
            document.addEventListener('click', function(event) {
                const windowWidth = window.innerWidth;
                if (windowWidth <= 768) {
                    if (!sidebar.contains(event.target) && !sidebarCollapse.contains(event.target)) {
                        if (!sidebar.classList.contains('active')) {
                            sidebar.classList.add('active');
                            content.classList.add('active');
                        }
                    }
                }
            });
            
            // Handle window resize
            window.addEventListener('resize', function() {
                const windowWidth = window.innerWidth;
                if (windowWidth > 768) {
                    sidebar.classList.remove('active');
                    content.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>