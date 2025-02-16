<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Initialize variables
$subjects = [];
$error = null;
$success = null;

try {
    // Fetch all subjects with class count
    $stmt = $conn->prepare("
        SELECT 
            s.*,
            (SELECT COUNT(DISTINCT c.id) 
             FROM classes c 
             WHERE c.subject_id = s.id 
             AND c.status = 'active') as class_count
        FROM subjects s
        ORDER BY s.grade_level ASC, s.subject_name ASC
    ");
    $stmt->execute();
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    error_log("Error in subjects page: " . $e->getMessage());
    $error = "An error occurred while loading the subjects data.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Subjects - STMA LMS</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/subjects.css">
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
                    <i class="fas fa-book"></i>
                    <h2>Manage Subjects</h2>
                </div>
                <button class="btn-primary" onclick="showAddSubjectModal()">
                    <i class="fas fa-plus"></i>
                    <span>Add New Subject</span>
                </button>
            </div>
        </div>

        <div class="filter-controls">
            <select id="gradeFilter" onchange="filterSubjects()">
                <option value="">All Grade Levels</option>
                <?php for($i = 1; $i <= 6; $i++): ?>
                    <option value="<?php echo $i; ?>">Grade <?php echo $i; ?></option>
                <?php endfor; ?>
            </select>
            
            <select id="statusFilter" onchange="filterSubjects()">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>

        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Grade Level</th>
                        <th>Subject Name</th>
                        <th>Description</th>
                        <th>Classes</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // Sort subjects by grade level and name
                    usort($subjects, function($a, $b) {
                        if ($a['grade_level'] != $b['grade_level']) {
                            return $a['grade_level'] - $b['grade_level'];
                        }
                        return strcmp($a['subject_name'], $b['subject_name']);
                    });
                    
                    foreach ($subjects as $subject): 
                    ?>
                        <tr data-grade="<?php echo $subject['grade_level']; ?>" 
                            data-status="<?php echo $subject['status']; ?>">
                            <td>
                                <div class="grade-badge">
                                    Grade <?php echo htmlspecialchars($subject['grade_level']); ?>
                                </div>
                            </td>
                            <td>
                                <div class="subject-name">
                                    <?php echo htmlspecialchars($subject['subject_name']); ?>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($subject['description']); ?></td>
                            <td>
                                <div class="class-count">
                                    <i class="fas fa-chalkboard"></i>
                                    <span><?php echo $subject['class_count']; ?> Classes</span>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $subject['status']; ?>">
                                    <?php echo ucfirst($subject['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-icon edit" onclick="editSubject(<?php echo $subject['id']; ?>)" 
                                            title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-icon toggle" onclick="toggleStatus(<?php echo $subject['id']; ?>)" 
                                            title="Toggle Status">
                                        <i class="fas fa-power-off"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Add Subject Modal -->
        <div id="addSubjectModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Add New Subject</h3>
                    <button type="button" class="close-modal" onclick="closeModal('addSubjectModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="addSubjectForm" method="POST" action="add_subject.php">
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
                            <label for="subjectName">Subject Name</label>
                            <input type="text" name="subject_name" id="subjectName" class="form-control" required 
                                   placeholder="Enter subject name">
                        </div>

                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea name="description" id="description" class="form-control" rows="3" required 
                                      placeholder="Enter subject description"></textarea>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn-secondary" onclick="closeModal('addSubjectModal')">Cancel</button>
                            <button type="submit" class="btn-primary">Add Subject</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit Subject Modal -->
        <div id="editSubjectModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Edit Subject</h3>
                    <button type="button" class="close-modal" onclick="closeModal('editSubjectModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="editSubjectForm" method="POST" action="update_subject.php">
                        <input type="hidden" name="subject_id" id="editSubjectId">
                        
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
                            <label for="editSubjectName">Subject Name</label>
                            <input type="text" name="subject_name" id="editSubjectName" class="form-control" required 
                                   placeholder="Enter subject name">
                        </div>

                        <div class="form-group">
                            <label for="editDescription">Description</label>
                            <textarea name="description" id="editDescription" class="form-control" rows="3" required 
                                      placeholder="Enter subject description"></textarea>
                        </div>

                        <div class="form-group">
                            <label for="editStatus">Status</label>
                            <select name="status" id="editStatus" class="form-control" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn-secondary" onclick="closeModal('editSubjectModal')">Cancel</button>
                            <button type="submit" class="btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script>
        function showAddSubjectModal() {
            document.getElementById('addSubjectModal').classList.add('active');
            document.getElementById('addSubjectForm').reset();
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function editSubject(subjectId) {
            // Fetch subject details
            fetch(`get_subject.php?id=${subjectId}`)
                .then(response => response.json())
                .then(subject => {
                    // Populate the edit form with subject data
                    document.getElementById('editSubjectId').value = subject.id;
                    document.getElementById('editGradeLevel').value = subject.grade_level;
                    document.getElementById('editSubjectName').value = subject.subject_name;
                    document.getElementById('editDescription').value = subject.description;
                    document.getElementById('editStatus').value = subject.status;
                    
                    // Show the modal
                    document.getElementById('editSubjectModal').classList.add('active');
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load subject details');
                });
        }

        function toggleStatus(subjectId) {
            if (!confirm('Are you sure you want to change this subject\'s status?')) {
                return;
            }

            fetch('toggle_subject_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `subject_id=${subjectId}`
            })
            .then(response => response.json())
            .then(data => {
                // Find the subject card and update its appearance
                const subjectCard = document.querySelector(`button[onclick="toggleStatus(${subjectId})"]`)
                    .closest('.subject-card');
                
                if (data.status === 'inactive') {
                    subjectCard.classList.add('inactive');
                    subjectCard.querySelector('.fa-power-off').style.color = '#dc3545';
                } else {
                    subjectCard.classList.remove('inactive');
                    subjectCard.querySelector('.fa-power-off').style.color = '#28a745';
                }

                // Show success message
                const alert = document.createElement('div');
                alert.className = 'alert alert-success';
                alert.textContent = `Subject status changed to ${data.status}`;
                document.querySelector('.page-header').insertAdjacentElement('afterend', alert);

                // Remove alert after 3 seconds
                setTimeout(() => alert.remove(), 3000);
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to toggle subject status');
            });
        }

        function toggleView(view) {
            const gridView = document.querySelector('.subjects-grid');
            const tableView = document.getElementById('tableView');
            const gridBtn = document.getElementById('gridViewBtn');
            const tableBtn = document.getElementById('tableViewBtn');
            
            if (view === 'grid') {
                gridView.style.display = 'grid';
                tableView.style.display = 'none';
                gridBtn.classList.add('active');
                tableBtn.classList.remove('active');
            } else {
                gridView.style.display = 'none';
                tableView.style.display = 'block';
                gridBtn.classList.remove('active');
                tableBtn.classList.add('active');
            }
        }

        function filterSubjects() {
            const gradeFilter = document.getElementById('gradeFilter').value;
            const statusFilter = document.getElementById('statusFilter').value;
            const rows = document.querySelectorAll('.data-table tbody tr');

            rows.forEach(row => {
                const grade = row.getAttribute('data-grade');
                const status = row.getAttribute('data-status');
                
                // Check if row matches both filters
                const matchesGrade = !gradeFilter || grade === gradeFilter;
                const matchesStatus = !statusFilter || status === statusFilter;
                
                // Show/hide row based on filters
                if (matchesGrade && matchesStatus) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
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
                                <p>No subjects found matching the selected filters</p>
                            </div>
                        </td>
                    `;
                    tbody.appendChild(emptyRow);
                }
            } else if (existingEmptyMessage) {
                existingEmptyMessage.remove();
            }
        }

        // Add these helper functions for the filter reset
        function resetFilters() {
            document.getElementById('gradeFilter').value = '';
            document.getElementById('statusFilter').value = '';
            filterSubjects();
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
            filterSubjects();
        });

        // Update URL when filters change
        function updateURL() {
            const grade = document.getElementById('gradeFilter').value;
            const status = document.getElementById('statusFilter').value;
            const url = new URL(window.location.href);
            
            if (grade) {
                url.searchParams.set('grade', grade);
            } else {
                url.searchParams.delete('grade');
            }
            
            if (status) {
                url.searchParams.set('status', status);
            } else {
                url.searchParams.delete('status');
            }

            window.history.replaceState({}, '', url);
        }

        // Update the filter controls to include the event
        document.getElementById('gradeFilter').addEventListener('change', function() {
            filterSubjects();
            updateURL();
        });

        document.getElementById('statusFilter').addEventListener('change', function() {
            filterSubjects();
            updateURL();
        });

        // Add data attributes to grid cards
        document.querySelectorAll('.subject-card').forEach(card => {
            const gradeLevel = card.querySelector('.grade-level').textContent.replace('Grade ', '');
            card.dataset.grade = gradeLevel;
            card.dataset.status = card.classList.contains('inactive') ? 'inactive' : 'active';
        });

        // Initialize view
        document.addEventListener('DOMContentLoaded', function() {
            // Show grid view by default
            const gridView = document.querySelector('.subjects-grid');
            if (gridView) {
                gridView.style.display = 'grid';
            }
            document.getElementById('tableView').style.display = 'none';
        });
    </script>
</body>
</html> 