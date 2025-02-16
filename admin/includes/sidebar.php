<?php
// Get the current page filename
$current_page = basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar" id="sidebar">
    <nav class="sidebar-nav">
        <ul>
            <li class="<?php echo ($current_page == 'dashboard.php') ? 'dashboard-active' : ''; ?>" style="--item-index: 0">
                <a href="dashboard.php" data-title="Dashboard">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="<?php echo ($current_page == 'classes.php') ? 'classes-active' : ''; ?>" style="--item-index: 1">
                <a href="classes.php" data-title="Classes">
                    <i class="fas fa-chalkboard"></i>
                    <span>Classes</span>
                </a>
            </li>
            <li class="<?php echo ($current_page == 'subjects.php') ? 'subjects-active' : ''; ?>" style="--item-index: 2">
                <a href="subjects.php" data-title="Subjects">
                    <i class="fas fa-book"></i>
                    <span>Subjects</span>
                </a>
            </li>
            <li class="<?php echo ($current_page == 'sections.php') ? 'sections-active' : ''; ?>" style="--item-index: 3">
                <a href="sections.php" data-title="Sections">
                    <i class="fas fa-layer-group"></i>
                    <span>Sections</span>
                </a>
            </li>
            <li class="<?php echo ($current_page == 'teachers.php') ? 'teachers-active' : ''; ?>" style="--item-index: 4">
                <a href="teachers.php" data-title="Teachers">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>Teachers</span>
                </a>
            </li>
            <li class="<?php echo ($current_page == 'students.php') ? 'students-active' : ''; ?>" style="--item-index: 5">
                <a href="students.php" data-title="Students">
                    <i class="fas fa-user-graduate"></i>
                    <span>Students</span>
                </a>
            </li>
            <li class="<?php echo ($current_page == 'reports.php') ? 'reports-active' : ''; ?>" style="--item-index: 6">
                <a href="reports.php" data-title="Reports">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </li>            
        </ul>
    </nav>
</aside> 