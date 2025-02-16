<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$teacher_id = $_GET['id'] ?? null;
if (!$teacher_id) {
    header('Location: teachers.php');
    exit();
}

try {
    // Validate teacher_id
    if (!$teacher_id || !is_numeric($teacher_id)) {
        throw new Exception('Invalid teacher ID');
    }

    // Fetch teacher details with user status
    $stmt = $conn->prepare("
        SELECT 
            t.*,
            u.username,
            u.status as account_status,
            u.last_login
        FROM teachers t
        JOIN users u ON t.user_id = u.id
        WHERE t.id = ?
    ");
    $stmt->execute([$teacher_id]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$teacher) {
        throw new Exception('Teacher not found');
    }

    // Fetch assigned classes
    $stmt = $conn->prepare("
        SELECT 
            c.*,
            s.subject_name,
            sec.section_name,
            (SELECT COUNT(*) 
             FROM class_enrollments ce 
             WHERE ce.class_id = c.id 
             AND ce.status = 'active') as student_count
        FROM classes c
        JOIN subjects s ON c.subject_id = s.id
        JOIN sections sec ON c.section_id = sec.id
        WHERE c.teacher_id = ? AND c.status = 'active'
        ORDER BY s.grade_level ASC, s.subject_name ASC
    ");
    $stmt->execute([$teacher_id]);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(Exception $e) {
    error_log("Error in teacher details: " . $e->getMessage());
    $_SESSION['error'] = "Failed to load teacher details: " . $e->getMessage();
    header('Location: teachers.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Details - STMA LMS</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/teacher_details.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <div class="header-content">
                <button onclick="window.location.href='teachers.php'" class="btn-back">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <h2>Teacher Details</h2>
            </div>
            <button class="btn-primary" onclick="editTeacher(<?php echo htmlspecialchars($teacher['id']); ?>)">
                <i class="fas fa-edit"></i> Edit Teacher
            </button>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?php 
                    echo $_SESSION['error'];
                    unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php 
                    echo $_SESSION['success'];
                    unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <!-- Teacher Information -->
        <div class="info-grid">
            <div class="info-card">
                <h3>Personal Information</h3>
                <div class="info-content">
                    <div class="info-item">
                        <label>Employee ID:</label>
                        <span><?php echo htmlspecialchars($teacher['employee_id']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Name:</label>
                        <span><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Email:</label>
                        <span><?php echo htmlspecialchars($teacher['email']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Contact Number:</label>
                        <span><?php echo htmlspecialchars($teacher['contact_number']); ?></span>
                    </div>
                </div>
            </div>

            <div class="info-card">
                <h3>Account Information</h3>
                <div class="info-content">
                    <div class="info-item">
                        <label>Username:</label>
                        <span><?php echo htmlspecialchars($teacher['username']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Status:</label>
                        <span class="status-badge <?php echo $teacher['account_status']; ?>">
                            <?php echo ucfirst($teacher['account_status']); ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <label>Last Login:</label>
                        <span>
                            <?php echo $teacher['last_login'] ? date('M d, Y h:i A', strtotime($teacher['last_login'])) : 'Never'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assigned Classes -->
        <div class="classes-section">
            <h3>Assigned Classes</h3>
            <?php if (!empty($classes)): ?>
                <div class="classes-grid">
                    <?php foreach ($classes as $class): ?>
                        <div class="class-card">
                            <div class="class-header">
                                <h4><?php echo htmlspecialchars($class['subject_name']); ?></h4>
                                <span class="grade-section">
                                    Grade <?php echo htmlspecialchars($class['grade_level']); ?> - 
                                    <?php echo htmlspecialchars($class['section_name']); ?>
                                </span>
                            </div>
                            <div class="class-info">
                                <div class="students-count">
                                    <i class="fas fa-users"></i>
                                    <span><?php echo $class['student_count']; ?> Students</span>
                                </div>
                                <button onclick="viewClass(<?php echo $class['id']; ?>)" 
                                        class="btn-view">View Class</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <p>No classes assigned to this teacher.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Edit Teacher Modal -->
    <div id="editTeacherModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Teacher</h3>
                <button type="button" class="close-modal" onclick="closeModal('editTeacherModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editTeacherForm" method="POST" action="update_teacher.php">
                    <input type="hidden" name="teacher_id" id="editTeacherId">
                    
                    <div class="form-group">
                        <label for="editEmployeeId">Employee ID</label>
                        <input type="text" name="employee_id" id="editEmployeeId" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="editFirstName">First Name</label>
                        <input type="text" name="first_name" id="editFirstName" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="editLastName">Last Name</label>
                        <input type="text" name="last_name" id="editLastName" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="editEmail">Email</label>
                        <input type="email" name="email" id="editEmail" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="editContact">Contact Number</label>
                        <input type="text" name="contact_number" id="editContact" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="editUsername">Username</label>
                        <input type="text" name="username" id="editUsername" class="form-control" required readonly>
                    </div>

                    <div class="form-group">
                        <label for="editStatus">Status</label>
                        <select name="status" id="editStatus" class="form-control" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-secondary" onclick="closeModal('editTeacherModal')">Cancel</button>
                        <button type="submit" class="btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function editTeacher(teacherId) {
            // Fetch teacher details
            fetch(`get_teacher.php?id=${teacherId}`)
                .then(response => response.json())
                .then(teacher => {
                    // Populate the edit form
                    document.getElementById('editTeacherId').value = teacher.id;
                    document.getElementById('editEmployeeId').value = teacher.employee_id;
                    document.getElementById('editFirstName').value = teacher.first_name;
                    document.getElementById('editLastName').value = teacher.last_name;
                    document.getElementById('editEmail').value = teacher.email;
                    document.getElementById('editContact').value = teacher.contact_number;
                    document.getElementById('editUsername').value = teacher.username;
                    document.getElementById('editStatus').value = teacher.account_status;
                    
                    // Show the modal
                    document.getElementById('editTeacherModal').classList.add('active');
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load teacher details');
                });
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function viewClass(classId) {
            window.location.href = `class_details.php?id=${classId}`;
        }
    </script>
</body>
</html> 