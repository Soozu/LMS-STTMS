<?php
// Create CSV template instead of Excel
$headers = [
    'LRN*',
    'First Name*',
    'Last Name*',
    'Email',
    'Grade Level',
    'Section Name',
    'Gender',
    'Birth Date',
    'Contact Number',
    'Address',
    'Guardian Name',
    'Guardian Contact'
];

// Multiple example students
$examples = [
    [
        '123456789012',
        'John',
        'Doe',
        'john.doe@email.com',
        '7',
        'St. Maria Goretti',
        'male',
        '2010-01-01',
        '09123456789',
        'Sample Address 1, Bacoor, Cavite',
        'Jane Doe',
        '09987654321'
    ],
    [
        '123456789013',
        'Maria',
        'Santos',
        'maria.santos@email.com',
        '8',
        'St. Francis of Assisi',
        'female',
        '2009-05-15',
        '09234567890',
        'Sample Address 2, Bacoor, Cavite',
        'Pedro Santos',
        '09876543210'
    ],
    [
        '123456789014',
        'Michael',
        'Garcia',
        'michael.garcia@email.com',
        '9',
        'St. Ignatius of Loyola',
        'male',
        '2008-08-20',
        '09345678901',
        'Sample Address 3, Bacoor, Cavite',
        'Ana Garcia',
        '09765432109'
    ]
];

// Create templates directory if it doesn't exist
if (!file_exists('templates')) {
    mkdir('templates', 0777, true);
}

// Open file for writing
$fp = fopen('templates/student_import_template.csv', 'w');

// Add headers
fputcsv($fp, $headers);

// Add example rows
foreach ($examples as $example) {
    fputcsv($fp, $example);
}

// Close file
fclose($fp);

// Set headers for download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="student_import_template.csv"');
header('Pragma: no-cache');
readfile('templates/student_import_template.csv');
?> 