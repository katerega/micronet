<?php

class AuthMiddleware
{
    /**
     * Authenticate an administrator using a Bearer access token.
     * Returns the administrator record or null after sending an error.
     */
    public static function requireAdmin(PDO $pdo): ?array
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (!$authHeader && function_exists('getallheaders')) {
            $headers = getallheaders();
            $authHeader = $headers['Authorization']
                ?? $headers['authorization']
                ?? '';
        }

        if (!preg_match('/^Bearer\s+(.+)$/i', trim($authHeader), $matches)) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Authentication token is required.'
            ]);
            return null;
        }

        $tokenHash = hash('sha256', trim($matches[1]));

        $stmt = $pdo->prepare("
            SELECT
                a.id,
                a.full_name,
                a.email,
                a.phone,
                a.role,
                a.status
            FROM sessions s
            INNER JOIN agents a ON a.id = s.agent_id
            WHERE s.access_token_hash = :token_hash
              AND s.revoked_at IS NULL
              AND s.expires_at > NOW()
            LIMIT 1
        ");

        $stmt->execute([
            'token_hash' => $tokenHash
        ]);

        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid, expired, or revoked access token.'
            ]);
            return null;
        }

        if ($admin['status'] !== 'active' || $admin['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Administrator privileges are required.'
            ]);
            return null;
        }

        return $admin;
    }

    public static function requireAuth(PDO $pdo): ?array
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $authorization = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (!$authorization && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authorization = $_SERVER['HTTP_AUTHORIZATION'];
        }

        if (!preg_match('/Bearer\s+(.+)/i', $authorization, $matches)) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Authentication token required.'
            ]);
            return null;
        }

        $tokenHash = hash('sha256', trim($matches[1]));

        $stmt = $pdo->prepare(
            "SELECT a.id, a.full_name, a.email, a.role, a.status
             FROM sessions s
             INNER JOIN agents a ON a.id = s.agent_id
             WHERE s.access_token_hash = :token_hash
               AND s.revoked_at IS NULL
               AND s.expires_at > CURRENT_TIMESTAMP
               AND a.status = 'active'
             LIMIT 1"
        );

        $stmt->execute(['token_hash' => $tokenHash]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid, expired, or revoked session.'
            ]);
            return null;
        }

        return $user;
    }

}
