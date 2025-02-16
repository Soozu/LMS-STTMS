<?php
// Add these lines at the very top of the file
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Strict');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);

// Add security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Initialize variables
$students = [];
$error = null;
$success = null;

// Handle student status toggle
if (isset($_POST['toggle_status'])) {
    try {
        $user_id = $_POST['user_id'];
        $new_status = $_POST['new_status'];
        
        $stmt = $conn->prepare("
            UPDATE users 
            SET status = ? 
            WHERE id = ? AND user_type = 'student'
        ");
        $stmt->execute([$new_status, $user_id]);
        
        $success = "Student status updated successfully";
    } catch(PDOException $e) {
        error_log("Error updating student status: " . $e->getMessage());
        $error = "Failed to update student status";
    }
}

try {
    // Fetch all active students with their information
    $stmt = $conn->prepare("
        SELECT 
            s.*,
            u.username,
            u.status,
            COALESCE(s.gender, '') as gender,
            COALESCE(s.birth_date, '') as birth_date,
            COALESCE(s.contact_number, '') as contact_number,
            COALESCE(s.address, '') as address,
            COALESCE(s.guardian_name, '') as guardian_name,
            COALESCE(s.guardian_contact, '') as guardian_contact,
            COALESCE(sec.section_name, 'Not set') as section_name,
            COALESCE(sec.grade_level, 'Not set') as grade_level
        FROM students s
        JOIN users u ON s.user_id = u.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        WHERE u.status = 'active'
        ORDER BY s.grade_level ASC, s.last_name ASC, s.first_name ASC
    ");
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add this for debugging
    error_log("First student data: " . print_r($students[0] ?? 'No students found', true));

} catch(PDOException $e) {
    error_log("Error in students page: " . $e->getMessage());
    $error = "An error occurred while loading the students data.";
}

// Add these lines after your session start and before any HTML output
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - STMA LMS</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/students.css">
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
            <h2><i class="fas fa-user-graduate"></i> Manage Students</h2>
            <div class="header-actions">
                <a href="archived_students.php" class="btn-secondary">
                    <i class="fas fa-archive"></i> Archived Students
                </a>
                <button class="btn-secondary" onclick="openModal('importModal')">
                    <i class="fas fa-file-import"></i> Import Students
                </button>
                <button class="btn-primary" onclick="openModal('addStudentModal')">
                    <i class="fas fa-plus"></i> Add New Student
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

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php 
                    echo $_SESSION['success'];
                    unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <!-- Student Filters -->
        <div class="filter-section">
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Search students...">
                <i class="fas fa-search"></i>
            </div>
            <div class="filter-group">
                <select id="gradeFilter" onchange="filterStudents()">
                    <option value="">All Grades</option>
                    <?php for($i = 1; $i <= 6; $i++): ?>
                        <option value="<?php echo $i; ?>">Grade <?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
                <select id="sectionFilter" onchange="filterStudents()">
                    <option value="">All Sections</option>
                    <?php
                    try {
                        $sectionStmt = $conn->prepare("
                            SELECT DISTINCT s.id, s.section_name, s.grade_level 
                            FROM sections s 
                            WHERE s.status = 'active'
                            ORDER BY s.grade_level ASC, s.section_name ASC
                        ");
                        $sectionStmt->execute();
                        while ($section = $sectionStmt->fetch(PDO::FETCH_ASSOC)) {
                            echo sprintf(
                                '<option value="%s">Grade %d - %s</option>',
                                $section['id'],
                                $section['grade_level'],
                                htmlspecialchars($section['section_name'])
                            );
                        }
                    } catch (PDOException $e) {
                        error_log("Error fetching sections: " . $e->getMessage());
                    }
                    ?>
                </select>
                <select id="statusFilter" onchange="filterStudents()">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                </select>
            </div>
        </div>

        <!-- Students Table -->
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>LRN</th>
                        <th>NAME</th>
                        <th>GRADE & SECTION</th>
                        <th>CONTACT INFO</th>
                        <th>STATUS</th>
                        <th style="width: 120px; text-align: center;">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                        <tr data-section-id="<?php echo $student['section_id']; ?>">
                            <td>
                                <div class="lrn"><?php echo htmlspecialchars($student['lrn']); ?></div>
                            </td>
                            <td>
                                <div class="student-info">
                                    <span class="student-name">
                                        <?php echo htmlspecialchars($student['last_name'] ?? '') . ', ' . htmlspecialchars($student['first_name'] ?? ''); ?>
                                    </span>
                                    <span class="student-details">
                                        <?php 
                                            $gender = $student['gender'] ?? '';
                                            $birthDate = !empty($student['birth_date']) ? date('M d, Y', strtotime($student['birth_date'])) : 'Not set';
                                            echo htmlspecialchars($gender) . ($gender ? ' | ' : '') . $birthDate;
                                        ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <div>Grade <?php echo htmlspecialchars($student['grade_level'] ?? 'Not set'); ?></div>
                                <small><?php echo htmlspecialchars($student['section_name'] ?? 'Not set'); ?></small>
                            </td>
                            <td>
                                <div class="contact-info">
                                    <div><?php echo htmlspecialchars($student['contact_number'] ?? 'No contact number'); ?></div>
                                    <div class="guardian">
                                        Guardian: <?php echo htmlspecialchars($student['guardian_name'] ?? 'Not set'); ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $student['status']; ?>">
                                    <?php echo ucfirst($student['status']); ?>
                                </span>
                            </td>
                            <td class="actions">
                                <div class="action-buttons">
                                    <button onclick="viewStudent(<?php echo $student['id']; ?>)" 
                                            class="btn-icon view" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button onclick="editStudent(<?php echo $student['id']; ?>)" 
                                            class="btn-icon edit" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="archiveStudent(<?php echo $student['id']; ?>)" 
                                            class="btn-icon archive" title="Archive Student">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="no-results" style="display: none; text-align: center; padding: 2rem;">
            <i class="fas fa-search" style="font-size: 2rem; color: #ccc; margin-bottom: 1rem;"></i>
            <p>No students found matching your filters</p>
        </div>
    </main>

    <!-- Add/Edit Student Modal -->
    <div id="studentModal" class="modal">
        <!-- Modal content will be loaded dynamically -->
    </div>

    <!-- Add Student Modal -->
    <div id="addStudentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Add New Student</h3>
                <button type="button" class="close-modal" onclick="closeModal('addStudentModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="addStudentForm" method="POST" action="add_student.php" autocomplete="off">
                    <?php if(isset($_SESSION['csrf_token'])): ?>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <?php endif; ?>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="lrn">LRN (Learner Reference Number)</label>
                            <input type="text" 
                                   name="lrn" 
                                   id="lrn" 
                                   class="form-control" 
                                   pattern="[0-9]{12}" 
                                   maxlength="12" 
                                   title="LRN must be exactly 12 digits" 
                                   required 
                                   oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 12);"
                                   onkeypress="return event.charCode >= 48 && event.charCode <= 57">
                            <small class="form-text">Enter exactly 12 digits</small>
                        </div>

                        <div class="form-group">
                            <label for="firstName">First Name</label>
                            <input type="text" name="first_name" id="firstName" class="form-control" required autocomplete="off">
                        </div>

                        <div class="form-group">
                            <label for="lastName">Last Name</label>
                            <input type="text" name="last_name" id="lastName" class="form-control" required autocomplete="off">
                        </div>

                        <div class="form-group">
                            <label for="gender">Gender</label>
                            <select name="gender" id="gender" class="form-control" required>
                                <option value="">Select Gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="birth_date">Birth Date</label>
                            <input type="date" name="birth_date" id="birth_date" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="section">Section</label>
                            <select name="section_id" id="section" class="form-control" required>
                                <option value="">Select Section</option>
                                <?php
                                try {
                                    $stmt = $conn->prepare("
                                        SELECT id, grade_level, section_name 
                                        FROM sections 
                                        WHERE status = 'active' 
                                        ORDER BY grade_level ASC, section_name ASC
                                    ");
                                    $stmt->execute();
                                    while ($section = $stmt->fetch()) {
                                        echo sprintf(
                                            '<option value="%d" data-grade="%d">%s</option>',
                                            $section['id'],
                                            $section['grade_level'],
                                            htmlspecialchars($section['section_name'])
                                        );
                                    }
                                } catch (PDOException $e) {
                                    error_log("Error fetching sections: " . $e->getMessage());
                                }
                                ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="grade_level">Grade Level</label>
                            <select name="grade_level" id="grade_level" class="form-control" required>
                                <option value="">Select Grade Level</option>
                                <?php for($i = 1; $i <= 6; $i++): ?>
                                    <option value="<?php echo $i; ?>">Grade <?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="contact_number">Contact Number</label>
                            <input type="text" name="contact_number" id="contact_number" class="form-control" 
                                   pattern="^09\d{9}$" title="Please enter a valid phone number (e.g., 09123456789)" 
                                   placeholder="09123456789">
                        </div>

                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea name="address" id="address" class="form-control" rows="3"></textarea>
                        </div>

                        <div class="form-group">
                            <label for="guardian_name">Guardian Name</label>
                            <input type="text" name="guardian_name" id="guardian_name" class="form-control">
                        </div>

                        <div class="form-group">
                            <label for="guardian_contact">Guardian Contact Number</label>
                            <input type="text" name="guardian_contact" id="guardian_contact" class="form-control" 
                                   pattern="^09\d{9}$" title="Please enter a valid phone number (e.g., 09123456789)" 
                                   placeholder="09123456789">
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" 
                                   name="email" 
                                   id="email" 
                                   class="form-control" 
                                   pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$"
                                   placeholder="student@example.com"
                                   title="Please enter a valid email address"
                                   required>
                            <small class="form-text">Enter a valid email address</small>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-secondary" onclick="closeModal('addStudentModal')">Cancel</button>
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-plus"></i> Add Student
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Import Students Modal -->
    <div id="importModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-file-import"></i> Import Students</h3>
                <button type="button" class="close-modal" onclick="closeModal('importModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form action="import_students.php" method="POST" enctype="multipart/form-data">
                    <?php if(isset($_SESSION['csrf_token'])): ?>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <?php endif; ?>

                    <!-- Import Instructions -->
                    <div class="import-instructions">
                        <h4><i class="fas fa-info-circle"></i> Instructions</h4>
                        <ul>
                            <li>Download and use the template file below</li>
                            <li>Required fields:</li>
                            <ul>
                                <li>LRN (12 digits)</li>
                                <li>First Name</li>
                                <li>Last Name</li>
                                <li>Grade Level (1-6)</li>
                                <li>Section ID</li>
                                <li>Email</li>
                            </ul>
                            <li>Students can complete their profile after first login</li>
                            <li>Maximum file size: 5MB</li>
                        </ul>
                    </div>

                    <!-- Template Download -->
                    <div class="template-download">
                        <a href="templates/Student_Import_Template.csv" download class="btn-secondary">
                            <i class="fas fa-download"></i> Download Template
                        </a>
                    </div>

                    <div class="file-upload-area" id="fileUploadArea">
                        <i class="fas fa-cloud-upload-alt file-upload-icon"></i>
                        <p>Choose CSV file or drag it here</p>
                        <p class="text-muted">Maximum file size: 5MB</p>
                        <input type="file" 
                               id="csvFile" 
                               name="csvFile" 
                               accept=".csv"
                               style="display: none" 
                               required>
                    </div>

                    <div class="selected-file" id="selectedFile">
                        <i class="fas fa-file-csv"></i>
                        <span class="file-name"></span>
                        <i class="fas fa-times remove-file" onclick="removeFile()"></i>
                    </div>

                    <div class="modal-footer">
                        <div class="button-group">
                            <button type="button" class="btn-secondary" onclick="closeModal('importModal')">Cancel</button>
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-file-import"></i> Import Students
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Archive Student Modal -->
    <div id="archiveStudentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-archive"></i> Archive Student</h3>
                <button type="button" class="close-modal" onclick="closeModal('archiveStudentModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to archive this student? This will:</p>
                <ul>
                    <li>Move the student to the archive</li>
                    <li>Deactivate their account</li>
                    <li>Remove them from active classes</li>
                </ul>
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('archiveStudentModal')">Cancel</button>
                    <button type="button" class="btn-danger" onclick="confirmArchive()">Archive Student</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchValue = this.value.toLowerCase();
            const rows = document.querySelectorAll('.data-table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchValue) ? '' : 'none';
            });
        });

        // Filter functionality
        function filterStudents() {
            const searchText = document.getElementById('searchInput').value.toLowerCase();
            const gradeFilter = document.getElementById('gradeFilter').value;
            const sectionFilter = document.getElementById('sectionFilter').value;
            const statusFilter = document.getElementById('statusFilter').value;

            const rows = document.querySelectorAll('.data-table tbody tr');

            rows.forEach(row => {
                const lrn = row.querySelector('.lrn').textContent.toLowerCase();
                const name = row.querySelector('.student-name').textContent.toLowerCase();
                const gradeSection = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
                // Status is always 'active' now, so we don't need to check it
                
                // Check if row matches all filters
                const matchesSearch = lrn.includes(searchText) || 
                                    name.includes(searchText) || 
                                    gradeSection.includes(searchText);
                
                const matchesGrade = !gradeFilter || gradeSection.includes(`grade ${gradeFilter}`);
                const matchesSection = !sectionFilter || row.dataset.sectionId === sectionFilter;
                // Status filter is always true since we only show active students
                const matchesStatus = true;

                // Show/hide row based on all filters
                row.style.display = (matchesSearch && matchesGrade && matchesSection && matchesStatus) ? '' : 'none';
            });

            // Update empty state message
            const visibleRows = document.querySelectorAll('.data-table tbody tr:not([style*="display: none"])');
            const emptyMessage = document.querySelector('.no-results');
            
            if (emptyMessage) {
                emptyMessage.style.display = visibleRows.length === 0 ? 'block' : 'none';
            }
        }

        // Add event listener for search input
        document.getElementById('searchInput').addEventListener('input', filterStudents);

        // Modal functions
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('active');
            }
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('active');
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }

        function editStudent(studentId) {
            fetch('get_student.php?id=' + studentId)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert(data.error);
                        return;
                    }
                    
                    const modal = document.getElementById('studentModal');
                    modal.classList.add('active');
                    
                    modal.innerHTML = `
                        <div class="modal-content">
                            <div class="modal-header">
                                <h3>Edit Student</h3>
                                <button type="button" class="close-modal" onclick="closeModal('studentModal')">&times;</button>
                            </div>
                            <div class="modal-body">
                                <form id="editStudentForm" method="POST" action="update_student.php">
                                    <input type="hidden" name="student_id" value="${data.id}">
                                    
                                    <div class="form-group">
                                        <label for="editLrn">LRN</label>
                                        <input type="text" name="lrn" id="editLrn" class="form-control" value="${data.lrn}" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="editFirstName">First Name</label>
                                        <input type="text" name="first_name" id="editFirstName" class="form-control" value="${data.first_name}" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="editLastName">Last Name</label>
                                        <input type="text" name="last_name" id="editLastName" class="form-control" value="${data.last_name}" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="editGender">Sex</label>
                                        <select name="gender" id="editGender" class="form-control" required>
                                            <option value="male" ${data.gender === 'male' ? 'selected' : ''}>Male</option>
                                            <option value="female" ${data.gender === 'female' ? 'selected' : ''}>Female</option>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label for="editBirthdate">Birthdate</label>
                                        <input type="date" name="birthdate" id="editBirthdate" class="form-control" value="${data.birth_date}" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="editAddress">Address</label>
                                        <textarea name="address" id="editAddress" class="form-control" rows="2" required>${data.address}</textarea>
                                    </div>

                                    <div class="form-group">
                                        <label for="editContactNumber">Contact Number</label>
                                        <input type="text" name="contact_number" id="editContactNumber" class="form-control" value="${data.contact_number}">
                                    </div>

                                    <div class="form-group">
                                        <label for="editGuardianName">Guardian Name</label>
                                        <input type="text" name="guardian_name" id="editGuardianName" class="form-control" value="${data.guardian_name}" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="editGuardianContact">Guardian Contact</label>
                                        <input type="text" name="guardian_contact" id="editGuardianContact" class="form-control" value="${data.guardian_contact}" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="editSection">Section</label>
                                        <select name="section_id" id="editSection" class="form-control" required>
                                            ${data.sections.map(section => `
                                                <option value="${section.id}" ${section.id === data.section_id ? 'selected' : ''}>
                                                    Grade ${section.grade_level} - ${section.section_name}
                                                </option>
                                            `).join('')}
                                        </select>
                                    </div>

                                    <div class="form-actions">
                                        <button type="button" class="btn-secondary" onclick="closeModal('studentModal')">Cancel</button>
                                        <button type="submit" class="btn-primary">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    `;
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load student data');
                });
        }

        function viewStudent(studentId) {
            console.log("Viewing student with ID:", studentId);
            if (studentId > 0) {
                window.location.href = 'student_details.php?id=' + studentId;
            } else {
                console.error('Invalid student ID:', studentId);
                alert('Error: Invalid student ID');
            }
        }

        function confirmNavigation(url) {
            console.log("Navigating to:", url);
            return true;
        }

        function showImportModal() {
            document.getElementById('importModal').classList.add('active');
        }

        // Add this to your existing JavaScript
        document.getElementById('lrn').addEventListener('input', function(e) {
            const lrn = e.target.value;
            const isValid = /^\d{12}$/.test(lrn);
            
            // Visual feedback
            if (lrn.length === 12) {
                if (isValid) {
                    this.classList.add('is-valid');
                    this.classList.remove('is-invalid');
                } else {
                    this.classList.add('is-invalid');
                    this.classList.remove('is-valid');
                }
            } else {
                this.classList.remove('is-valid', 'is-invalid');
            }
        });

        // Form submission validation
        document.getElementById('addStudentForm').addEventListener('submit', function(e) {
            const lrn = document.getElementById('lrn').value;
            if (!/^\d{12}$/.test(lrn)) {
                e.preventDefault();
                alert('LRN must be exactly 12 digits');
                return false;
            }
        });

        // Add to your existing JavaScript
        function validatePhoneNumber(input) {
            const phoneNumber = input.value.replace(/[^0-9]/g, '');
            const isValid = /^09\d{9}$/.test(phoneNumber);
            
            if (phoneNumber.length > 0) {
                if (isValid) {
                    input.classList.add('is-valid');
                    input.classList.remove('is-invalid');
                    return true;
                } else {
                    input.classList.add('is-invalid');
                    input.classList.remove('is-valid');
                    return false;
                }
            } else {
                input.classList.remove('is-valid', 'is-invalid');
                return true; // Empty is allowed
            }
        }

        // Add event listeners for phone number inputs
        document.querySelectorAll('input[type="tel"]').forEach(input => {
            input.addEventListener('input', function() {
                validatePhoneNumber(this);
            });
        });

        // Add form validation
        document.getElementById('addStudentForm').addEventListener('submit', function(e) {
            const contactNumber = document.getElementById('contactNumber').value;
            const guardianContact = document.getElementById('guardianContact').value;
            
            if (contactNumber && !validatePhoneNumber(document.getElementById('contactNumber'))) {
                e.preventDefault();
                alert('Please enter a valid contact number (11 digits starting with 09)');
                return false;
            }
            
            if (guardianContact && !validatePhoneNumber(document.getElementById('guardianContact'))) {
                e.preventDefault();
                alert('Please enter a valid guardian contact number (11 digits starting with 09)');
                return false;
            }
        });

        // Add email validation
        function validateEmail(email) {
            const re = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            return re.test(String(email).toLowerCase());
        }

        document.getElementById('email').addEventListener('input', function(e) {
            const email = e.target.value;
            if (email.length > 0) {
                if (validateEmail(email)) {
                    this.classList.add('is-valid');
                    this.classList.remove('is-invalid');
                } else {
                    this.classList.add('is-invalid');
                    this.classList.remove('is-valid');
                }
            } else {
                this.classList.remove('is-valid', 'is-invalid');
            }
        });

        // Add email validation to form submission
        document.getElementById('addStudentForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            if (email && !validateEmail(email)) {
                e.preventDefault();
                alert('Please enter a valid email address');
                return false;
            }
        });

        // Add this to your existing JavaScript
        const fileUploadArea = document.getElementById('fileUploadArea');
        const fileInput = document.getElementById('csvFile');
        const selectedFile = document.getElementById('selectedFile');
        const fileNameDisplay = selectedFile.querySelector('.file-name');

        fileUploadArea.addEventListener('click', () => {
            fileInput.click();
        });

        fileUploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            fileUploadArea.style.borderColor = '#28a745';
        });

        fileUploadArea.addEventListener('dragleave', (e) => {
            e.preventDefault();
            fileUploadArea.style.borderColor = '#8b0000';
        });

        fileUploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            fileUploadArea.style.borderColor = '#8b0000';
            const files = e.dataTransfer.files;
            if (files.length) {
                handleFile(files[0]);
            }
        });

        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length) {
                handleFile(e.target.files[0]);
            }
        });

        function handleFile(file) {
            if (file.size > 5 * 1024 * 1024) { // 5MB
                alert('File size exceeds 5MB limit');
                fileInput.value = '';
                return;
            }
            
            if (!file.name.toLowerCase().endsWith('.csv')) {
                alert('Please select a CSV file');
                fileInput.value = '';
                return;
            }

            fileNameDisplay.textContent = file.name;
            selectedFile.classList.add('active');
            fileUploadArea.style.borderColor = '#28a745';
        }

        function removeFile() {
            fileInput.value = '';
            selectedFile.classList.remove('active');
            fileUploadArea.style.borderColor = '#8b0000';
        }

        let selectedStudentId = null;

        function archiveStudent(studentId) {
            selectedStudentId = studentId;
            document.getElementById('archiveStudentModal').classList.add('active');
        }

        function confirmArchive() {
            if (!selectedStudentId) return;

            fetch('archive_student.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `student_id=${selectedStudentId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Student archived successfully');
                    location.reload();
                } else {
                    throw new Error(data.message || 'Failed to archive student');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to archive student');
            })
            .finally(() => {
                closeModal('archiveStudentModal');
            });
        }

        // Add this function to filter sections based on grade level
        function filterSections() {
            const gradeLevelSelect = document.getElementById('grade_level');
            const sectionSelect = document.getElementById('section');
            const selectedGrade = gradeLevelSelect.value;
            
            // Reset section selection
            sectionSelect.value = '';
            
            // Show/hide sections based on grade level
            Array.from(sectionSelect.options).forEach(option => {
                if (option.value === '') return; // Skip the placeholder option
                
                const sectionGrade = option.getAttribute('data-grade');
                if (selectedGrade === '' || sectionGrade === selectedGrade) {
                    option.style.display = '';
                } else {
                    option.style.display = 'none';
                }
            });
        }

        // Update the existing updateGradeLevel function
        function updateGradeLevel() {
            const sectionSelect = document.getElementById('section');
            const gradeLevelSelect = document.getElementById('grade_level');
            
            if (sectionSelect.selectedIndex > 0) {
                const selectedOption = sectionSelect.options[sectionSelect.selectedIndex];
                const gradeLevel = selectedOption.getAttribute('data-grade');
                gradeLevelSelect.value = gradeLevel;
            }
        }

        // Add event listener for grade level changes
        document.getElementById('grade_level').addEventListener('change', filterSections);
    </script>
</body>
</html> 