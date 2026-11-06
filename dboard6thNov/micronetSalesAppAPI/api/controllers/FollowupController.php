<?php

class FollowupController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // GET /api/v1/followups
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
                $conditions[] = 'f.agent_id = :agent_id';
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

                $conditions[] = 'f.customer_id = :customer_id';
                $params['customer_id'] = $customerId;
            }

            if (!empty($_GET['status'])) {
                $validStatuses = [
                    'pending', 'completed', 'cancelled', 'overdue'
                ];

                if (!in_array($_GET['status'], $validStatuses, true)) {
                    http_response_code(422);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Invalid follow-up status.'
                    ]);
                    return;
                }

                $conditions[] = 'f.status = :status';
                $params['status'] = $_GET['status'];
            }

            if (!empty($_GET['priority'])) {
                $validPriorities = [
                    'low', 'medium', 'high', 'urgent'
                ];

                if (!in_array($_GET['priority'], $validPriorities, true)) {
                    http_response_code(422);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Invalid follow-up priority.'
                    ]);
                    return;
                }

                $conditions[] = 'f.priority = :priority';
                $params['priority'] = $_GET['priority'];
            }

            if (!empty($_GET['date_from'])) {
                $date = DateTime::createFromFormat('!Y-m-d', $_GET['date_from']);

                if (!$date || $date->format('Y-m-d') !== $_GET['date_from']) {
                    http_response_code(422);
                    echo json_encode([
                        'success' => false,
                        'message' => 'date_from must use YYYY-MM-DD format.'
                    ]);
                    return;
                }

                $conditions[] = 'f.followup_date >= :date_from';
                $params['date_from'] = $_GET['date_from'];
            }

            if (!empty($_GET['date_to'])) {
                $date = DateTime::createFromFormat('!Y-m-d', $_GET['date_to']);

                if (!$date || $date->format('Y-m-d') !== $_GET['date_to']) {
                    http_response_code(422);
                    echo json_encode([
                        'success' => false,
                        'message' => 'date_to must use YYYY-MM-DD format.'
                    ]);
                    return;
                }

                $conditions[] = 'f.followup_date <= :date_to';
                $params['date_to'] = $_GET['date_to'];
            }

            $where = $conditions
                ? ' WHERE ' . implode(' AND ', $conditions)
                : '';

            $count = $this->pdo->prepare(
                "SELECT COUNT(*) FROM followups f $where"
            );
            $count->execute($params);
            $total = (int)$count->fetchColumn();

            $sql = "SELECT
                        f.id,
                        f.agent_id,
                        a.full_name AS agent_name,
                        f.customer_id,
                        c.customer_code,
                        c.customer_name,
                        f.sale_id,
                        s.sale_number,
                        f.invoice_id,
                        i.invoice_number,
                        f.title,
                        f.description,
                        f.followup_date,
                        f.priority,
                        f.status,
                        f.completed_at,
                        f.outcome,
                        f.created_at,
                        f.updated_at
                    FROM followups f
                    LEFT JOIN agents a ON a.id = f.agent_id
                    LEFT JOIN customers c ON c.id = f.customer_id
                    LEFT JOIN sales s ON s.id = f.sale_id
                    LEFT JOIN invoices i ON i.id = f.invoice_id
                    $where
                    ORDER BY f.followup_date ASC, f.id DESC
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
                'message' => 'Follow-ups retrieved successfully.',
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
                'message' => 'Unable to retrieve follow-ups.'
            ]);
        }
    }

    // POST /api/v1/followups
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

        $title = trim((string)($data['title'] ?? ''));

        if (strlen($title) < 2 || strlen($title) > 255) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Title must be between 2 and 255 characters.'
            ]);
            return;
        }

        $description = $data['description'] ?? null;

        if ($description !== null && !is_string($description)) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Description must be text or null.'
            ]);
            return;
        }

        $followupDate = $data['followup_date'] ?? null;

        if (!is_string($followupDate)) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'A followup_date is required in YYYY-MM-DD format.'
            ]);
            return;
        }

        $date = DateTime::createFromFormat('!Y-m-d', $followupDate);

        if (!$date || $date->format('Y-m-d') !== $followupDate) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'followup_date must use YYYY-MM-DD format.'
            ]);
            return;
        }

        $priority = $data['priority'] ?? 'medium';
        $validPriorities = ['low', 'medium', 'high', 'urgent'];

        if (!in_array($priority, $validPriorities, true)) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid follow-up priority.'
            ]);
            return;
        }

        $saleId = null;
        if (isset($data['sale_id']) && $data['sale_id'] !== '') {
            $saleId = filter_var($data['sale_id'], FILTER_VALIDATE_INT);

            if ($saleId === false || $saleId < 1) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid sale ID.'
                ]);
                return;
            }
        }

        $invoiceId = null;
        if (isset($data['invoice_id']) && $data['invoice_id'] !== '') {
            $invoiceId = filter_var($data['invoice_id'], FILTER_VALIDATE_INT);

            if ($invoiceId === false || $invoiceId < 1) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid invoice ID.'
                ]);
                return;
            }
        }

        if ($saleId !== null && $invoiceId !== null) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Link a follow-up to a sale or an invoice, not both.'
            ]);
            return;
        }

        try {
            $isAdmin = ($user['role'] ?? '') === 'admin';
            $agentId = (int)$user['id'];

            // Admins can optionally assign the follow-up to an active agent.
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

            // Verify customer access.
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

            // Verify an optional sale belongs to this customer and agent.
            if ($saleId !== null) {
                $saleCheck = $this->pdo->prepare(
                    "SELECT id FROM sales
                     WHERE id = :sale_id
                     AND customer_id = :customer_id
                     AND agent_id = :agent_id
                     LIMIT 1"
                );

                $saleCheck->execute([
                    'sale_id' => $saleId,
                    'customer_id' => $customerId,
                    'agent_id' => $agentId
                ]);

                if (!$saleCheck->fetch()) {
                    http_response_code(422);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Sale not found for this customer and agent.'
                    ]);
                    return;
                }
            }

            // Verify an optional invoice belongs to this customer and agent.
            if ($invoiceId !== null) {
                $invoiceCheck = $this->pdo->prepare(
                    "SELECT id FROM invoices
                     WHERE id = :invoice_id
                     AND customer_id = :customer_id
                     AND agent_id = :agent_id
                     LIMIT 1"
                );

                $invoiceCheck->execute([
                    'invoice_id' => $invoiceId,
                    'customer_id' => $customerId,
                    'agent_id' => $agentId
                ]);

                if (!$invoiceCheck->fetch()) {
                    http_response_code(422);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Invoice not found for this customer and agent.'
                    ]);
                    return;
                }
            }

            $stmt = $this->pdo->prepare(
                "INSERT INTO followups (
                    agent_id,
                    customer_id,
                    sale_id,
                    invoice_id,
                    title,
                    description,
                    followup_date,
                    priority,
                    status
                ) VALUES (
                    :agent_id,
                    :customer_id,
                    :sale_id,
                    :invoice_id,
                    :title,
                    :description,
                    :followup_date,
                    :priority,
                    'pending'
                )"
            );

            $stmt->execute([
                'agent_id' => $agentId,
                'customer_id' => $customerId,
                'sale_id' => $saleId,
                'invoice_id' => $invoiceId,
                'title' => $title,
                'description' => $description,
                'followup_date' => $followupDate,
                'priority' => $priority
            ]);

            $followupId = (int)$this->pdo->lastInsertId();

            $result = $this->pdo->prepare(
                "SELECT f.*,
                        c.customer_name,
                        a.full_name AS agent_name
                 FROM followups f
                 LEFT JOIN customers c ON c.id = f.customer_id
                 LEFT JOIN agents a ON a.id = f.agent_id
                 WHERE f.id = :id"
            );

            $result->execute(['id' => $followupId]);

            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Follow-up created successfully.',
                'data' => $result->fetch(PDO::FETCH_ASSOC)
            ]);

        } catch (Throwable $e) {
            error_log($e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Unable to create follow-up.'
            ]);
        }
    }
}
