<?php

class NotificationController
{
    public static function index(PDO $pdo, array $user): void
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $where = [];
        $params = [];

        // Agents see their own notifications and system-wide notifications.
        // Admins see all notifications.
        if (($user['role'] ?? '') !== 'admin') {
            $where[] = '(n.agent_id = :agent_id OR n.agent_id IS NULL)';
            $params['agent_id'] = (int)$user['id'];
        }

        // Exclude notifications that have expired.
        $where[] = '(n.expires_at IS NULL OR n.expires_at >= NOW())';

        // Filter by read status.
        if (isset($_GET['is_read']) && $_GET['is_read'] !== '') {
            $isRead = filter_var(
                $_GET['is_read'],
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );

            if ($isRead === null) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'is_read must be true or false.'
                ]);
                return;
            }

            $where[] = 'n.is_read = :is_read';
            $params['is_read'] = $isRead ? 1 : 0;
        }

        // Filter by notification type.
        $allowedTypes = [
            'system',
            'sales',
            'commission',
            'target',
            'visit',
            'invoice',
            'payment',
            'followup',
            'announcement'
        ];

        if (!empty($_GET['notification_type'])) {
            if (!in_array($_GET['notification_type'], $allowedTypes, true)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid notification_type.'
                ]);
                return;
            }

            $where[] = 'n.notification_type = :notification_type';
            $params['notification_type'] = $_GET['notification_type'];
        }

        // Filter by priority.
        $allowedPriorities = [
            'low',
            'normal',
            'high',
            'urgent'
        ];

        if (!empty($_GET['priority'])) {
            if (!in_array($_GET['priority'], $allowedPriorities, true)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid priority.'
                ]);
                return;
            }

            $where[] = 'n.priority = :priority';
            $params['priority'] = $_GET['priority'];
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        // Count matching notifications.
        $countStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM notifications n
            $whereSql
        ");

        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Retrieve notifications.
        $stmt = $pdo->prepare("
            SELECT
                n.id,
                n.agent_id,
                a.full_name AS agent_name,
                n.notification_type,
                n.title,
                n.message,
                n.data_json,
                n.priority,
                n.is_read,
                n.read_at,
                n.expires_at,
                n.created_at
            FROM notifications n
            LEFT JOIN agents a
                ON a.id = n.agent_id
            $whereSql
            ORDER BY
                n.created_at DESC,
                n.id DESC
            LIMIT :limit OFFSET :offset
        ");

        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();

        $notifications = $stmt->fetchAll();

        // Decode JSON payloads and normalize boolean read status.
        foreach ($notifications as &$notification) {
            if (!empty($notification['data_json'])) {
                $decoded = json_decode(
                    $notification['data_json'],
                    true
                );

                $notification['data'] = (
                    json_last_error() === JSON_ERROR_NONE
                ) ? $decoded : null;
            } else {
                $notification['data'] = null;
            }

            unset($notification['data_json']);

            $notification['is_read'] = (bool)$notification['is_read'];
        }

        unset($notification);

        // Count unread notifications visible to this user.
        $unreadStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM notifications n
            $whereSql
            AND n.is_read = 0
        ");

        $unreadStmt->execute($params);
        $unreadCount = (int)$unreadStmt->fetchColumn();

        echo json_encode([
            'success' => true,
            'data' => $notifications,
            'unread_count' => $unreadCount,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int)ceil($total / $limit)
            ]
        ]);
    }

    /**
     * Mark a notification as read.
     */
    public static function markRead(PDO $pdo, array $user, int $id): void
    {
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid notification ID.'
            ]);
            return;
        }

        $isAdmin = ($user['role'] ?? '') === 'admin';

        if ($isAdmin) {
            $stmt = $pdo->prepare("
                SELECT id, agent_id, is_read
                FROM notifications
                WHERE id = :id
                LIMIT 1
            ");
            $stmt->execute(['id' => $id]);
        } else {
            // Agents can mark only their own notifications.
            // Global notifications require per-user read tracking.
            $stmt = $pdo->prepare("
                SELECT id, agent_id, is_read
                FROM notifications
                WHERE id = :id
                  AND agent_id = :agent_id
                LIMIT 1
            ");
            $stmt->execute([
                'id' => $id,
                'agent_id' => $user['id']
            ]);
        }

        $notification = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$notification) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Notification not found or access denied.'
            ]);
            return;
        }

        $update = $pdo->prepare("
            UPDATE notifications
            SET is_read = 1,
                read_at = COALESCE(read_at, NOW())
            WHERE id = :id
        ");

        $update->execute(['id' => $id]);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Notification marked as read.',
            'data' => [
                'id' => (int) $notification['id'],
                'is_read' => true,
                'read_at' => date('Y-m-d H:i:s')
            ]
        ]);
    }

}
