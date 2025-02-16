document.addEventListener('DOMContentLoaded', function() {
    const submissionForm = document.getElementById('submissionForm');
    const submitBtn = document.getElementById('submitBtn');

    if (submissionForm) {
        submissionForm.addEventListener('submit', function() {
            // Disable the submit button
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
        });
    }
}); 