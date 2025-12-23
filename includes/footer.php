</div>
        <!-- /.content-wrapper -->
    </div>
    <!-- ./wrapper -->

    <!-- Page-level scripts only; Bootstrap bundle is loaded in header.php -->
    
    <!-- AdminLTE App -->
    <script>
        // Toggle Sidebar for Mobile
        function toggleSidebar() {
            const sidebar = document.getElementById('mainSidebar');
            sidebar.classList.toggle('sidebar-open');
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('mainSidebar');
            const toggle = document.querySelector('[onclick="toggleSidebar()"]');
            
            if (window.innerWidth < 992) {
                if (!sidebar.contains(event.target) && toggle && !toggle.contains(event.target)) {
                    sidebar.classList.remove('sidebar-open');
                }
            }
        });

        // Note: Dropdowns are initialized in header.php for consistency

        // Auto-open active menu item
        document.addEventListener('DOMContentLoaded', function() {
            const activeLink = document.querySelector('.nav-treeview .nav-link.active');
            if (activeLink) {
                const navItem = activeLink.closest('.nav-item.menu-open');
                if (navItem) {
                    navItem.classList.add('menu-open');
                }
            }
        });
    </script>
</body>
</html>