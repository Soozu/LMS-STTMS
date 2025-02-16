document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.tab-btn');
    const cards = document.querySelectorAll('.assignment-card');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            // Update active tab
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');

            // Filter cards
            const type = tab.dataset.type;
            cards.forEach(card => {
                if (type === 'all' || card.dataset.type === type) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });

    // Add file upload handling
    document.querySelectorAll('.file-upload-input').forEach(input => {
        input.addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            const selectedFileDiv = this.parentElement.querySelector('.selected-file');
            
            if (fileName) {
                selectedFileDiv.innerHTML = `
                    <i class="fas fa-check-circle"></i>
                    Selected file: ${fileName}
                `;
                selectedFileDiv.style.display = 'block';
            } else {
                selectedFileDiv.style.display = 'none';
            }
        });
    });
}); 