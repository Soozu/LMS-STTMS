// File upload handling
document.getElementById('csvFile').addEventListener('change', function(e) {
    const fileName = e.target.files[0]?.name;
    const fileSize = e.target.files[0]?.size;
    const maxSize = 5 * 1024 * 1024; // 5MB
    const allowedExtensions = ['csv', 'xls', 'xlsx'];
    const fileExtension = fileName?.split('.').pop().toLowerCase();

    if (fileSize > maxSize) {
        alert('File size exceeds 5MB limit');
        e.target.value = '';
        return;
    }

    if (!allowedExtensions.includes(fileExtension)) {
        alert('Please upload a CSV or Excel file');
        e.target.value = '';
        return;
    }

    if (fileName) {
        document.getElementById('selectedFile').textContent = `Selected file: ${fileName}`;
    } else {
        document.getElementById('selectedFile').textContent = '';
    }
});

// Form submission handling
document.getElementById('importForm').addEventListener('submit', function(e) {
    const fileInput = document.getElementById('csvFile');
    if (!fileInput.files.length) {
        e.preventDefault();
        alert('Please select a file to import');
        return;
    }
});

// Prevent duplicate form submissions
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        // Check if form is already being submitted
        if (this.hasAttribute('data-submitting')) {
            e.preventDefault();
            return;
        }

        // Add submitting flag
        this.setAttribute('data-submitting', 'true');

        // Remove flag after 5 seconds (in case of errors)
        setTimeout(() => {
            this.removeAttribute('data-submitting');
        }, 5000);
    });
});

// Add confirmation for important actions
function confirmAction(message) {
    return window.confirm(message);
}

// Update the edit and delete functions
function editStudent(id) {
    if (!confirmAction('Are you sure you want to edit this student?')) {
        return;
    }
    // existing edit code...
}

// Add form validation
document.getElementById('addStudentForm').addEventListener('submit', function(e) {
    const lrn = document.getElementById('lrn').value;
    const email = document.getElementById('email').value;

    // Validate LRN format
    if (!/^\d{12}$/.test(lrn)) {
        e.preventDefault();
        alert('LRN must be exactly 12 digits');
        return;
    }

    // Validate email format
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        e.preventDefault();
        alert('Please enter a valid email address');
        return;
    }
}); 