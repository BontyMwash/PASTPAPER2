</main>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery (required for some Bootstrap components) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="assets/js/school-admin.js"></script>
    
    <script>
        // Initialize tooltips and popovers
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })
        
        var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
        var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl)
        })
        
        // Sidebar toggle functionality
        document.querySelectorAll('.sidebar-toggle-btn, #sidebarCollapse').forEach(function(button) {
            button.addEventListener('click', function() {
                document.getElementById('sidebar').classList.toggle('active');
                document.getElementById('content').classList.toggle('active');
            });
        });
        
        // Responsive sidebar behavior
        function checkWidth() {
            if (window.innerWidth < 768) {
                document.getElementById('sidebar').classList.add('active');
                document.getElementById('content').classList.add('active');
            } else {
                document.getElementById('sidebar').classList.remove('active');
                document.getElementById('content').classList.remove('active');
            }
        }
        
        // Check width on page load
        checkWidth();
        
        // Check width on window resize
        window.addEventListener('resize', checkWidth);
    </script>
</body>
</html>