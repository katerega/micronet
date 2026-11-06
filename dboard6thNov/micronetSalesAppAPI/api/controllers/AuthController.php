<?php

class AuthController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * POST /api/v1/auth/login
     * Login using email or phone.
     */
    public function login(array $data): void
    {
        $identity = trim($data['identity'] ?? '');
        $password = $data['password'] ?? '';

        if ($identity === '' || $password === '') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Email/phone and password are required.'
            ]);
            return;
        }

        $stmt = $this->pdo->prepare("
            SELECT id, full_name, email, phone, password_hash, role, status
            FROM agents
            WHERE email = :email OR phone = :phone
            LIMIT 1
        ");

        $stmt->execute([
            'email' => $identity,
            'phone' => $identity
        ]);

        $agent = $stmt->fetch(PDO::FETCH_ASSOC);

        if (
            !$agent ||
            !password_verify($password, $agent['password_hash'])
        ) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid login credentials.'
            ]);
            return;
        }

        if ($agent['status'] !== 'active') {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Your account is inactive or suspended.'
            ]);
            return;
        }

        $accessToken = bin2hex(random_bytes(32));
        $refreshToken = bin2hex(random_bytes(32));

        $accessExpiry = date('Y-m-d H:i:s', time() + 900);
        $refreshExpiry = date('Y-m-d H:i:s', time() + (30 * 86400));

        $insert = $this->pdo->prepare("
            INSERT INTO sessions (
                agent_id,
                access_token_hash,
                refresh_token_hash,
                ip_address,
                user_agent,
                expires_at,
                refresh_expires_at
            ) VALUES (
                :agent_id,
                :access_hash,
                :refresh_hash,
                :ip,
                :user_agent,
                :expires,
                :refresh_expires
            )
        ");

        $insert->execute([
            'agent_id' => $agent['id'],
            'access_hash' => hash('sha256', $accessToken),
            'refresh_hash' => hash('sha256', $refreshToken),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'expires' => $accessExpiry,
            'refresh_expires' => $refreshExpiry
        ]);

        $update = $this->pdo->prepare("
            UPDATE agents
            SET last_login_at = NOW()
            WHERE id = :id
        ");

        $update->execute(['id' => $agent['id']]);

        unset($agent['password_hash']);

        http_response_code(200);

        echo json_encode([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'agent' => $agent,
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer',
                'expires_in' => 900
            ]
        ]);
    }

    /**
     * POST /api/v1/auth/logout
     * Revoke the current authenticated session.
     */
    public function logout(): void
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
            return;
        }

        $accessToken = trim($matches[1]);
        $tokenHash = hash('sha256', $accessToken);

        $stmt = $this->pdo->prepare("
            UPDATE sessions
            SET revoked_at = NOW()
            WHERE access_token_hash = :token_hash
              AND revoked_at IS NULL
              AND expires_at > NOW()
        ");

        $stmt->execute([
            'token_hash' => $tokenHash
        ]);

        if ($stmt->rowCount() === 0) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid, expired, or already revoked session.'
            ]);
            return;
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Logout successful. Session revoked.'
        ]);
    }


    /**
     * POST /api/v1/auth/refresh
     * Rotate refresh token and issue a new access token.
     */
    public function refresh(array $data): void
    {
        $refreshToken = trim($data['refresh_token'] ?? '');

        if ($refreshToken === '') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Refresh token is required.'
            ]);
            return;
        }

        $oldRefreshHash = hash('sha256', $refreshToken);

        // Generate replacement tokens
        $newAccessToken = bin2hex(random_bytes(32));
        $newRefreshToken = bin2hex(random_bytes(32));

        $newAccessHash = hash('sha256', $newAccessToken);
        $newRefreshHash = hash('sha256', $newRefreshToken);

        $accessExpiry = date(
            'Y-m-d H:i:s',
            time() + 900
        );

        $refreshExpiry = date(
            'Y-m-d H:i:s',
            time() + (30 * 86400)
        );

        // Rotate tokens only if the existing session is valid
        $stmt = $this->pdo->prepare("
            UPDATE sessions
            SET access_token_hash = :new_access_hash,
                refresh_token_hash = :new_refresh_hash,
                expires_at = :access_expiry,
                refresh_expires_at = :refresh_expiry
            WHERE refresh_token_hash = :old_refresh_hash
              AND revoked_at IS NULL
              AND refresh_expires_at > NOW()
        ");

        $stmt->execute([
            'new_access_hash' => $newAccessHash,
            'new_refresh_hash' => $newRefreshHash,
            'access_expiry' => $accessExpiry,
            'refresh_expiry' => $refreshExpiry,
            'old_refresh_hash' => $oldRefreshHash
        ]);

        if ($stmt->rowCount() === 0) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid, expired, or revoked refresh token.'
            ]);
            return;
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Access token refreshed successfully.',
            'data' => [
                'access_token' => $newAccessToken,
                'refresh_token' => $newRefreshToken,
                'token_type' => 'Bearer',
                'expires_in' => 900,
                'refresh_expires_in' => 2592000
            ]
        ]);
    }


    /**
     * POST /api/v1/auth/forgot-password
     * Create a password reset request.
     */
    public function forgotPassword(array $data): void
    {
        $email = strtolower(trim($data['email'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'A valid email address is required.'
            ]);
            return;
        }

        // Use a generic response to prevent account enumeration.
        $response = [
            'success' => true,
            'message' => 'If an account exists for this email, password reset instructions will be provided.'
        ];

        $stmt = $this->pdo->prepare("
            SELECT id
            FROM agents
            WHERE email = :email
            LIMIT 1
        ");

        $stmt->execute(['email' => $email]);
        $agent = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($agent) {
            // Generate a secure, single-use reset token.
            $resetToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $resetToken);

            $expiresAt = date(
                'Y-m-d H:i:s',
                time() + 1800
            );

            // Invalidate previous unused reset requests.
            $invalidate = $this->pdo->prepare("
                UPDATE password_resets
                SET used_at = NOW()
                WHERE agent_id = :agent_id
                  AND used_at IS NULL
            ");

            $invalidate->execute([
                'agent_id' => $agent['id']
            ]);

            // Store only the token hash.
            $insert = $this->pdo->prepare("
                INSERT INTO password_resets (
                    agent_id,
                    token_hash,
                    expires_at
                ) VALUES (
                    :agent_id,
                    :token_hash,
                    :expires_at
                )
            ");

            $insert->execute([
                'agent_id' => $agent['id'],
                'token_hash' => $tokenHash,
                'expires_at' => $expiresAt
            ]);

            // Development testing only. Never expose tokens in production.
            if (getenv('APP_ENV') === 'development') {
                $response['development_reset_token'] = $resetToken;
            }
        }

        http_response_code(200);
        echo json_encode($response);
    }

}
