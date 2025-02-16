<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

try {
    // Fetch all teachers with their user status
    $stmt = $conn->prepare("
        SELECT 
            t.*,
            u.status as account_status,
            u.username,
            (SELECT COUNT(DISTINCT c.id) 
             FROM classes c 
             WHERE c.teacher_id = t.id 
             AND c.status = 'active') as class_count
        FROM teachers t
        JOIN users u ON t.user_id = u.id
        ORDER BY t.last_name, t.first_name
    ");
    $stmt->execute();
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    error_log("Error in teachers page: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while loading the teachers data.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Teachers - STMA LMS</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/teachers.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h2>Manage Teachers</h2>
            <div class="header-actions">
                <button class="btn-secondary" onclick="viewArchivedTeachers()">
                    <i class="fas fa-archive"></i> View Archived
                </button>
                <button class="btn-primary" onclick="showAddTeacherModal()">
                    <i class="fas fa-plus"></i> Add New Teacher
                </button>
            </div>
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

        <!-- Teachers Table -->
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Employee ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Contact</th>
                        <th>Classes</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($teachers as $teacher): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($teacher['employee_id']); ?></td>
                            <td>
                                <?php echo htmlspecialchars($teacher['last_name'] . ', ' . $teacher['first_name']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                            <td><?php echo htmlspecialchars($teacher['contact_number']); ?></td>
                            <td>
                                <span class="class-count">
                                    <i class="fas fa-book"></i>
                                    <?php echo $teacher['class_count']; ?> Classes
                                </span>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $teacher['account_status']; ?>">
                                    <?php echo ucfirst($teacher['account_status']); ?>
                                </span>
                            </td>
                            <td class="actions">
                                <button onclick="viewTeacher(<?php echo $teacher['id']; ?>)" 
                                        class="btn-icon view" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button onclick="editTeacher(<?php echo $teacher['id']; ?>)" 
                                        class="btn-icon edit" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="archiveTeacher(<?php echo $teacher['id']; ?>)" 
                                        class="btn-icon archive" title="Archive Teacher">
                                    <i class="fas fa-archive"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Add Teacher Modal -->
        <div id="addTeacherModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-user-plus"></i> Add New Teacher</h3>
                    <button type="button" class="close-modal" onclick="closeModal('addTeacherModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="addTeacherForm" method="POST" action="add_teacher.php">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="employeeId">Employee ID</label>
                                <input type="text" name="employee_id" id="employeeId" class="form-control" readonly 
                                       placeholder="Will be generated automatically">
                            </div>

                            <div class="form-group">
                                <label for="firstName">First Name</label>
                                <input type="text" name="first_name" id="firstName" class="form-control" required>
                            </div>

                            <div class="form-group">
                                <label for="lastName">Last Name</label>
                                <input type="text" name="last_name" id="lastName" class="form-control" required>
                            </div>

                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" name="email" id="email" class="form-control" required
                                       placeholder="example@domain.com"
                                       oninput="validateEmail(this)">
                                <small class="validation-message" id="emailMessage"></small>
                            </div>

                            <div class="form-group">
                                <label for="contact">Contact Number</label>
                                <input type="text" name="contact_number" id="contact" class="form-control" required 
                                       placeholder="09XXXXXXXXX"
                                       maxlength="11"
                                       oninput="validateContact(this)">
                                <small class="validation-message" id="contactMessage"></small>
                            </div>

                            <div class="form-group">
                                <label for="username">Username</label>
                                <input type="text" name="username" id="username" class="form-control" required>
                            </div>

                            <div class="form-group">
                                <label for="password">Password</label>
                                <div class="password-input-group">
                                    <input type="password" name="password" id="password" class="form-control" required
                                           minlength="8"
                                           oninput="validatePassword(this)">
                                    <button type="button" class="toggle-password" onclick="togglePassword('password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <small class="validation-message" id="passwordMessage"></small>
                                <small class="password-requirements">
                                    Password must contain:
                                    <ul>
                                        <li id="lengthCheck">At least 8 characters</li>
                                        <li id="upperCheck">One uppercase letter</li>
                                        <li id="lowerCheck">One lowercase letter</li>
                                        <li id="numberCheck">One number</li>
                                        <li id="specialCheck">One special character</li>
                                    </ul>
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="confirmPassword">Confirm Password</label>
                                <div class="password-input-group">
                                    <input type="password" name="confirm_password" id="confirmPassword" class="form-control" required
                                           oninput="validatePasswordMatch()">
                                    <button type="button" class="toggle-password" onclick="togglePassword('confirmPassword')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <small class="validation-message" id="confirmPasswordMessage"></small>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn-secondary" onclick="closeModal('addTeacherModal')">Cancel</button>
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-plus"></i> Add Teacher
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit Teacher Modal -->
        <div id="editTeacherModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-user-edit"></i> Edit Teacher</h3>
                    <button type="button" class="close-modal" onclick="closeModal('editTeacherModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="editTeacherForm" method="POST" action="update_teacher.php">
                        <input type="hidden" name="teacher_id" id="editTeacherId">
                        
                        <div class="form-grid">
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
                                <input type="text" name="username" id="editUsername" class="form-control" required>
                            </div>

                            <div class="form-group">
                                <label for="editStatus">Status</label>
                                <select name="status" id="editStatus" class="form-control" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn-secondary" onclick="closeModal('editTeacherModal')">Cancel</button>
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Add Archive Teacher Modal -->
        <div id="archiveTeacherModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-archive"></i> Archive Teacher</h3>
                    <button type="button" class="close-modal" onclick="closeModal('archiveTeacherModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to archive this teacher? This will:</p>
                    <ul>
                        <li>Move the teacher to the archive</li>
                        <li>Deactivate their account</li>
                        <li>Remove them from active classes</li>
                    </ul>
                    <div class="form-actions">
                        <button type="button" class="btn-secondary" onclick="closeModal('archiveTeacherModal')">Cancel</button>
                        <button type="button" class="btn-danger" onclick="confirmArchive()">Archive Teacher</button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        function showAddTeacherModal() {
            document.getElementById('addTeacherModal').classList.add('active');
            document.getElementById('addTeacherForm').reset();
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function viewTeacher(teacherId) {
            if (!teacherId) {
                alert('Invalid teacher ID');
                return;
            }
            window.location.href = `teacher_details.php?id=${teacherId}`;
        }

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

        function toggleStatus(teacherId) {
            if (!confirm('Are you sure you want to change this teacher\'s status?')) {
                return;
            }

            fetch('toggle_teacher_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `teacher_id=${teacherId}`
            })
            .then(response => response.json())
            .then(data => {
                location.reload();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to toggle teacher status');
            });
        }

        let selectedTeacherId = null;

        function viewArchivedTeachers() {
            window.location.href = 'archived_teachers.php';
        }

        function archiveTeacher(teacherId) {
            selectedTeacherId = teacherId;
            document.getElementById('archiveTeacherModal').classList.add('active');
        }

        function confirmArchive() {
            if (!selectedTeacherId) return;

            fetch('archive_teacher.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `teacher_id=${selectedTeacherId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Teacher archived successfully');
                    location.reload();
                } else {
                    throw new Error(data.message || 'Failed to archive teacher');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to archive teacher');
            })
            .finally(() => {
                closeModal('archiveTeacherModal');
            });
        }

        function validateEmail(input) {
            const email = input.value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            const messageElement = document.getElementById('emailMessage');

            if (emailRegex.test(email)) {
                input.classList.remove('invalid');
                input.classList.add('valid');
                messageElement.textContent = '✓ Valid email format';
                messageElement.className = 'validation-message valid';
                return true;
            } else {
                input.classList.remove('valid');
                input.classList.add('invalid');
                messageElement.textContent = 'Please enter a valid email address';
                messageElement.className = 'validation-message invalid';
                return false;
            }
        }

        function validateContact(input) {
            // Remove any non-digit characters
            input.value = input.value.replace(/\D/g, '');
            
            const contact = input.value;
            const messageElement = document.getElementById('contactMessage');
            
            // Check if number starts with 09 and has 11 digits
            if (contact.length === 11 && contact.startsWith('09')) {
                input.classList.remove('invalid');
                input.classList.add('valid');
                messageElement.textContent = '✓ Valid number format';
                messageElement.className = 'validation-message valid';
                return true;
            } else {
                input.classList.remove('valid');
                input.classList.add('invalid');
                messageElement.textContent = contact.length === 11 && !contact.startsWith('09') ? 
                    'Number must start with 09' : 'Please enter 11 digits starting with 09';
                messageElement.className = 'validation-message invalid';
                return false;
            }
        }

        function validatePassword(input) {
            const password = input.value;
            const messageElement = document.getElementById('passwordMessage');
            
            // Password requirements
            const minLength = 8;
            const hasUpperCase = /[A-Z]/.test(password);
            const hasLowerCase = /[a-z]/.test(password);
            const hasNumber = /\d/.test(password);
            const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);

            // Update requirement checks
            document.getElementById('lengthCheck').classList.toggle('valid', password.length >= minLength);
            document.getElementById('upperCheck').classList.toggle('valid', hasUpperCase);
            document.getElementById('lowerCheck').classList.toggle('valid', hasLowerCase);
            document.getElementById('numberCheck').classList.toggle('valid', hasNumber);
            document.getElementById('specialCheck').classList.toggle('valid', hasSpecial);

            const isValid = password.length >= minLength && hasUpperCase && hasLowerCase && 
                           hasNumber && hasSpecial;

            if (isValid) {
                input.classList.remove('invalid');
                input.classList.add('valid');
                messageElement.textContent = '✓ Strong password';
                messageElement.className = 'validation-message valid';
                return true;
            } else {
                input.classList.remove('valid');
                input.classList.add('invalid');
                messageElement.textContent = 'Please meet all password requirements';
                messageElement.className = 'validation-message invalid';
                return false;
            }
        }

        function validatePasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword');
            const messageElement = document.getElementById('confirmPasswordMessage');

            if (confirmPassword.value === password) {
                confirmPassword.classList.remove('invalid');
                confirmPassword.classList.add('valid');
                messageElement.textContent = '✓ Passwords match';
                messageElement.className = 'validation-message valid';
                return true;
            } else {
                confirmPassword.classList.remove('valid');
                confirmPassword.classList.add('invalid');
                messageElement.textContent = 'Passwords do not match';
                messageElement.className = 'validation-message invalid';
                return false;
            }
        }

        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        document.getElementById('addTeacherForm').addEventListener('submit', function(e) {
            const contact = document.getElementById('contact');
            const email = document.getElementById('email');
            const password = document.getElementById('password');
            
            let isValid = true;

            // Validate all fields
            if (!validateContact(contact)) isValid = false;
            if (!validateEmail(email)) isValid = false;
            if (!validatePassword(password)) isValid = false;
            if (!validatePasswordMatch()) isValid = false;

            if (!isValid) {
                e.preventDefault();
            }
        });

        // Disable manual entry of Employee ID
        document.getElementById('employeeId').readOnly = true;
    </script>
</body>
</html> 