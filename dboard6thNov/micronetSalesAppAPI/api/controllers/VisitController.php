<?php

class VisitController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // GET /api/v1/visits
    public function index(array $user): void
    {
        try {
            $isAdmin = ($user['role'] ?? '') === 'admin';

            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;

            $conditions = [];
            $params = [];

            if (!$isAdmin) {
                $conditions[] = 'v.agent_id = :agent_id';
                $params['agent_id'] = (int)$user['id'];
            }

            if (isset($_GET['customer_id']) && $_GET['customer_id'] !== '') {
                $customerId = filter_var(
                    $_GET['customer_id'],
                    FILTER_VALIDATE_INT
                );

                if ($customerId === false || $customerId < 1) {
                    http_response_code(422);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Invalid customer ID.'
                    ]);
                    return;
                }

                $conditions[] = 'v.customer_id = :customer_id';
                $params['customer_id'] = $customerId;
            }

            if (!empty($_GET['status'])) {
                $validStatuses = [
                    'scheduled', 'in_progress', 'completed', 'cancelled'
                ];

                if (!in_array($_GET['status'], $validStatuses, true)) {
                    http_response_code(422);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Invalid visit status.'
                    ]);
                    return;
                }

                $conditions[] = 'v.status = :status';
                $params['status'] = $_GET['status'];
            }

            if (!empty($_GET['visit_type'])) {
                $validTypes = [
                    'sales', 'followup', 'collection', 'service', 'other'
                ];

                if (!in_array($_GET['visit_type'], $validTypes, true)) {
                    http_response_code(422);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Invalid visit type.'
                    ]);
                    return;
                }

                $conditions[] = 'v.visit_type = :visit_type';
                $params['visit_type'] = $_GET['visit_type'];
            }

            if (!empty($_GET['date'])) {
                $date = DateTime::createFromFormat('!Y-m-d', $_GET['date']);

                if (!$date || $date->format('Y-m-d') !== $_GET['date']) {
                    http_response_code(422);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Date must use YYYY-MM-DD format.'
                    ]);
                    return;
                }

                $conditions[] = 'DATE(v.created_at) = :visit_date';
                $params['visit_date'] = $_GET['date'];
            }

            $where = $conditions
                ? ' WHERE ' . implode(' AND ', $conditions)
                : '';

            $count = $this->pdo->prepare(
                "SELECT COUNT(*) FROM visits v $where"
            );
            $count->execute($params);
            $total = (int)$count->fetchColumn();

            $sql = "SELECT
                        v.id,
                        v.agent_id,
                        a.full_name AS agent_name,
                        v.customer_id,
                        c.customer_code,
                        c.customer_name,
                        v.visit_type,
                        v.purpose,
                        v.visit_notes,
                        v.check_in_time,
                        v.check_out_time,
                        v.check_in_latitude,
                        v.check_in_longitude,
                        v.check_out_latitude,
                        v.check_out_longitude,
                        v.status,
                        v.created_at
                    FROM visits v
                    LEFT JOIN agents a ON a.id = v.agent_id
                    LEFT JOIN customers c ON c.id = v.customer_id
                    $where
                    ORDER BY v.created_at DESC, v.id DESC
                    LIMIT :limit OFFSET :offset";

            $stmt = $this->pdo->prepare($sql);

            foreach ($params as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }

            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            echo json_encode([
                'success' => true,
                'message' => 'Visits retrieved successfully.',
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
                'message' => 'Unable to retrieve visits.'
            ]);
        }
    }

    // POST /api/v1/visits
    public function store(array $data, array $user): void
    {
        $customerId = filter_var(
            $data['customer_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if ($customerId === false || $customerId === null || $customerId < 1) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'A valid customer_id is required.'
            ]);
            return;
        }

        $visitType = $data['visit_type'] ?? 'sales';
        $validTypes = [
            'sales', 'followup', 'collection', 'service', 'other'
        ];

        if (!in_array($visitType, $validTypes, true)) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid visit type.'
            ]);
            return;
        }

        $purpose = $data['purpose'] ?? null;
        $visitNotes = $data['visit_notes'] ?? null;

        foreach (['purpose' => $purpose, 'visit_notes' => $visitNotes] as $field => $value) {
            if ($value !== null && !is_string($value)) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'message' => "$field must be text or null."
                ]);
                return;
            }
        }

        $coordinates = [
            'check_in_latitude' => [-90, 90],
            'check_in_longitude' => [-180, 180],
            'check_out_latitude' => [-90, 90],
            'check_out_longitude' => [-180, 180]
        ];

        $gps = [];

        foreach ($coordinates as $field => [$minimum, $maximum]) {
            $value = $data[$field] ?? null;

            if ($value === null || $value === '') {
                $gps[$field] = null;
                continue;
            }

            if (!is_numeric($value) ||
                (float)$value < $minimum ||
                (float)$value > $maximum) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'message' => "Invalid GPS coordinate: $field."
                ]);
                return;
            }

            $gps[$field] = (float)$value;
        }

        try {
            $isAdmin = ($user['role'] ?? '') === 'admin';

            // Agents may only create visits for customers assigned to them.
            $customerSql = "SELECT id FROM customers WHERE id = :customer_id";

            if (!$isAdmin) {
                $customerSql .= " AND assigned_agent_id = :agent_id";
            }

            $customerCheck = $this->pdo->prepare($customerSql);
            $customerCheck->bindValue(':customer_id', $customerId, PDO::PARAM_INT);

            if (!$isAdmin) {
                $customerCheck->bindValue(
                    ':agent_id',
                    (int)$user['id'],
                    PDO::PARAM_INT
                );
            }

            $customerCheck->execute();

            if (!$customerCheck->fetch()) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Customer not found or not assigned to this agent.'
                ]);
                return;
            }

            // Admins may optionally create a visit on behalf of another active agent.
            $agentId = (int)$user['id'];

            if ($isAdmin && isset($data['agent_id'])) {
                $requestedAgentId = filter_var(
                    $data['agent_id'],
                    FILTER_VALIDATE_INT
                );

                if ($requestedAgentId === false || $requestedAgentId < 1) {
                    http_response_code(422);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Invalid agent ID.'
                    ]);
                    return;
                }

                $agentCheck = $this->pdo->prepare(
                    "SELECT id FROM agents
                     WHERE id = :id AND status = 'active'
                     LIMIT 1"
                );
                $agentCheck->execute(['id' => $requestedAgentId]);

                if (!$agentCheck->fetch()) {
                    http_response_code(422);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Agent not found or inactive.'
                    ]);
                    return;
                }

                $agentId = (int)$requestedAgentId;
            }

            $stmt = $this->pdo->prepare(
                "INSERT INTO visits (
                    agent_id,
                    customer_id,
                    visit_type,
                    purpose,
                    visit_notes,
                    check_in_latitude,
                    check_in_longitude,
                    check_out_latitude,
                    check_out_longitude,
                    status
                ) VALUES (
                    :agent_id,
                    :customer_id,
                    :visit_type,
                    :purpose,
                    :visit_notes,
                    :check_in_latitude,
                    :check_in_longitude,
                    :check_out_latitude,
                    :check_out_longitude,
                    'scheduled'
                )"
            );

            $stmt->execute([
                'agent_id' => $agentId,
                'customer_id' => $customerId,
                'visit_type' => $visitType,
                'purpose' => $purpose,
                'visit_notes' => $visitNotes,
                'check_in_latitude' => $gps['check_in_latitude'],
                'check_in_longitude' => $gps['check_in_longitude'],
                'check_out_latitude' => $gps['check_out_latitude'],
                'check_out_longitude' => $gps['check_out_longitude']
            ]);

            $visitId = (int)$this->pdo->lastInsertId();

            $result = $this->pdo->prepare(
                "SELECT v.*, c.customer_name, a.full_name AS agent_name
                 FROM visits v
                 LEFT JOIN customers c ON c.id = v.customer_id
                 LEFT JOIN agents a ON a.id = v.agent_id
                 WHERE v.id = :id"
            );
            $result->execute(['id' => $visitId]);

            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Visit created successfully.',
                'data' => $result->fetch(PDO::FETCH_ASSOC)
            ]);

        } catch (Throwable $e) {
            error_log($e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Unable to create visit.'
            ]);
        }
    }
}
