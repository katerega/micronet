<?php

class CommissionController
{
    public static function index(PDO $pdo, array $user): void
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $where = [];
        $params = [];

        // Agents can only view their own commissions.
        if (($user['role'] ?? '') !== 'admin') {
            $where[] = 'c.agent_id = :auth_agent_id';
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

            $where[] = 'c.agent_id = :agent_id';
            $params['agent_id'] = $agentId;
        }

        if (!empty($_GET['sale_id'])) {
            $saleId = filter_var(
                $_GET['sale_id'],
                FILTER_VALIDATE_INT
            );

            if (!$saleId || $saleId < 1) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid sale_id.'
                ]);
                return;
            }

            $where[] = 'c.sale_id = :sale_id';
            $params['sale_id'] = $saleId;
        }

        if (!empty($_GET['status'])) {
            $allowedStatuses = [
                'pending',
                'approved',
                'paid',
                'cancelled'
            ];

            if (!in_array($_GET['status'], $allowedStatuses, true)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid commission status.'
                ]);
                return;
            }

            $where[] = 'c.status = :status';
            $params['status'] = $_GET['status'];
        }

        $whereSql = $where
            ? 'WHERE ' . implode(' AND ', $where)
            : '';

        // Count records for pagination.
        $countStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM commissions c
            $whereSql
        ");

        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Retrieve commission records.
        $stmt = $pdo->prepare("
            SELECT
                c.id,
                c.agent_id,
                a.full_name AS agent_name,
                a.email AS agent_email,
                c.sale_id,
                s.sale_number,
                s.customer_id,
                cu.customer_name,
                c.commission_rate,
                c.sale_amount,
                c.commission_amount,
                c.currency,
                c.status,
                c.approved_by,
                approver.full_name AS approved_by_name,
                c.approved_at,
                c.paid_at,
                c.created_at
            FROM commissions c
            LEFT JOIN agents a
                ON a.id = c.agent_id
            LEFT JOIN sales s
                ON s.id = c.sale_id
            LEFT JOIN customers cu
                ON cu.id = s.customer_id
            LEFT JOIN agents approver
                ON approver.id = c.approved_by
            $whereSql
            ORDER BY c.created_at DESC, c.id DESC
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
}
