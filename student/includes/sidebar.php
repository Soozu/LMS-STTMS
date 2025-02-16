<?php
// Get the current page name from the URL
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Check if this is the first load after login
$is_first_load = !isset($_SESSION['sidebar_animated']);
if ($is_first_load) {
    $_SESSION['sidebar_animated'] = true;
}
?>

<nav class="sidebar" id="sidebar">
    <div class="sidebar-nav">
        <ul class="<?php echo $is_first_load ? 'animate-items' : ''; ?>">
            <li class="<?php echo $current_page === 'dashboard' ? 'active' : ''; ?>" style="--item-index: 0;">
                <a href="dashboard.php" data-title="Dashboard">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="<?php echo $current_page === 'subjects' ? 'active' : ''; ?>" style="--item-index: 1;">
                <a href="subjects.php" data-title="My Subjects">
                    <i class="fas fa-book"></i>
                    <span>My Subjects</span>
                </a>
            </li>
            <li class="<?php echo $current_page === 'assignments' ? 'active' : ''; ?>" style="--item-index: 2;">
                <a href="assignments.php" data-title="Assignments">
                    <i class="fas fa-tasks"></i>
                    <span>Assignments</span>
                </a>
            </li>
            <li class="<?php echo $current_page === 'activities' ? 'active' : ''; ?>" style="--item-index: 3;">
                <a href="activity.php" data-title="Activities">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Activities</span>
                </a>
            </li>
            <li class="<?php echo $current_page === 'calendar' ? 'active' : ''; ?>" style="--item-index: 4;">
                <a href="calendar.php" data-title="Calendar">
                    <i class="fas fa-calendar"></i>
                    <span>Calendar</span>
                </a>
            </li>
            <li class="<?php echo $current_page === 'announcements' ? 'active' : ''; ?>" style="--item-index: 5;">
                <a href="announcements.php" data-title="Announcements">
                    <i class="fas fa-bullhorn"></i>
                    <span>Announcements</span>
                </a>
            </li>
            <li class="<?php echo $current_page === 'messages' ? 'active' : ''; ?>" style="--item-index: 6;">
                <a href="messages.php" data-title="Messages">
                    <i class="fas fa-envelope"></i>
                    <span>Messages</span>
                </a>
            </li>
        </ul>
    </div>
</nav> 