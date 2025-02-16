document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.querySelector('.file-input');
    const fileNameDisplay = document.querySelector('.selected-file-name');

    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            if (fileName) {
                fileNameDisplay.textContent = fileName;
            } else {
                fileNameDisplay.textContent = '';
            }
        });
    }
}); 