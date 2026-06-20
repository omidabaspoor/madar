<?php
/**
 * Activity Logging System - Clean & Organized
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Log an activity (very clean and efficient)
 */
function log_activity(
    int $userId,
    string $action,
    ?string $targetType = null,
    ?int $targetId = null,
    ?array $details = null
): void {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $stmt = db()->prepare("
            INSERT INTO activity_logs 
            (user_id, action, target_type, target_id, details, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $userId,
            $action,
            $targetType,
            $targetId,
            $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
            $ip,
            $ua
        ]);
    } catch (Throwable $e) {
        // Silent fail - logging should never break the app
        if (defined('APP_ENV') && APP_ENV === 'development') {
            error_log("Activity log error: " . $e->getMessage());
        }
    }
}

/**
 * Get recent activity logs (for admin panel)
 */
function get_recent_logs(int $limit = 50, ?int $advisorId = null): array {
    $sql = "
        SELECT l.*, u.full_name as user_name, u.role as user_role
        FROM activity_logs l
        LEFT JOIN users u ON u.id = l.user_id
        WHERE 1=1
    ";
    $params = [];

    if ($advisorId) {
        $sql .= " AND (u.id = ? OR u.advisor_id = ?)";
        $params[] = $advisorId;
        $params[] = $advisorId;
    }

    $sql .= " ORDER BY l.created_at DESC LIMIT ?";
    $params[] = $limit;

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Get logs for a specific user
 */
function get_user_logs(int $userId, int $limit = 30): array {
    $stmt = db()->prepare("
        SELECT * FROM activity_logs 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT ?
    ");
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll();
}