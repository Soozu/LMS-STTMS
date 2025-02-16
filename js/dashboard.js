document.addEventListener('DOMContentLoaded', function() {
    // Sidebar Toggle
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    
    sidebarToggle.addEventListener('click', function() {
        sidebar.classList.toggle('active');
    });

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(event) {
        if (window.innerWidth <= 768) {
            if (!sidebar.contains(event.target) && !sidebarToggle.contains(event.target)) {
                sidebar.classList.remove('active');
            }
        }
    });

    // User Menu Dropdown
    const userMenu = document.querySelector('.user-menu');
    const dropdownMenu = document.querySelector('.dropdown-menu');

    userMenu.addEventListener('click', function(event) {
        dropdownMenu.classList.toggle('active');
        event.stopPropagation();
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function() {
        dropdownMenu.classList.remove('active');
    });

    // Notification dropdown toggle
    const notificationBtn = document.querySelector('.notification-btn');
    const notificationDropdown = document.querySelector('.notification-dropdown');

    notificationBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        notificationDropdown.classList.toggle('active');
    });

    // Close notification dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!notificationDropdown.contains(e.target)) {
            notificationDropdown.classList.remove('active');
        }
    });

    // Mark all as read functionality
    const markAllRead = document.querySelector('.mark-all-read');
    const unreadNotifications = document.querySelectorAll('.notification-item.unread');

    markAllRead.addEventListener('click', function(e) {
        e.preventDefault();
        unreadNotifications.forEach(notification => {
            notification.classList.remove('unread');
        });
        // Update notification badge
        document.querySelector('.notification-badge').style.display = 'none';
    });
}); 