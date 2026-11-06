<?php

class TargetController
{
    public static function index(PDO $pdo, array $user): void
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $where = [];
        $params = [];

        // Agents can only view their own targets.
        if (($user['role'] ?? '') !== 'admin') {
            $where[] = 't.agent_id = :auth_agent_id';
            $params['auth_agent_id'] = (int)$user['id'];
        } elseif (!empty($_GET['agent_id'])) {
            $agentId = filter_var(
                $_GET['agent_id'],
                FILTER_VALIDATE_INT
            );

            if (!$agentId || $agentId < 1) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid agent_id.'
                ]);
                return;
            }

            $where[] = 't.agent_id = :agent_id';
            $params['agent_id'] = $agentId;
        }

        $filters = [
            'target_type' => [
                'sales_amount',
                'sales_count',
                'visits_count',
                'collections_amount'
            ],
            'period_type' => [
                'daily',
                'weekly',
                'monthly',
                'quarterly',
                'yearly',
                'custom'
            ],
            'status' => [
                'active',
                'completed',
                'cancelled'
            ]
        ];

        foreach ($filters as $field => $allowedValues) {
            if (isset($_GET[$field]) && $_GET[$field] !== '') {
                $value = $_GET[$field];

                if (!in_array($value, $allowedValues, true)) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => "Invalid {$field}."
                    ]);
                    return;
                }

                $where[] = "t.{$field} = :{$field}";
                $params[$field] = $value;
            }
        }

        if (!empty($_GET['period_start'])) {
            $date = DateTime::createFromFormat(
                'Y-m-d',
                $_GET['period_start']
            );

            if (!$date || $date->format('Y-m-d') !== $_GET['period_start']) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'period_start must use YYYY-MM-DD format.'
                ]);
                return;
            }

            $where[] = 't.period_start >= :period_start';
            $params['period_start'] = $_GET['period_start'];
        }

        if (!empty($_GET['period_end'])) {
            $date = DateTime::createFromFormat(
                'Y-m-d',
                $_GET['period_end']
            );

            if (!$date || $date->format('Y-m-d') !== $_GET['period_end']) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'period_end must use YYYY-MM-DD format.'
                ]);
                return;
            }

            $where[] = 't.period_end <= :period_end';
            $params['period_end'] = $_GET['period_end'];
        }

        $whereSql = $where
            ? 'WHERE ' . implode(' AND ', $where)
            : '';

        $countStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM targets t
            $whereSql
        ");

        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT
                t.id,
                t.agent_id,
                a.full_name AS agent_name,
                a.email AS agent_email,
                t.target_type,
                t.target_value,
                t.currency,
                t.period_type,
                t.period_start,
                t.period_end,
                t.status,
                t.created_by,
                creator.full_name AS created_by_name,
                t.created_at,
                t.updated_at
            FROM targets t
            LEFT JOIN agents a
                ON a.id = t.agent_id
            LEFT JOIN agents creator
                ON creator.id = t.created_by
            $whereSql
            ORDER BY t.period_start DESC, t.id DESC
            LIMIT :limit OFFSET :offset
        ");

        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        echo json_encode([
            'success' => true,
            'data' => $stmt->fetchAll(),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int)ceil($total / $limit)
            ]
        ]);
    }

    public static function create(
        PDO $pdo,
        array $user,
        array $data
    ): void {
        $agentId = filter_var(
            $data['agent_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$agentId || $agentId < 1) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'A valid agent_id is required.'
            ]);
            return;
        }

        $targetTypes = [
            'sales_amount',
            'sales_count',
            'visits_count',
            'collections_amount'
        ];

        $targetType = $data['target_type'] ?? '';

        if (!in_array($targetType, $targetTypes, true)) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid target_type.'
            ]);
            return;
        }

        $periodTypes = [
            'daily',
            'weekly',
            'monthly',
            'quarterly',
            'yearly',
            'custom'
        ];

        $periodType = $data['period_type'] ?? 'monthly';

        if (!in_array($periodType, $periodTypes, true)) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid period_type.'
            ]);
            return;
        }

        $targetValue = $data['target_value'] ?? null;

        if (
            !is_numeric($targetValue) ||
            !is_finite((float)$targetValue) ||
            (float)$targetValue < 0
        ) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'target_value must be a non-negative number.'
            ]);
            return;
        }

        $targetValue = number_format(
            (float)$targetValue,
            2,
            '.',
            ''
        );

        $currency = strtoupper(trim($data['currency'] ?? 'UGX'));

        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Currency must be a three-letter code.'
            ]);
            return;
        }

        $periodStart = $data['period_start'] ?? '';
        $periodEnd = $data['period_end'] ?? '';

        $startDate = DateTime::createFromFormat('!Y-m-d', $periodStart);
        $endDate = DateTime::createFromFormat('!Y-m-d', $periodEnd);

        if (
            !$startDate ||
            !$endDate ||
            $startDate->format('Y-m-d') !== $periodStart ||
            $endDate->format('Y-m-d') !== $periodEnd
        ) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Both period_start and period_end must use YYYY-MM-DD format.'
            ]);
            return;
        }

        if ($periodEnd < $periodStart) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'period_end cannot be earlier than period_start.'
            ]);
            return;
        }

        $status = $data['status'] ?? 'active';

        if (!in_array($status, ['active', 'completed', 'cancelled'], true)) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid target status.'
            ]);
            return;
        }

        try {
            // Confirm the assigned agent exists and is active.
            $agentStmt = $pdo->prepare("
                SELECT id, full_name
                FROM agents
                WHERE id = :agent_id
                  AND status = 'active'
                LIMIT 1
            ");

            $agentStmt->execute([
                'agent_id' => $agentId
            ]);

            $agent = $agentStmt->fetch();

            if (!$agent) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'message' => 'The selected agent does not exist or is inactive.'
                ]);
                return;
            }

            $stmt = $pdo->prepare("
                INSERT INTO targets (
                    agent_id,
                    target_type,
                    target_value,
                    currency,
                    period_type,
                    period_start,
                    period_end,
                    status,
                    created_by
                ) VALUES (
                    :agent_id,
                    :target_type,
                    :target_value,
                    :currency,
                    :period_type,
                    :period_start,
                    :period_end,
                    :status,
                    :created_by
                )
            ");

            $stmt->execute([
                'agent_id' => $agentId,
                'target_type' => $targetType,
                'target_value' => $targetValue,
                'currency' => $currency,
                'period_type' => $periodType,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'status' => $status,
                'created_by' => (int)$user['id']
            ]);

            $targetId = (int)$pdo->lastInsertId();

            http_response_code(201);

            echo json_encode([
                'success' => true,
                'message' => 'Target created successfully.',
                'data' => [
                    'id' => $targetId,
                    'agent_id' => $agentId,
                    'agent_name' => $agent['full_name'],
                    'target_type' => $targetType,
                    'target_value' => $targetValue,
                    'currency' => $currency,
                    'period_type' => $periodType,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'status' => $status,
                    'created_by' => (int)$user['id']
                ]
            ]);

        } catch (PDOException $e) {
            error_log($e->getMessage());

            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Could not create target. Database operation failed.'
            ]);
        }
    }
}
