<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Initialize variables
$sections = [];
$error = null;
$success = null;

try {
    // Fetch all sections with their statistics
    $stmt = $conn->prepare("
        SELECT 
            s.*,
            CONCAT(t.first_name, ' ', t.last_name) as adviser_name,
            (SELECT COUNT(*) 
             FROM students st 
             JOIN users u ON st.user_id = u.id
             WHERE st.section_id = s.id 
             AND u.status = 'active') as total_students,
            (SELECT COUNT(DISTINCT ce.id) 
             FROM classes c
             JOIN class_enrollments ce ON c.id = ce.class_id
             WHERE c.section_id = s.id 
             AND ce.status = 'active'
             AND c.status = 'active') as total_enrollments
        FROM sections s
        LEFT JOIN section_teachers st ON s.id = st.section_id AND st.is_adviser = 1
        LEFT JOIN teachers t ON st.teacher_id = t.id
        WHERE s.status = 'active'
        ORDER BY s.grade_level ASC, s.section_name ASC
    ");
    $stmt->execute();
    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    error_log("Error in sections page: " . $e->getMessage());
    $error = "An error occurred while loading the sections data.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Sections - STMA LMS</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/sections.css">
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
                    <i class="fas fa-users-class"></i>
                    <h2>Manage Sections</h2>
                </div>
                <div class="header-actions">
                    <button class="btn-secondary" onclick="viewArchivedSections()">
                        <i class="fas fa-archive"></i>
                        <span>View Archived</span>
                    </button>
                    <button class="btn-primary" onclick="showAddSectionModal()">
                        <i class="fas fa-plus"></i>
                        <span>Add New Section</span>
                    </button>
                </div>
            </div>

            <div class="filter-controls">
                <select id="gradeFilter" onchange="filterSections()">
                    <option value="">All Grade Levels</option>
                    <?php for($i = 1; $i <= 6; $i++): ?>
                        <option value="<?php echo $i; ?>">Grade <?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>

        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Grade Level</th>
                        <th>Section Name</th>
                        <th>Schedule</th>
                        <th>Adviser</th>
                        <th>Students</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sections as $section): ?>
                        <tr data-grade="<?php echo $section['grade_level']; ?>" 
                            data-section-id="<?php echo $section['id']; ?>">
                            <td>
                                <div class="grade-badge">
                                    Grade <?php echo htmlspecialchars($section['grade_level']); ?>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($section['section_name']); ?></td>
                            <td>
                                <div class="schedule-badge">
                                    <i class="fas fa-clock"></i>
                                    <span>
                                        <?php 
                                            echo date('g:i A', strtotime($section['time_start'])) . ' - ' . 
                                                 date('g:i A', strtotime($section['time_end'])); 
                                        ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <?php echo $section['adviser_name'] ? htmlspecialchars($section['adviser_name']) : 'No Adviser'; ?>
                            </td>
                            <td>
                                <div class="student-count">
                                    <i class="fas fa-user-graduate"></i>
                                    <span><?php echo $section['total_students']; ?> Students</span>
                                </div>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-icon view" onclick="viewSection(<?php echo $section['id']; ?>)" 
                                            title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn-icon edit" onclick="editSection(<?php echo $section['id']; ?>)" 
                                            title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-icon archive" onclick="archiveSection(<?php echo $section['id']; ?>)" 
                                            title="Archive Section">
                                        <i class="fas fa-archive"></i>
                                    </button>
                                    <button class="btn-icon assign" onclick="showAssignTeacherModal(<?php echo $section['id']; ?>)" 
                                            title="Assign Teachers">
                                        <i class="fas fa-user-plus"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Add these modals just before the closing </main> tag -->

    <!-- Add Section Modal -->
    <div id="addSectionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Section</h3>
                <button type="button" class="close-modal" onclick="closeModal('addSectionModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="addSectionForm" method="POST" action="add_section.php">
                    <div class="form-group">
                        <label for="gradeLevel">Grade Level</label>
                        <select name="grade_level" id="gradeLevel" class="form-control" required>
                            <option value="">Select Grade Level</option>
                            <?php for($i = 1; $i <= 6; $i++): ?>
                                <option value="<?php echo $i; ?>">Grade <?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="sectionName">Section Name</label>
                        <input type="text" name="section_name" id="sectionName" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="timeStart">Time Start</label>
                        <input type="time" name="time_start" id="timeStart" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="timeEnd">Time End</label>
                        <input type="time" name="time_end" id="timeEnd" class="form-control" required>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-secondary" onclick="closeModal('addSectionModal')">Cancel</button>
                        <button type="submit" class="btn-primary">Add Section</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Section Modal -->
    <div id="editSectionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Section</h3>
                <button type="button" class="close-modal" onclick="closeModal('editSectionModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editSectionForm" method="POST" action="update_section.php">
                    <input type="hidden" name="section_id" id="editSectionId">
                    
                    <div class="form-group">
                        <label for="editGradeLevel">Grade Level</label>
                        <select name="grade_level" id="editGradeLevel" class="form-control" required>
                            <option value="">Select Grade Level</option>
                            <?php for($i = 1; $i <= 6; $i++): ?>
                                <option value="<?php echo $i; ?>">Grade <?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="editSectionName">Section Name</label>
                        <input type="text" name="section_name" id="editSectionName" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="editTimeStart">Time Start</label>
                        <input type="time" name="time_start" id="editTimeStart" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="editTimeEnd">Time End</label>
                        <input type="time" name="time_end" id="editTimeEnd" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="editStatus">Status</label>
                        <select name="status" id="editStatus" class="form-control" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-secondary" onclick="closeModal('editSectionModal')">Cancel</button>
                        <button type="submit" class="btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Assign Teacher Modal -->
    <div id="assignTeacherModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Assign Teachers</h3>
                <button type="button" class="close-modal" onclick="closeModal('assignTeacherModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="assignTeacherForm" method="POST" action="assign_teachers.php">
                    <input type="hidden" name="section_id" id="sectionId">
                    
                    <div class="form-group">
                        <label for="adviser">Section Adviser</label>
                        <select name="adviser_id" id="adviser" class="form-control">
                            <option value="">Select Adviser</option>
                            <?php
                            try {
                                // Modified query to correctly join with teachers table
                                $teacherStmt = $conn->prepare("
                                    SELECT 
                                        t.id,
                                        t.user_id,
                                        t.first_name,
                                        t.last_name,
                                        t.employee_id,
                                        st.section_id,
                                        s.section_name
                                    FROM teachers t
                                    LEFT JOIN section_teachers st ON t.id = st.teacher_id 
                                        AND st.is_adviser = 1
                                    LEFT JOIN sections s ON st.section_id = s.id 
                                        AND s.status = 'active'
                                    JOIN users u ON t.user_id = u.id
                                    WHERE u.status = 'active'
                                    ORDER BY t.last_name, t.first_name
                                ");
                                $teacherStmt->execute();
                                
                                while ($teacher = $teacherStmt->fetch(PDO::FETCH_ASSOC)) {
                                    $isAssigned = !empty($teacher['section_id']);
                                    $status = $isAssigned ? 
                                        " (Current Adviser: " . htmlspecialchars($teacher['section_name']) . ")" : 
                                        " (Available)";
                                    
                                    printf(
                                        '<option value="%d" %s>%s %s (%s) %s</option>',
                                        $teacher['id'],
                                        $isAssigned ? 'class="assigned-teacher"' : '',
                                        htmlspecialchars($teacher['first_name']),
                                        htmlspecialchars($teacher['last_name']),
                                        htmlspecialchars($teacher['employee_id']),
                                        $status
                                    );
                                }
                            } catch (PDOException $e) {
                                error_log("Error fetching teachers: " . $e->getMessage());
                                echo '<option value="">Error loading teachers</option>';
                            }
                            ?>
                        </select>
                        <small class="form-text text-muted">
                            Note: Teachers marked with sections are currently assigned as advisers. 
                            Available teachers can be assigned to new sections.
                        </small>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-secondary" onclick="closeModal('assignTeacherModal')">Cancel</button>
                        <button type="submit" class="btn-primary">Save Assignments</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Archive Section Modal -->
    <div id="archiveSectionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Archive Section</h3>
                <button type="button" class="close-modal" onclick="closeModal('archiveSectionModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to archive this section? This will move all related data to the archive.</p>
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('archiveSectionModal')">Cancel</button>
                    <button type="button" class="btn-danger" onclick="confirmArchive()">Archive Section</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Modal Functions
        function showAddSectionModal() {
            document.getElementById('addSectionModal').classList.add('active');
            // Reset form
            document.getElementById('addSectionForm').reset();
        }

        function showEditSectionModal(sectionId) {
            document.getElementById('editSectionModal').classList.add('active');
            // Fetch section details and populate form
            fetch(`get_section.php?id=${sectionId}`)
                .then(response => response.json())
                .then(section => {
                    document.getElementById('editSectionId').value = section.id;
                    document.getElementById('editGradeLevel').value = section.grade_level;
                    document.getElementById('editSectionName').value = section.section_name;
                    document.getElementById('editTimeStart').value = section.time_start;
                    document.getElementById('editTimeEnd').value = section.time_end;
                    document.getElementById('editStatus').value = section.status;
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load section details');
                });
        }

        function showAssignTeacherModal(sectionId) {
            document.getElementById('assignTeacherModal').classList.add('active');
            document.getElementById('sectionId').value = sectionId;
            
            // Fetch current assignments
            fetch(`get_section_teachers.php?section_id=${sectionId}`)
                .then(response => response.json())
                .then(data => {
                    const adviserSelect = document.getElementById('adviser');
                    if (data.adviser) {
                        adviserSelect.value = data.adviser.teacher_id;
                    } else {
                        adviserSelect.value = '';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load current assignments');
                });
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        // Action Functions
        function viewSection(sectionId) {
            window.location.href = `section_details.php?id=${sectionId}`;
        }

        function editSection(sectionId) {
            showEditSectionModal(sectionId);
        }

        let selectedSectionId = null;

        function archiveSection(sectionId) {
            selectedSectionId = sectionId;
            document.getElementById('archiveSectionModal').classList.add('active');
        }

        function confirmArchive() {
            if (!selectedSectionId) return;

            fetch('archive_section.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `section_id=${selectedSectionId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Section archived successfully');
                    location.reload();
                } else {
                    throw new Error(data.message || 'Failed to archive section');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to archive section');
            })
            .finally(() => {
                closeModal('archiveSectionModal');
            });
        }

        function viewArchivedSections() {
            window.location.href = 'archived_sections.php';
        }

        // Filter Function
        function filterSections() {
            const gradeFilter = document.getElementById('gradeFilter').value;
            const rows = document.querySelectorAll('.data-table tbody tr');

            rows.forEach(row => {
                const grade = row.getAttribute('data-grade');
                const matchesGrade = !gradeFilter || grade === gradeFilter;
                row.style.display = matchesGrade ? '' : 'none';
            });

            // Update empty state message
            const visibleRows = document.querySelectorAll('.data-table tbody tr:not([style*="display: none"])');
            const tbody = document.querySelector('.data-table tbody');
            const existingEmptyMessage = document.querySelector('.empty-message');

            if (visibleRows.length === 0) {
                if (!existingEmptyMessage) {
                    const emptyRow = document.createElement('tr');
                    emptyRow.className = 'empty-message';
                    emptyRow.innerHTML = `
                        <td colspan="6" style="text-align: center; padding: 2rem;">
                            <div style="color: var(--text-secondary);">
                                <i class="fas fa-search" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                                <p>No sections found matching the selected filters</p>
                            </div>
                        </td>
                    `;
                    tbody.appendChild(emptyRow);
                }
            } else if (existingEmptyMessage) {
                existingEmptyMessage.remove();
            }
        }

        // Initialize filters on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Set initial filter values from URL parameters if any
            const urlParams = new URLSearchParams(window.location.search);
            const gradeParam = urlParams.get('grade');
            const statusParam = urlParams.get('status');

            if (gradeParam) {
                document.getElementById('gradeFilter').value = gradeParam;
            }
            if (statusParam) {
                document.getElementById('statusFilter').value = statusParam;
            }

            // Apply initial filters
            filterSections();
        });
    </script>
</body>
</html> 