<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Get selected date (default to today)
$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Initialize variables
$logs = [];
$error = null;

try {
    // Fetch logs for the selected date
    $stmt = $conn->prepare("
        SELECT 
            sl.*,
            u.username,
            u.user_type
        FROM system_logs sl 
        JOIN users u ON sl.user_id = u.id
        WHERE DATE(sl.created_at) = :selected_date
        ORDER BY sl.created_at DESC
    ");
    
    $stmt->bindParam(':selected_date', $selectedDate);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get unique dates with logs for the datepicker
    $dateStmt = $conn->query("
        SELECT DISTINCT DATE(created_at) as log_date 
        FROM system_logs 
        ORDER BY log_date DESC
    ");
    $availableDates = $dateStmt->fetchAll(PDO::FETCH_COLUMN);

} catch(PDOException $e) {
    error_log("Error in archive logs: " . $e->getMessage());
    $error = "An error occurred while fetching the logs.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Logs Archive - STMA LMS</title>
    <link rel="stylesheet" href="css/system_logs.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h2><i class="fas fa-archive"></i> Daily Logs Archive</h2>
            <div class="header-actions">
                <div class="date-picker-container">
                    <input type="text" id="datePicker" value="<?php echo $selectedDate; ?>" placeholder="Select date">
                    <i class="fas fa-calendar"></i>
                </div>
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search logs...">
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
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="daily-summary">
            <div class="summary-card">
                <i class="fas fa-chart-bar"></i>
                <div class="summary-info">
                    <span class="summary-count"><?php echo count($logs); ?></span>
                    <span class="summary-label">Total Activities</span>
                </div>
            </div>
            <!-- Add more summary cards as needed -->
        </div>

        <?php if (empty($logs)): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-day"></i>
                <p>No logs found for <?php echo date('F d, Y', strtotime($selectedDate)); ?></p>
                <span class="empty-state-description">Try selecting a different date</span>
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
                                    <?php echo date('H:i:s', strtotime($log['created_at'])); ?>
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
        // Initialize date picker
        flatpickr("#datePicker", {
            dateFormat: "Y-m-d",
            defaultDate: "<?php echo $selectedDate; ?>",
            enable: <?php echo json_encode($availableDates); ?>,
            onChange: function(selectedDates, dateStr) {
                window.location.href = 'archive_logs.php?date=' + dateStr;
            }
        });

        // Filtering functionality
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