<?php

class CustomerController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // GET /api/v1/customers
    public function index(array $user): void
    {
        try {
            $isAdmin = ($user['role'] ?? '') === 'admin';
            $agentId = (int) $user['id'];

            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;

            $conditions = [];
            $params = [];

            if (!$isAdmin) {
                $conditions[] = 'assigned_agent_id = :agent_id';
                $params['agent_id'] = $agentId;
            }

            if (!empty($_GET['status'])) {
                $valid = ['active', 'inactive', 'prospect'];

                if (!in_array($_GET['status'], $valid, true)) {
                    http_response_code(422);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Invalid customer status.'
                    ]);
                    return;
                }

                $conditions[] = 'status = :status';
                $params['status'] = $_GET['status'];
            }

            if (!empty($_GET['search'])) {
                $search = '%' . trim($_GET['search']) . '%';

                $conditions[] = '(
                    customer_name LIKE :name
                    OR customer_code LIKE :code
                    OR contact_person LIKE :contact
                    OR email LIKE :email
                    OR phone LIKE :phone
                )';

                $params['name'] = $search;
                $params['code'] = $search;
                $params['contact'] = $search;
                $params['email'] = $search;
                $params['phone'] = $search;
            }

            $where = $conditions
                ? ' WHERE ' . implode(' AND ', $conditions)
                : '';

            $count = $this->pdo->prepare(
                "SELECT COUNT(*) FROM customers $where"
            );
            $count->execute($params);
            $total = (int)$count->fetchColumn();

            $sql = "SELECT
                        id, customer_code, customer_name,
                        contact_person, email, phone,
                        alternative_phone, address, district,
                        region, customer_type, status,
                        assigned_agent_id, notes,
                        created_at, updated_at
                    FROM customers
                    $where
                    ORDER BY created_at DESC, id DESC
                    LIMIT :limit OFFSET :offset";

            $stmt = $this->pdo->prepare($sql);

            foreach ($params as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }

            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Customers retrieved successfully.',
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total_records' => $total,
                    'total_pages' => (int)ceil($total / $limit)
                ]
            ]);

        } catch (Throwable $e) {
            error_log($e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Unable to retrieve customers.'
            ]);
        }
    }

    // POST /api/v1/customers
    public function store(array $data, array $user): void
    {
        $name = trim((string)($data['customer_name'] ?? ''));

        if (strlen($name) < 2 || strlen($name) > 150) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Customer name must be between 2 and 150 characters.'
            ]);
            return;
        }

        $type = $data['customer_type'] ?? 'individual';
        $validTypes = [
            'individual', 'business', 'institution',
            'government', 'other'
        ];

        if (!in_array($type, $validTypes, true)) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid customer type.'
            ]);
            return;
        }

        $status = $data['status'] ?? 'prospect';

        if (!in_array($status, ['active', 'inactive', 'prospect'], true)) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid customer status.'
            ]);
            return;
        }

        $email = isset($data['email']) && trim((string)$data['email']) !== ''
            ? strtolower(trim((string)$data['email']))
            : null;

        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid email address.'
            ]);
            return;
        }

        $textFields = [
            'contact_person' => 150,
            'phone' => 30,
            'alternative_phone' => 30,
            'address' => 255,
            'district' => 100,
            'region' => 100
        ];

        $values = [];

        foreach ($textFields as $field => $max) {
            $value = $data[$field] ?? null;

            if ($value !== null && !is_string($value)) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'message' => "$field must be text or null."
                ]);
                return;
            }

            $value = $value !== null ? trim($value) : null;

            if ($value !== null && strlen($value) > $max) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'message' => "$field exceeds its maximum length."
                ]);
                return;
            }

            $values[$field] = $value === '' ? null : $value;
        }

        $notes = $data['notes'] ?? null;

        if ($notes !== null && !is_string($notes)) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Notes must be text or null.'
            ]);
            return;
        }

        $isAdmin = ($user['role'] ?? '') === 'admin';
        $assignedAgentId = (int)$user['id'];

        if ($isAdmin && array_key_exists('assigned_agent_id', $data)) {
            if ($data['assigned_agent_id'] === null ||
                $data['assigned_agent_id'] === '') {
                $assignedAgentId = null;
            } else {
                $assignedAgentId = filter_var(
                    $data['assigned_agent_id'],
                    FILTER_VALIDATE_INT
                );

                if ($assignedAgentId === false || $assignedAgentId < 1) {
                    http_response_code(422);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Invalid assigned agent ID.'
                    ]);
                    return;
                }
            }
        }

        try {
            if ($assignedAgentId !== null) {
                $checkAgent = $this->pdo->prepare(
                    "SELECT id FROM agents
                     WHERE id = :id AND role = 'agent'
                     AND status = 'active' LIMIT 1"
                );
                $checkAgent->execute(['id' => $assignedAgentId]);

                if (!$checkAgent->fetch()) {
                    http_response_code(422);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Assigned agent not found or inactive.'
                    ]);
                    return;
                }
            }

            $code = 'CUS-' . date('Ymd') . '-' .
                strtoupper(bin2hex(random_bytes(4)));

            $stmt = $this->pdo->prepare(
                "INSERT INTO customers (
                    customer_code, customer_name, contact_person,
                    email, phone, alternative_phone, address,
                    district, region, customer_type, status,
                    assigned_agent_id, notes
                ) VALUES (
                    :code, :name, :contact, :email, :phone,
                    :alternative_phone, :address, :district,
                    :region, :type, :status, :agent_id, :notes
                )"
            );

            $stmt->execute([
                'code' => $code,
                'name' => $name,
                'contact' => $values['contact_person'],
                'email' => $email,
                'phone' => $values['phone'],
                'alternative_phone' => $values['alternative_phone'],
                'address' => $values['address'],
                'district' => $values['district'],
                'region' => $values['region'],
                'type' => $type,
                'status' => $status,
                'agent_id' => $assignedAgentId,
                'notes' => $notes
            ]);

            $id = (int)$this->pdo->lastInsertId();

            $result = $this->pdo->prepare(
                "SELECT id, customer_code, customer_name,
                        contact_person, email, phone,
                        alternative_phone, address, district,
                        region, customer_type, status,
                        assigned_agent_id, notes, created_at, updated_at
                 FROM customers WHERE id = :id"
            );
            $result->execute(['id' => $id]);

            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Customer created successfully.',
                'data' => $result->fetch(PDO::FETCH_ASSOC)
            ]);

        } catch (Throwable $e) {
            error_log($e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Unable to create customer.'
            ]);
        }
    }

    // GET /api/v1/customers/{id}
    public function show(int $id, array $user): void
    {
        $isAdmin = ($user['role'] ?? '') === 'admin';

        $sql = "SELECT id, customer_code, customer_name, contact_person,
                       email, phone, alternative_phone, address, district,
                       region, customer_type, status, assigned_agent_id,
                       notes, created_at, updated_at
                FROM customers
                WHERE id = :id";

        if (!$isAdmin) {
            $sql .= " AND assigned_agent_id = :agent_id";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        if (!$isAdmin) {
            $stmt->bindValue(':agent_id', (int)$user['id'], PDO::PARAM_INT);
        }

        $stmt->execute();
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$customer) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Customer not found.'
            ]);
            return;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Customer retrieved successfully.',
            'data' => $customer
        ]);
    }

    // PATCH /api/v1/customers/{id}
    public function update(int $id, array $data, array $user): void
    {
        $allowed = [
            'customer_name',
            'contact_person',
            'email',
            'phone',
            'alternative_phone',
            'address',
            'district',
            'region',
            'customer_type',
            'status',
            'notes'
        ];

        $updates = [];
        $params = ['id' => $id];

        foreach ($allowed as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $value = $data[$field];

            if ($field === 'customer_name') {
                if (!is_string($value) || strlen(trim($value)) < 2 ||
                    strlen(trim($value)) > 150) {
                    http_response_code(422);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Customer name must be 2–150 characters.'
                    ]);
                    return;
                }
                $value = trim($value);
            }

            if ($field === 'email' && $value !== null && $value !== '') {
                if (!is_string($value) ||
                    !filter_var(trim($value), FILTER_VALIDATE_EMAIL)) {
                    http_response_code(422);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Invalid email address.'
                    ]);
                    return;
                }
                $value = strtolower(trim($value));
            }

            if ($field === 'customer_type' &&
                !in_array($value, [
                    'individual', 'business', 'institution',
                    'government', 'other'
                ], true)) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid customer type.'
                ]);
                return;
            }

            if ($field === 'status' &&
                !in_array($value, ['active', 'inactive', 'prospect'], true)) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid customer status.'
                ]);
                return;
            }

            if ($value !== null && !is_string($value)) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'message' => "$field must be text or null."
                ]);
                return;
            }

            if (is_string($value)) {
                $value = trim($value);
                if ($value === '') {
                    $value = null;
                }
            }

            $updates[] = "$field = :$field";
            $params[$field] = $value;
        }

        if (!$updates) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'No valid customer fields supplied.'
            ]);
            return;
        }

        $isAdmin = ($user['role'] ?? '') === 'admin';

        $sql = "UPDATE customers SET " . implode(', ', $updates) .
               ", updated_at = CURRENT_TIMESTAMP WHERE id = :id";

        if (!$isAdmin) {
            $sql .= " AND assigned_agent_id = :agent_id";
            $params['agent_id'] = (int)$user['id'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        if ($stmt->rowCount() === 0) {
            // Distinguish an unchanged record from a missing/inaccessible one.
            $checkSql = "SELECT id FROM customers WHERE id = :id";
            if (!$isAdmin) {
                $checkSql .= " AND assigned_agent_id = :agent_id";
            }

            $check = $this->pdo->prepare($checkSql);
            $check->bindValue(':id', $id, PDO::PARAM_INT);
            if (!$isAdmin) {
                $check->bindValue(':agent_id', (int)$user['id'], PDO::PARAM_INT);
            }
            $check->execute();

            if (!$check->fetch()) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Customer not found.'
                ]);
                return;
            }
        }

        $this->show($id, $user);
    }

    // DELETE /api/v1/customers/{id} - soft delete by deactivation
    public function destroy(int $id, array $user): void
    {
        $isAdmin = ($user['role'] ?? '') === 'admin';

        $sql = "UPDATE customers
                SET status = 'inactive',
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id";

        if (!$isAdmin) {
            $sql .= " AND assigned_agent_id = :agent_id";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        if (!$isAdmin) {
            $stmt->bindValue(':agent_id', (int)$user['id'], PDO::PARAM_INT);
        }

        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            $checkSql = "SELECT id FROM customers WHERE id = :id";
            if (!$isAdmin) {
                $checkSql .= " AND assigned_agent_id = :agent_id";
            }

            $check = $this->pdo->prepare($checkSql);
            $check->bindValue(':id', $id, PDO::PARAM_INT);
            if (!$isAdmin) {
                $check->bindValue(':agent_id', (int)$user['id'], PDO::PARAM_INT);
            }
            $check->execute();

            if (!$check->fetch()) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Customer not found.'
                ]);
                return;
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Customer deactivated successfully.'
        ]);
    }

}
