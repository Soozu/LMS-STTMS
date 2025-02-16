<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

$studentId = $_SESSION['role_id'];
$userId = $_SESSION['user_id'];

try {
    // Fetch student's information
    $stmt = $conn->prepare("
        SELECT 
            s.id,
            s.user_id,
            s.lrn,
            s.first_name,
            s.last_name,
            s.email,
            s.grade_level,
            s.gender,
            s.birth_date,
            s.contact_number,
            s.address,
            s.guardian_name,
            s.guardian_contact,
            s.section_id,
            u.username,
            u.status,
            u.last_login
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE s.id = ? AND u.status = 'active'
    ");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        throw new Exception('Student not found or inactive');
    }

    // Handle profile update
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $firstName = trim($_POST['first_name']);
        $lastName = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['contact_number'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $birthDate = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
        $address = trim($_POST['address'] ?? '');
        $guardianName = trim($_POST['guardian_name'] ?? '');
        $guardianContact = trim($_POST['guardian_contact'] ?? '');
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];

        // Start transaction
        $conn->beginTransaction();

        try {
            // Update basic info
            $stmt = $conn->prepare("
                UPDATE students 
                SET first_name = ?, 
                    last_name = ?, 
                    contact_number = ?,
                    email = ?,
                    gender = ?,
                    birth_date = ?,
                    address = ?,
                    guardian_name = ?,
                    guardian_contact = ?
                WHERE id = ?
            ");
            
            // Execute with proper null handling
            $stmt->execute([
                $firstName, 
                $lastName, 
                $phone ?: null,
                $email, 
                $gender ?: null,
                $birthDate,
                $address ?: null,
                $guardianName ?: null,
                $guardianContact ?: null,
                $studentId
            ]);

            // Update password if provided
            if (!empty($currentPassword) && !empty($newPassword)) {
                // Verify current password
                $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();

                if (!password_verify($currentPassword, $user['password'])) {
                    throw new Exception('Current password is incorrect');
                }

                if ($newPassword !== $confirmPassword) {
                    throw new Exception('New passwords do not match');
                }

                // Update password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashedPassword, $userId]);
            }

            $conn->commit();
            $success = "Profile updated successfully";

            // Refresh student data
            $stmt = $conn->prepare("
                SELECT s.*, u.username, u.status, u.last_login
                FROM students s
                JOIN users u ON s.user_id = u.id
                WHERE s.id = ?
            ");
            $stmt->execute([$studentId]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            $conn->rollBack();
            $error = $e->getMessage();
        }
    }

} catch (Exception $e) {
    error_log("Error in profile: " . $e->getMessage());
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - STMA LMS</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="js/sidebar.js" defer></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h2>My Profile</h2>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="profile-container">
            <div class="profile-header">
                <div class="profile-avatar">
                    <img src="../images/default-avatar.png" alt="Profile Picture">
                </div>
                <div class="profile-info">
                    <h3><?php echo htmlspecialchars($student['first_name'] ?? '') . ' ' . htmlspecialchars($student['last_name'] ?? ''); ?></h3>
                    <p class="user-role">Student</p>
                    <p class="last-login">
                        Last login: <?php echo !empty($student['last_login']) ? formatDateTime($student['last_login']) : 'Never'; ?>
                    </p>
                </div>
            </div>

            <form class="profile-form" method="POST" action="">
                <div class="form-section">
                    <h4>Personal Information</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" 
                                   value="<?php echo !empty($student['first_name']) ? htmlspecialchars($student['first_name']) : ''; ?>" 
                                   required>
                        </div>
                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" 
                                   value="<?php echo !empty($student['last_name']) ? htmlspecialchars($student['last_name']) : ''; ?>" 
                                   required>
                        </div>
                        <div class="form-group">
                            <label for="gender">Gender</label>
                            <select id="gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="male" <?php echo ($student['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo ($student['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="birth_date">Birth Date</label>
                            <input type="date" id="birth_date" name="birth_date" 
                                   value="<?php echo $student['birth_date'] ?? ''; ?>" 
                                   required>
                        </div>
                        <div class="form-group full-width">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" rows="3"><?php echo !empty($student['address']) ? htmlspecialchars($student['address']) : ''; ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo !empty($student['email']) ? htmlspecialchars($student['email']) : ''; ?>" 
                                   required>
                        </div>
                        <div class="form-group">
                            <label for="contact_number">Contact Number</label>
                            <input type="tel" id="contact_number" name="contact_number" 
                                   value="<?php echo !empty($student['contact_number']) ? htmlspecialchars($student['contact_number']) : ''; ?>">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h4>Guardian Information</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="guardian_name">Guardian Name</label>
                            <input type="text" id="guardian_name" name="guardian_name" 
                                   value="<?php echo !empty($student['guardian_name']) ? htmlspecialchars($student['guardian_name']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="guardian_contact">Guardian Contact Number</label>
                            <input type="tel" id="guardian_contact" name="guardian_contact" 
                                   value="<?php echo !empty($student['guardian_contact']) ? htmlspecialchars($student['guardian_contact']) : ''; ?>">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h4>Change Password</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password">
                        </div>
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password">
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password">
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-save">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script>
        // Password validation
        document.getElementById('profile-form').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const currentPassword = document.getElementById('current_password').value;

            if (newPassword || confirmPassword || currentPassword) {
                if (!currentPassword) {
                    e.preventDefault();
                    alert('Please enter your current password');
                    return;
                }
                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    alert('New passwords do not match');
                    return;
                }
            }
        });
    </script>
</body>
</html> 