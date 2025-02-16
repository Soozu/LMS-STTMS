<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Initialize variables
$logs = [];
$error = null;

try {
    // Fetch both current and archived logs
    $stmt = $conn->prepare("
        SELECT 
            sl.*,
            u.username,
            u.user_type
        FROM system_logs sl 
        JOIN users u ON sl.user_id = u.id
        ORDER BY sl.created_at DESC
    ");
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    error_log("Error in system logs: " . $e->getMessage());
    $error = "An error occurred while fetching the logs.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs - STMA LMS</title>
    <link rel="stylesheet" href="css/system_logs.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h2><i class="fas fa-history"></i> System Logs</h2>
            <div class="header-actions">
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search by user, action, or description...">
                    <i class="fas fa-search"></i>
                </div>
                <select id="filterAction">
                    <option value="">All Activities</option>
                    <option value="add">Added Items</option>
                    <option value="update">Updated Items</option>
                    <option value="archive">Archived Items</option>
                    <option value="restore">Restored Items</option>
                    <option value="login">Login Activities</option>
                </select>
                <a href="archive_logs.php" class="view-archive-btn">
                    <i class="fas fa-calendar-alt"></i>
                    View Daily Logs
                </a>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (empty($logs)): ?>
            <div class="empty-state">
                <i class="fas fa-history"></i>
                <p>No system logs available</p>
                <span class="empty-state-description">System activities will appear here when users perform actions</span>
            </div>
        <?php else: ?>
            <div class="logs-container">
                <table class="logs-table">
                    <thead>
                        <tr>
                            <th width="15%">Time</th>
                            <th width="15%">User</th>
                            <th width="20%">Action</th>
                            <th width="50%">Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr class="log-row">
                                <td class="log-time">
                                    <?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?>
                                </td>
                                <td class="log-user">
                                    <div class="user-info">
                                        <span class="username"><?php echo htmlspecialchars($log['username']); ?></span>
                                        <span class="user-type"><?php echo htmlspecialchars($log['user_type']); ?></span>
                                    </div>
                                </td>
                                <td class="log-action">
                                    <?php
                                    $actionClass = 'info';
                                    $icon = 'fa-info-circle';
                                    $action = strtolower($log['action']);
                                    
                                    if (strpos($action, 'create') !== false || strpos($action, 'add') !== false) {
                                        $actionClass = 'create';
                                        $icon = 'fa-plus-circle';
                                    } elseif (strpos($action, 'update') !== false || strpos($action, 'edit') !== false) {
                                        $actionClass = 'update';
                                        $icon = 'fa-edit';
                                    } elseif (strpos($action, 'delete') !== false || strpos($action, 'archive') !== false) {
                                        $actionClass = 'delete';
                                        $icon = 'fa-trash';
                                    } elseif (strpos($action, 'restore') !== false) {
                                        $actionClass = 'restore';
                                        $icon = 'fa-history';
                                    } elseif (strpos($action, 'login') !== false) {
                                        $actionClass = 'login';
                                        $icon = 'fa-sign-in-alt';
                                    } elseif (strpos($action, 'logout') !== false) {
                                        $actionClass = 'logout';
                                        $icon = 'fa-sign-out-alt';
                                    }
                                    ?>
                                    <span class="action-badge <?php echo $actionClass; ?>">
                                        <i class="fas <?php echo $icon; ?>"></i>
                                        <?php echo htmlspecialchars($log['action']); ?>
                                    </span>
                                </td>
                                <td class="log-description">
                                    <?php echo htmlspecialchars($log['description']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="no-logs" style="display: none;">
            <i class="fas fa-search"></i>
            <p>No logs found matching your filters</p>
            <button onclick="resetFilters()" class="reset-button">Reset Filters</button>
        </div>
    </main>

    <script>
        function filterLogs() {
            const searchText = document.getElementById('searchInput').value.toLowerCase();
            const actionFilter = document.getElementById('filterAction').value.toLowerCase();
            const rows = document.querySelectorAll('.log-row');
            let visibleCount = 0;

            rows.forEach(row => {
                const rowText = row.textContent.toLowerCase();
                const actionBadge = row.querySelector('.action-badge');
                const actionText = actionBadge ? actionBadge.textContent.toLowerCase() : '';
                
                const matchesSearch = rowText.includes(searchText);
                const matchesAction = !actionFilter || actionText.includes(actionFilter);
                
                if (matchesSearch && matchesAction) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            const noResults = document.querySelector('.no-logs');
            noResults.style.display = visibleCount === 0 ? 'block' : 'none';
        }

        function resetFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('filterAction').value = '';
            filterLogs();
        }

        document.getElementById('searchInput').addEventListener('input', filterLogs);
        document.getElementById('filterAction').addEventListener('change', filterLogs);
    </script>
</body>
</html> 