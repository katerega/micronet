<?php

class AgentController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * POST /api/v1/admin/agents
     * Create a new sales agent.
     */
    public function create(array $data, int $adminId): void
    {
        $fullName = trim($data['full_name'] ?? '');
        $email = strtolower(trim($data['email'] ?? ''));
        $phone = trim($data['phone'] ?? '');
        $password = $data['password'] ?? '';

        if (
            $fullName === '' ||
            $email === '' ||
            $phone === '' ||
            $password === ''
        ) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Full name, email, phone, and password are required.'
            ]);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Please provide a valid email address.'
            ]);
            return;
        }

        if (strlen($fullName) < 2 || strlen($fullName) > 150) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Full name must be between 2 and 150 characters.'
            ]);
            return;
        }

        if (strlen($phone) < 5 || strlen($phone) > 30) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Please provide a valid phone number.'
            ]);
            return;
        }

        if (strlen($password) < 12) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Password must contain at least 12 characters.'
            ]);
            return;
        }

        // Check for an existing email or phone number.
        $check = $this->pdo->prepare("
            SELECT id
            FROM agents
            WHERE email = :email OR phone = :phone
            LIMIT 1
        ");

        $check->execute([
            'email' => $email,
            'phone' => $phone
        ]);

        if ($check->fetch()) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => 'An agent with this email or phone number already exists.'
            ]);
            return;
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO agents (
                    full_name,
                    email,
                    phone,
                    password_hash,
                    role,
                    status,
                    created_by
                ) VALUES (
                    :full_name,
                    :email,
                    :phone,
                    :password_hash,
                    'agent',
                    'active',
                    :created_by
                )
            ");

            $stmt->execute([
                'full_name' => $fullName,
                'email' => $email,
                'phone' => $phone,
                'password_hash' => $passwordHash,
                'created_by' => $adminId
            ]);

            $agentId = (int) $this->pdo->lastInsertId();

            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Agent created successfully.',
                'data' => [
                    'id' => $agentId,
                    'full_name' => $fullName,
                    'email' => $email,
                    'phone' => $phone,
                    'role' => 'agent',
                    'status' => 'active'
                ]
            ]);

        } catch (PDOException $e) {
            error_log('Agent creation failed: ' . $e->getMessage());

            if ($e->getCode() === '23000') {
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'message' => 'An agent with this email or phone number already exists.'
                ]);
                return;
            }

            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Unable to create agent at this time.'
            ]);
        }
    }

    /**
     * GET /api/v1/admin/agents
     * List agents with pagination, search and status filtering.
     */
    public function index(): void
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = (int) ($_GET['limit'] ?? 20);
        $limit = max(1, min($limit, 100));
        $offset = ($page - 1) * $limit;

        $search = trim($_GET['search'] ?? '');
        $status = trim($_GET['status'] ?? '');

        $allowedStatuses = ['active', 'inactive', 'suspended'];

        if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid status filter.'
            ]);
            return;
        }

        $conditions = ["role = 'agent'"];
        $params = [];

        if ($search !== '') {
            $conditions[] = "(
                full_name LIKE :search_name
                OR email LIKE :search_email
                OR phone LIKE :search_phone
            )";

            $searchValue = '%' . $search . '%';

            $params['search_name'] = $searchValue;
            $params['search_email'] = $searchValue;
            $params['search_phone'] = $searchValue;
        }

        if ($status !== '') {
            $conditions[] = "status = :status";
            $params['status'] = $status;
        }

        $where = implode(' AND ', $conditions);

        try {
            // Get total matching agents.
            $countStmt = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM agents
                WHERE $where
            ");

            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            // Retrieve paginated agent records.
            $stmt = $this->pdo->prepare("
                SELECT
                    id,
                    full_name,
                    email,
                    phone,
                    role,
                    status,
                    profile_photo,
                    address,
                    created_by,
                    last_login_at,
                    created_at,
                    updated_at
                FROM agents
                WHERE $where
                ORDER BY created_at DESC, id DESC
                LIMIT :limit OFFSET :offset
            ");

            foreach ($params as $key => $value) {
                $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
            }

            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();

            $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);

            http_response_code(200);

            echo json_encode([
                'success' => true,
                'message' => 'Agents retrieved successfully.',
                'data' => $agents,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total_records' => $total,
                    'total_pages' => (int) ceil($total / $limit)
                ]
            ]);

        } catch (PDOException $e) {
            error_log('Agent listing failed: ' . $e->getMessage());

            http_response_code(500);

            echo json_encode([
                'success' => false,
                'message' => 'Unable to retrieve agents.'
            ]);
        }
    }


    public function update(int $id, array $data): void
    {
        if ($id < 1) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid agent ID.'
            ]);
            return;
        }

        $allowed = [
            'full_name',
            'email',
            'phone',
            'status',
            'address',
            'profile_photo'
        ];

        $updates = [];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $updates[$field] = $data[$field];
            }
        }

        if (empty($updates)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'No valid fields supplied for update.'
            ]);
            return;
        }

        if (isset($updates['full_name'])) {
            $name = trim((string) $updates['full_name']);

            if (strlen($name) < 2 || strlen($name) > 150) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'message' => 'Full name must be between 2 and 150 characters.'
                ]);
                return;
            }

            $updates['full_name'] = $name;
        }

        if (isset($updates['email'])) {
            $email = trim((string) $updates['email']);

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'message' => 'A valid email address is required.'
                ]);
                return;
            }

            $updates['email'] = strtolower($email);
        }

        if (isset($updates['phone'])) {
            $phone = trim((string) $updates['phone']);

            if (strlen($phone) < 5 || strlen($phone) > 30) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'message' => 'Phone number must be between 5 and 30 characters.'
                ]);
                return;
            }

            $updates['phone'] = $phone;
        }

        if (isset($updates['status'])) {
            $validStatuses = ['active', 'inactive', 'suspended'];

            if (!in_array($updates['status'], $validStatuses, true)) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'message' => 'Status must be active, inactive, or suspended.'
                ]);
                return;
            }
        }

        foreach (['address', 'profile_photo'] as $field) {
            if (array_key_exists($field, $updates) &&
                $updates[$field] !== null &&
                !is_string($updates[$field])) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'message' => ucfirst(str_replace('_', ' ', $field)) . ' must be a string or null.'
                ]);
                return;
            }
        }

        $check = $this->pdo->prepare(
            "SELECT id FROM agents WHERE id = :id AND role = 'agent' LIMIT 1"
        );
        $check->execute(['id' => $id]);

        if (!$check->fetch()) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Agent not found.'
            ]);
            return;
        }

        foreach (['email', 'phone'] as $field) {
            if (isset($updates[$field])) {
                $duplicate = $this->pdo->prepare(
                    "SELECT id FROM agents
                     WHERE {$field} = :value AND id <> :id
                     LIMIT 1"
                );

                $duplicate->execute([
                    'value' => $updates[$field],
                    'id' => $id
                ]);

                if ($duplicate->fetch()) {
                    http_response_code(409);
                    echo json_encode([
                        'success' => false,
                        'message' => ucfirst($field) . ' is already in use.'
                    ]);
                    return;
                }
            }
        }

        $setParts = [];
        $params = ['id' => $id];

        foreach ($updates as $field => $value) {
            $setParts[] = "{$field} = :{$field}";
            $params[$field] = $value;
        }

        $setParts[] = "updated_at = CURRENT_TIMESTAMP";

        $sql = "UPDATE agents SET " . implode(', ', $setParts) .
               " WHERE id = :id AND role = 'agent'";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $result = $this->pdo->prepare(
            "SELECT id, full_name, email, phone, role, status,
                    profile_photo, address, created_by,
                    last_login_at, created_at, updated_at
             FROM agents
             WHERE id = :id AND role = 'agent'
             LIMIT 1"
        );
        $result->execute(['id' => $id]);

        $agent = $result->fetch();

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Agent updated successfully.',
            'data' => $agent
        ]);
    }


    public function deactivate(int $id): void
    {
        if ($id < 1) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid agent ID.'
            ]);
            return;
        }

        $check = $this->pdo->prepare(
            "SELECT id, full_name, email, status
             FROM agents
             WHERE id = :id AND role = 'agent'
             LIMIT 1"
        );

        $check->execute(['id' => $id]);
        $agent = $check->fetch();

        if (!$agent) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Agent not found.'
            ]);
            return;
        }

        if ($agent['status'] === 'inactive') {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Agent is already inactive.',
                'data' => [
                    'id' => (int) $agent['id'],
                    'full_name' => $agent['full_name'],
                    'status' => 'inactive'
                ]
            ]);
            return;
        }

        try {
            $this->pdo->beginTransaction();

            $update = $this->pdo->prepare(
                "UPDATE agents
                 SET status = 'inactive',
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND role = 'agent'"
            );

            $update->execute(['id' => $id]);

            $revoke = $this->pdo->prepare(
                "UPDATE sessions
                 SET revoked_at = CURRENT_TIMESTAMP
                 WHERE agent_id = :id
                   AND revoked_at IS NULL"
            );

            $revoke->execute(['id' => $id]);

            $this->pdo->commit();

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Agent deactivated successfully. All active sessions have been revoked.',
                'data' => [
                    'id' => (int) $agent['id'],
                    'full_name' => $agent['full_name'],
                    'status' => 'inactive',
                    'sessions_revoked' => $revoke->rowCount()
                ]
            ]);

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            error_log($e->getMessage());

            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Unable to deactivate agent.'
            ]);
        }
    }

}
