<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$error = null;
$success = null;

try {
    switch ($action) {
        case 'create':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $title = trim($_POST['title']);
                $content = trim($_POST['content']);
                
                if (empty($title) || empty($content)) {
                    throw new Exception('Title and content are required.');
                }

                $stmt = $conn->prepare("
                    INSERT INTO announcements (title, content, admin_id)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$title, $content, $_SESSION['role_id']]);

                // Log the action
                $stmt = $conn->prepare("
                    INSERT INTO system_logs (user_id, action, description)
                    VALUES (?, 'Create Announcement', ?)
                ");
                $stmt->execute([$_SESSION['user_id'], "Created announcement: $title"]);

                $_SESSION['success_message'] = "Announcement created successfully.";
                header('Location: announcements.php');
                exit();
            }
            break;

        case 'edit':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $title = trim($_POST['title']);
                $content = trim($_POST['content']);
                
                if (empty($title) || empty($content)) {
                    throw new Exception('Title and content are required.');
                }

                $stmt = $conn->prepare("
                    UPDATE announcements 
                    SET title = ?, content = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmt->execute([$title, $content, $id]);

                // Log the action
                $stmt = $conn->prepare("
                    INSERT INTO system_logs (user_id, action, description)
                    VALUES (?, 'Update Announcement', ?)
                ");
                $stmt->execute([$_SESSION['user_id'], "Updated announcement: $title"]);

                $_SESSION['success_message'] = "Announcement updated successfully.";
                header('Location: announcements.php');
                exit();
            }

            // Fetch announcement details for editing
            $stmt = $conn->prepare("SELECT * FROM announcements WHERE id = ?");
            $stmt->execute([$id]);
            $announcement = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$announcement) {
                throw new Exception('Announcement not found.');
            }
            break;

        case 'delete':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $id = (int)$_POST['id'];
                
                $stmt = $conn->prepare("SELECT title FROM announcements WHERE id = ?");
                $stmt->execute([$id]);
                $title = $stmt->fetchColumn();

                $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
                $stmt->execute([$id]);

                // Log the action
                $stmt = $conn->prepare("
                    INSERT INTO system_logs (user_id, action, description)
                    VALUES (?, 'Delete Announcement', ?)
                ");
                $stmt->execute([$_SESSION['user_id'], "Deleted announcement: $title"]);

                $_SESSION['success_message'] = "Announcement deleted successfully.";
                header('Location: announcements.php');
                exit();
            }
            break;

        default:
            // Fetch all announcements
            $stmt = $conn->prepare("
                SELECT a.*, CONCAT(adm.first_name, ' ', adm.last_name) as posted_by
                FROM announcements a
                JOIN admins adm ON a.admin_id = adm.id
                ORDER BY a.created_at DESC
            ");
            $stmt->execute();
            $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
    }
} catch(Exception $e) {
    $error = $e->getMessage();
}

// Check for success message
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Announcements - STMA LMS</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/announcements.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if ($action === 'list'): ?>
            <div class="page-header">
                <h2>Announcements</h2>
                <a href="?action=create" class="btn-create">
                    <i class="fas fa-plus"></i> Create Announcement
                </a>
            </div>

            <div class="announcements-list">
                <?php if (!empty($announcements)): ?>
                    <?php foreach ($announcements as $announcement): ?>
                        <div class="announcement-card">
                            <div class="announcement-header">
                                <h3><?php echo htmlspecialchars($announcement['title']); ?></h3>
                                <div class="announcement-actions">
                                    <a href="?action=edit&id=<?php echo $announcement['id']; ?>" 
                                       class="btn-edit" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form action="?action=delete" method="POST" class="delete-form" 
                                          onsubmit="return confirm('Are you sure you want to delete this announcement?');">
                                        <input type="hidden" name="id" value="<?php echo $announcement['id']; ?>">
                                        <button type="submit" class="btn-delete" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <div class="announcement-content">
                                <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                            </div>
                            <div class="announcement-meta">
                                <span>Posted by: <?php echo htmlspecialchars($announcement['posted_by']); ?></span>
                                <span>Posted on: <?php echo date('M d, Y h:i A', strtotime($announcement['created_at'])); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-bullhorn"></i>
                        <p>No announcements found</p>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($action === 'create' || $action === 'edit'): ?>
            <div class="page-header">
                <h2><?php echo $action === 'create' ? 'Create' : 'Edit'; ?> Announcement</h2>
            </div>

            <div class="form-container">
                <form method="POST" class="announcement-form">
                    <div class="form-group">
                        <label for="title">Title</label>
                        <input type="text" id="title" name="title" required
                               value="<?php echo isset($announcement) ? htmlspecialchars($announcement['title']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="content">Content</label>
                        <textarea id="content" name="content" rows="10" required><?php 
                            echo isset($announcement) ? htmlspecialchars($announcement['content']) : ''; 
                        ?></textarea>
                    </div>

                    <div class="form-actions">
                        <a href="announcements.php" class="btn-secondary">Cancel</a>
                        <button type="submit" class="btn-primary">
                            <?php echo $action === 'create' ? 'Create' : 'Update'; ?> Announcement
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </main>
</body>
</html> 