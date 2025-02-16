<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Initialize variables
$classes = [];
$error = null;
$success = null;

try {
    // Fetch all classes with related data
    $stmt = $conn->prepare("
        SELECT 
            c.id,
            c.status,
            COALESCE(s.subject_name, 'No Subject') as subject_name,
            COALESCE(s.grade_level, 0) as grade_level,
            COALESCE(CONCAT(t.first_name, ' ', t.last_name), 'Unassigned') as teacher_name,
            COALESCE(sec.section_name, 'No Section') as section_name,
            (SELECT COUNT(*) 
             FROM class_enrollments ce 
             WHERE ce.class_id = c.id 
             AND ce.status = 'active') as student_count
        FROM classes c
        LEFT JOIN subjects s ON c.subject_id = s.id
        LEFT JOIN teachers t ON c.teacher_id = t.id
        LEFT JOIN sections sec ON c.section_id = sec.id
        WHERE c.status = 'active'
        ORDER BY s.grade_level ASC, s.subject_name ASC
    ");
    $stmt->execute();
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    error_log("Error in classes page: " . $e->getMessage());
    $error = "An error occurred while loading the classes data.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Classes - STMA LMS</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/classes.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header-wrapper">
            <div class="page-header">
                <div class="page-title">
                    <i class="fas fa-chalkboard"></i>
                    <h2>Manage Classes</h2>
                </div>
                <button class="btn-primary" onclick="showAddClassModal()">
                    <i class="fas fa-plus"></i>
                    <span>Add New Class</span>
                </button>
            </div>

            <!-- Statistics Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-chalkboard"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo count($classes); ?></h3>
                        <p>Total Classes</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo array_sum(array_column($classes, 'student_count')); ?></h3>
                        <p>Total Students</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo count(array_unique(array_column($classes, 'teacher_name'))); ?></h3>
                        <p>Active Teachers</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo count(array_unique(array_column($classes, 'subject_name'))); ?></h3>
                        <p>Total Subjects</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-container">
            <div class="table-header">
                <h3 class="table-title">
                    <i class="fas fa-list"></i>
                    Class List
                </h3>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Grade Level</th>
                        <th>Section</th>
                        <th>Subject</th>
                        <th>Teacher</th>
                        <th>Students</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // Sort classes by grade level and section
                    usort($classes, function($a, $b) {
                        if ($a['grade_level'] != $b['grade_level']) {
                            return $a['grade_level'] - $b['grade_level'];
                        }
                        return strcmp($a['section_name'], $b['section_name']);
                    });
                    
                    foreach ($classes as $class): 
                    ?>
                        <tr>
                            <td>
                                <div class="grade-info">
                                    <span class="grade-number">Grade <?php echo htmlspecialchars($class['grade_level']); ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="section-info">
                                    <span class="section-name"><?php echo htmlspecialchars($class['section_name']); ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="subject-info">
                                    <span class="subject-name"><?php echo htmlspecialchars($class['subject_name']); ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="teacher-info">
                                    <i class="fas fa-user-tie"></i>
                                    <span><?php echo htmlspecialchars($class['teacher_name']); ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="student-count">
                                    <i class="fas fa-users"></i>
                                    <span><?php echo $class['student_count']; ?> Students</span>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $class['status']; ?>">
                                    <?php echo ucfirst($class['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-icon view" onclick="viewClass(<?php echo $class['id']; ?>)" title="View">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn-icon edit" onclick="editClass(<?php echo $class['id']; ?>)" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-icon toggle" onclick="toggleStatus(<?php echo $class['id']; ?>)" title="Toggle Status">
                                        <i class="fas fa-power-off"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Add this modal before closing main tag -->
    <!-- Add Class Modal -->
    <div id="addClassModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Class</h3>
                <button type="button" class="close-modal" onclick="closeModal('addClassModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="addClassForm" method="POST" action="add_class.php">
                    <div class="form-group">
                        <label for="gradeLevel">Grade Level</label>
                        <select name="grade_level" id="gradeLevel" class="form-control" required onchange="loadSections(this.value)">
                            <option value="">Select Grade Level</option>
                            <?php for($i = 1; $i <= 6; $i++): ?>
                                <option value="<?php echo $i; ?>">Grade <?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="section">Section</label>
                        <select name="section_id" id="section" class="form-control" required>
                            <option value="">Select Section</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="subject">Subject</label>
                        <select name="subject_id" id="subject" class="form-control" required>
                            <option value="">Select Subject</option>
                            <?php
                            // Fetch active subjects
                            $stmt = $conn->prepare("SELECT id, subject_name, grade_level FROM subjects WHERE status = 'active' ORDER BY grade_level, subject_name");
                            $stmt->execute();
                            $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($subjects as $subject):
                            ?>
                                <option value="<?php echo $subject['id']; ?>" data-grade="<?php echo $subject['grade_level']; ?>">
                                    <?php echo htmlspecialchars($subject['subject_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="teacher">Teacher</label>
                        <select name="teacher_id" id="teacher" class="form-control" required>
                            <option value="">Select Teacher</option>
                            <?php
                            // Fetch active teachers
                            $stmt = $conn->prepare("SELECT t.id, t.first_name, t.last_name FROM teachers t JOIN users u ON t.user_id = u.id WHERE u.status = 'active' ORDER BY t.last_name, t.first_name");
                            $stmt->execute();
                            $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($teachers as $teacher):
                            ?>
                                <option value="<?php echo $teacher['id']; ?>">
                                    <?php echo htmlspecialchars($teacher['last_name'] . ', ' . $teacher['first_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-secondary" onclick="closeModal('addClassModal')">Cancel</button>
                        <button type="submit" class="btn-primary">Add Class</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Class Modal -->
    <div id="editClassModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Class</h3>
                <button type="button" class="close-modal" onclick="closeModal('editClassModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editClassForm" method="POST" action="update_class.php">
                    <input type="hidden" name="class_id" id="editClassId">
                    
                    <div class="form-group">
                        <label for="editGradeLevel">Grade Level</label>
                        <select name="grade_level" id="editGradeLevel" class="form-control" required onchange="loadSectionsForEdit(this.value)">
                            <option value="">Select Grade Level</option>
                            <?php for($i = 1; $i <= 6; $i++): ?>
                                <option value="<?php echo $i; ?>">Grade <?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="editSection">Section</label>
                        <select name="section_id" id="editSection" class="form-control" required>
                            <option value="">Select Section</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="editSubject">Subject</label>
                        <select name="subject_id" id="editSubject" class="form-control" required>
                            <option value="">Select Subject</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?php echo $subject['id']; ?>" data-grade="<?php echo $subject['grade_level']; ?>">
                                    <?php echo htmlspecialchars($subject['subject_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="editTeacher">Teacher</label>
                        <select name="teacher_id" id="editTeacher" class="form-control" required>
                            <option value="">Select Teacher</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>">
                                    <?php echo htmlspecialchars($teacher['last_name'] . ', ' . $teacher['first_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="editStatus">Status</label>
                        <select name="status" id="editStatus" class="form-control" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-secondary" onclick="closeModal('editClassModal')">Cancel</button>
                        <button type="submit" class="btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showAddClassModal() {
            document.getElementById('addClassModal').classList.add('active');
            document.getElementById('addClassForm').reset();
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function loadSections(gradeLevel) {
            if (!gradeLevel) {
                document.getElementById('section').innerHTML = '<option value="">Select Section</option>';
                return;
            }

            fetch(`get_sections_by_grade.php?grade_level=${gradeLevel}`)
                .then(response => response.json())
                .then(sections => {
                    const sectionSelect = document.getElementById('section');
                    sectionSelect.innerHTML = '<option value="">Select Section</option>';
                    
                    sections.forEach(section => {
                        const option = document.createElement('option');
                        option.value = section.id;
                        option.textContent = section.section_name;
                        sectionSelect.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load sections');
                });

            // Filter subjects based on grade level
            const subjectSelect = document.getElementById('subject');
            const options = subjectSelect.querySelectorAll('option');
            options.forEach(option => {
                if (option.value === '') return; // Skip placeholder option
                if (option.dataset.grade === gradeLevel) {
                    option.style.display = '';
                } else {
                    option.style.display = 'none';
                }
            });
        }

        function viewClass(classId) {
            window.location.href = `class_details.php?id=${classId}`;
        }

        function editClass(classId) {
            // Fetch class details
            fetch(`get_class.php?id=${classId}`)
                .then(response => response.json())
                .then(classData => {
                    // Populate the edit form with class data
                    document.getElementById('editClassId').value = classData.id;
                    document.getElementById('editGradeLevel').value = classData.grade_level;
                    loadSectionsForEdit(classData.grade_level, classData.section_id);
                    document.getElementById('editSubject').value = classData.subject_id;
                    document.getElementById('editTeacher').value = classData.teacher_id;
                    document.getElementById('editStatus').value = classData.status;
                    
                    // Show the modal
                    document.getElementById('editClassModal').classList.add('active');
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load class details');
                });
        }

        function loadSectionsForEdit(gradeLevel, selectedSectionId) {
            if (!gradeLevel) {
                document.getElementById('editSection').innerHTML = '<option value="">Select Section</option>';
                return;
            }

            fetch(`get_sections_by_grade.php?grade_level=${gradeLevel}`)
                .then(response => response.json())
                .then(sections => {
                    const sectionSelect = document.getElementById('editSection');
                    sectionSelect.innerHTML = '<option value="">Select Section</option>';
                    
                    sections.forEach(section => {
                        const option = document.createElement('option');
                        option.value = section.id;
                        option.textContent = section.section_name;
                        if (section.id == selectedSectionId) {
                            option.selected = true;
                        }
                        sectionSelect.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load sections');
                });
        }

        function toggleStatus(classId) {
            if (!confirm('Are you sure you want to change this class\'s status?')) {
                return;
            }

            fetch('toggle_class_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `class_id=${classId}`
            })
            .then(response => response.json())
            .then(data => {
                location.reload(); // Reload to show updated status
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to toggle class status');
            });
        }
    </script>
</body>
</html> 