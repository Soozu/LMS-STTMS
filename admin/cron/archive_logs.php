<?php
require_once '../../includes/config.php';

try {
    $conn->beginTransaction();

    // Get logs older than 30 days
    $stmt = $conn->prepare("
        INSERT INTO archived_system_logs (
            user_id, action, description, created_at, 
            ip_address, user_agent
        )
        SELECT 
            user_id, action, description, created_at,
            ip_address, user_agent
        FROM system_logs
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute();

    // Delete the archived logs from the main table
    $stmt = $conn->prepare("
        DELETE FROM system_logs 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute();

    $conn->commit();
    
    // Log the archiving action
    $archivedCount = $stmt->rowCount();
    if ($archivedCount > 0) {
        $stmt = $conn->prepare("
            INSERT INTO system_logs (user_id, action, description) 
            VALUES (1, 'Archive Logs', ?)
        ");
        $stmt->execute(["Automatically archived $archivedCount system logs"]);
    }

    echo "Successfully archived $archivedCount logs\n";

} catch (Exception $e) {
    $conn->rollBack();
    error_log("Error archiving logs: " . $e->getMessage());
    echo "Error archiving logs: " . $e->getMessage() . "\n";
} 