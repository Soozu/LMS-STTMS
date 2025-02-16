<?php
// Get the current page name from the URL
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Check if this is the first load after login
$is_first_load = !isset($_SESSION['sidebar_animated']);
if ($is_first_load) {
    $_SESSION['sidebar_animated'] = true;
}
?>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo-wrapper">
            <img src="../images/logo.png" alt="School Logo" class="sidebar-logo">
        </div>
    </div>
    <nav class="sidebar-nav">
        <ul class="<?php echo $is_first_load ? 'animate-items' : ''; ?>">
            <li class="<?php echo $current_page === 'dashboard' ? 'active' : ''; ?>" style="--item-index: 0;">
                <a href="dashboard.php" data-title="Dashboard">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="<?php echo $current_page === 'classes' ? 'active' : ''; ?>" style="--item-index: 1;">
                <a href="classes.php" data-title="My Classes">
                    <i class="fas fa-chalkboard"></i>
                    <span>My Classes</span>
                </a>
            </li>
            <li class="<?php echo $current_page === 'students' ? 'active' : ''; ?>" style="--item-index: 2;">
                <a href="students.php" data-title="Students">
                    <i class="fas fa-users"></i>
                    <span>Students</span>
                </a>
            </li>
            <li class="<?php echo $current_page === 'assignments' ? 'active' : ''; ?>" style="--item-index: 3;">
                <a href="assignments.php" data-title="Assignments">
                    <i class="fas fa-tasks"></i>
                    <span>Assignments and Activities</span>
                </a>
            </li>
            <li class="<?php echo $current_page === 'reports' ? 'active' : ''; ?>" style="--item-index: 4;">
                <a href="reports.php" data-title="Reports">
                    <i class="fas fa-chart-line"></i>
                    <span>Reports</span>
                </a>
            </li>
            <li class="<?php echo $current_page === 'schedule' ? 'active' : ''; ?>" style="--item-index: 5;">
                <a href="schedule.php" data-title="Schedule">
                    <i class="fas fa-calendar"></i>
                    <span>Schedule</span>
                </a>
            </li>
            <li class="<?php echo $current_page === 'announcements' ? 'active' : ''; ?>" style="--item-index: 6;">
                <a href="announcements.php" data-title="Announcements">
                    <i class="fas fa-bullhorn"></i>
                    <span>Announcements</span>
                </a>
            </li>
            <li class="<?php echo $current_page === 'messages' ? 'active' : ''; ?>" style="--item-index: 7;">
                <a href="messages.php" data-title="Messages">
                    <i class="fas fa-envelope"></i>
                    <span>Messages</span>
                </a>
            </li>
            <li class="<?php echo $current_page === 'archive_management' ? 'active' : ''; ?>" style="--item-index: 8;">
                <a href="archive_management.php" data-title="Archive Management">
                    <i class="fas fa-archive"></i>
                    <span>Archive Management</span>
                </a>
            </li>
        </ul>
    </nav>
</aside> 