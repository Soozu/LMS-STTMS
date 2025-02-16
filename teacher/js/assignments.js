function handleFileSelect(input) {
    const fileList = document.getElementById('fileList');
    fileList.innerHTML = '';
    
    Array.from(input.files).forEach(file => {
        const fileItem = document.createElement('div');
        fileItem.className = 'file-item ' + getFileType(file.type);
        
        const icon = getFileIcon(file.type);
        const size = formatFileSize(file.size);
        
        if (file.type.startsWith('video/')) {
            fileItem.innerHTML = `
                <i class="${icon}"></i>
                <div class="video-info">
                    <span class="video-name">${file.name}</span>
                    <span class="video-size">${size}</span>
                </div>
                <button type="button" class="remove-file" onclick="this.parentElement.remove()">×</button>
            `;
        } else {
            fileItem.innerHTML = `
                <i class="${icon}"></i>
                <span>${file.name}</span>
                <button type="button" class="remove-file" onclick="this.parentElement.remove()">×</button>
            `;
        }
        
        fileList.appendChild(fileItem);
    });
}

function getFileType(mimeType) {
    if (mimeType.startsWith('video/')) return 'video';
    if (mimeType.startsWith('image/')) return 'image';
    if (mimeType.includes('pdf')) return 'pdf';
    return 'document';
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
} 