document.addEventListener('DOMContentLoaded', function() {
    const body = document.body;
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    
    // Create overlay element
    const overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    document.body.appendChild(overlay);

    // Toggle sidebar
    sidebarToggle.addEventListener('click', function() {
        if (window.innerWidth <= 768) {
            body.classList.toggle('sidebar-active');
        } else {
            body.classList.toggle('sidebar-collapsed');
        }
    });

    // Close sidebar when clicking overlay
    overlay.addEventListener('click', function() {
        body.classList.remove('sidebar-active');
    });

    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            body.classList.remove('sidebar-active');
        }
    });
}); 