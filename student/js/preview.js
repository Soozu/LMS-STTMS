function previewFile(filePath, fileExt, fileName) {
    if (fileExt.toLowerCase() !== 'pdf') {
        alert('Preview is only available for PDF files');
        return;
    }

    // Get just the filename from the path
    const pdfFileName = filePath.split('/').pop();
    
    // Create URL for the viewer with the PDF file parameter
    const viewerUrl = `viewer.php?file=${encodeURIComponent(pdfFileName)}`;
    
    // Open viewer in new tab
    window.open(viewerUrl, '_blank');
} 