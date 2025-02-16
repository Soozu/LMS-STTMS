// Add this script to prevent multiple form submissions
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (this.hasAttribute('data-submitted')) {
                e.preventDefault();
                return;
            }
            this.setAttribute('data-submitted', 'true');
            setTimeout(() => this.removeAttribute('data-submitted'), 5000);
        });
    });

    // Disable autocomplete on sensitive forms
    const sensitiveInputs = document.querySelectorAll('input[type="text"], input[type="email"]');
    sensitiveInputs.forEach(input => {
        input.setAttribute('autocomplete', 'off');
    });
}); 