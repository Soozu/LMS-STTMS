<?php
// Create directories if they don't exist
$directories = [
    '../uploads',
    '../uploads/assignments',
    '../uploads/submissions'
];

foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
} 