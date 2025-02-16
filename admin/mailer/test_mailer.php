<?php
require_once 'email_config.php';

// Test the email sending
try {
    $result = sendCredentialsEmail(
        'jeanelleegalasinao@gmail.com', // Use your email for testing
        'Test Student',
        '123456789012',
        'testpassword123'
    );

    if ($result) {
        echo "<h2 style='color: green;'>Test email sent successfully!</h2>";
    } else {
        echo "<h2 style='color: red;'>Failed to send test email.</h2>";
    }
} catch (Exception $e) {
    echo "<h2 style='color: red;'>Error: " . $e->getMessage() . "</h2>";
} 