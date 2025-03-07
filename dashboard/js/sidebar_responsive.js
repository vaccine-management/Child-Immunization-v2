// Sidebar and Content Responsive Adjustment
function initSidebarResponsive() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const mobileSidebarToggle = document.getElementById('sidebar-mobile-toggle');
    
    if (!sidebar || !mainContent) {
        console.error('Sidebar or main content elements not found');
        return;
    }
    
    // Handle mobile sidebar toggle
    if (mobileSidebarToggle) {
        mobileSidebarToggle.addEventListener('click', function() {
            if (sidebar.classList.contains('-translate-x-full')) {
                // Show sidebar
                sidebar.classList.remove('-translate-x-full');
                sidebar.classList.add('translate-x-0', 'w-64');
                
                // Add overlay
                const overlay = document.createElement('div');
                overlay.id = 'sidebar-overlay';
                overlay.className = 'fixed inset-0 bg-black bg-opacity-50 z-30 lg:hidden';
                document.body.appendChild(overlay);
                
                // Handle overlay click to close sidebar
                overlay.addEventListener('click', function() {
                    sidebar.classList.add('-translate-x-full');
                    sidebar.classList.remove('translate-x-0', 'w-64');
                    overlay.remove();
                });
            } else {
                // Hide sidebar
                sidebar.classList.add('-translate-x-full');
                sidebar.classList.remove('translate-x-0', 'w-64');
                
                // Remove overlay
                const overlay = document.getElementById('sidebar-overlay');
                if (overlay) {
                    overlay.remove();
                }
            }
        });
    }
    
    // Handle window resize events
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 1024) { // lg breakpoint
            // Remove overlay on large screens
            const overlay = document.getElementById('sidebar-overlay');
            if (overlay) {
                overlay.remove();
            }
            
            // Reset sidebar on large screens
            sidebar.classList.remove('-translate-x-full');
            sidebar.classList.add('lg:translate-x-0');
        } else {
            // Hide sidebar on small screens if not manually opened
            if (!sidebar.classList.contains('translate-x-0')) {
                sidebar.classList.add('-translate-x-full');
            }
        }
    });
    
    // Handle sidebar pin toggle if available
    if (sidebarToggle) {
        let sidebarPinned = false;
        
        sidebarToggle.addEventListener('click', function() {
            sidebarPinned = !sidebarPinned;
            
            if (sidebarPinned) {
                // Pin sidebar in expanded state
                sidebar.classList.add('sidebar-expanded', 'w-64');
                sidebar.classList.remove('sidebar-collapsed', 'w-[70px]');
                sidebar.classList.add('hover:w-64');
                
                // Change icon to indicate pinned state
                const toggleIcon = sidebarToggle.querySelector('i');
                if (toggleIcon) {
                    toggleIcon.classList.remove('fa-thumbtack');
                    toggleIcon.classList.add('fa-thumbtack', 'text-blue-400');
                    sidebarToggle.classList.add('rotate-45');
                }
                
                // Adjust opacity for toggle button to always show
                sidebarToggle.classList.remove('opacity-0', 'group-hover:opacity-100');
                sidebarToggle.classList.add('opacity-100');
                
                // Adjust main content
                mainContent.classList.add('ml-64');
                mainContent.classList.remove('ml-[70px]');
                
                // Show all labels
                const labels = sidebar.querySelectorAll('span, p.uppercase');
                labels.forEach(function(label) {
                    label.classList.remove('opacity-0', 'group-hover:opacity-100');
                    label.classList.add('opacity-100');
                });
            } else {
                // Unpin sidebar
                sidebar.classList.remove('sidebar-expanded', 'w-64');
                sidebar.classList.add('sidebar-collapsed', 'w-[70px]');
                
                // Revert icon
                const toggleIcon = sidebarToggle.querySelector('i');
                if (toggleIcon) {
                    toggleIcon.classList.add('fa-thumbtack');
                    toggleIcon.classList.remove('text-blue-400');
                    sidebarToggle.classList.remove('rotate-45');
                }
                
                // Revert opacity for hover behavior
                sidebarToggle.classList.add('opacity-0', 'group-hover:opacity-100');
                sidebarToggle.classList.remove('opacity-100');
                
                // Adjust main content
                mainContent.classList.remove('ml-64');
                mainContent.classList.add('ml-[70px]');
                
                // Revert labels to hover behavior
                const labels = sidebar.querySelectorAll('span, p.uppercase');
                labels.forEach(function(label) {
                    label.classList.add('opacity-0', 'group-hover:opacity-100');
                    label.classList.remove('opacity-100');
                });
            }
            
            // Trigger window resize to redraw charts
            window.dispatchEvent(new Event('resize'));
        });
    }
}