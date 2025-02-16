<?php
// File handling functions
function getAllowedFileTypes() {
    return [
        // Documents
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain',
        
        // Images
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/bmp',
        'image/webp',
        
        // Videos
        'video/mp4',
        'video/mpeg',
        'video/quicktime',
        'video/x-msvideo',
        'video/x-ms-wmv',
        'video/webm', // Add support for WebM
        
        // Audio
        'audio/mpeg',
        'audio/wav',
        'audio/midi',
        'audio/x-midi',
        'audio/ogg',
        
        // Archives
        'application/zip',
        'application/x-rar-compressed',
        'application/x-7z-compressed'
    ];
}

function getMaxFileSize() {
    return 100 * 1024 * 1024; // 100MB
}

function validateFile($file) {
    $allowedTypes = getAllowedFileTypes();
    $maxSize = getMaxFileSize();
    
    if ($file['size'] > $maxSize) {
        throw new Exception("File size exceeds limit of " . ($maxSize / 1024 / 1024) . "MB");
    }
    
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception("File type not allowed: " . $file['type']);
    }
    
    return true;
}

/**
 * Get the appropriate Font Awesome icon class based on file extension
 * @param string $extension File extension
 * @return string Font Awesome icon class
 */
function getFileIcon($fileType) {
    switch ($fileType) {
        case 'application/pdf':
            return 'fas fa-file-pdf';
        case 'application/msword':
        case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
            return 'fas fa-file-word';
        case 'application/vnd.ms-excel':
        case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
            return 'fas fa-file-excel';
        case 'application/vnd.ms-powerpoint':
        case 'application/vnd.openxmlformats-officedocument.presentationml.presentation':
            return 'fas fa-file-powerpoint';
        case 'image/jpeg':
        case 'image/png':
        case 'image/gif':
            return 'fas fa-file-image';
        case 'video/mp4':
        case 'video/mpeg':
            return 'fas fa-file-video';
        default:
            return 'fas fa-file';
    }
}

// Date formatting function
function formatDateTime($datetime) {
    return date('M d, Y h:i A', strtotime($datetime));
}

// String helpers
function truncateText($text, $length = 100) {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . '...';
} 