<?php

class SalesController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // GET /api/v1/sales
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
                $conditions[] = 's.agent_id = :agent_id';
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

                $conditions[] = 's.customer_id = :customer_id';
                $params['customer_id'] = $customerId;
            }

            if (!empty($_GET['payment_status'])) {
                $valid = ['unpaid', 'partial', 'paid', 'refunded'];

                if (!in_array($_GET['payment_status'], $valid, true)) {
                    http_response_code(422);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Invalid payment status.'
                    ]);
                    return;
                }

                $conditions[] = 's.payment_status = :payment_status';
                $params['payment_status'] = $_GET['payment_status'];
            }

            if (!empty($_GET['sale_status'])) {
                $valid = ['draft', 'confirmed', 'cancelled', 'completed'];

                if (!in_array($_GET['sale_status'], $valid, true)) {
                    http_response_code(422);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Invalid sale status.'
                    ]);
                    return;
                }

                $conditions[] = 's.sale_status = :sale_status';
                $params['sale_status'] = $_GET['sale_status'];
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

                $conditions[] = 's.sale_date >= :date_from';
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

                $conditions[] = 's.sale_date <= :date_to';
                $params['date_to'] = $_GET['date_to'];
            }

            $where = $conditions
                ? ' WHERE ' . implode(' AND ', $conditions)
                : '';

            $count = $this->pdo->prepare(
                "SELECT COUNT(*) FROM sales s $where"
            );
            $count->execute($params);
            $total = (int)$count->fetchColumn();

            $sql = "SELECT
                        s.id,
                        s.sale_number,
                        s.agent_id,
                        a.full_name AS agent_name,
                        s.customer_id,
                        c.customer_code,
                        c.customer_name,
                        s.visit_id,
                        s.sale_date,
                        s.subtotal,
                        s.discount,
                        s.tax_amount,
                        s.total_amount,
                        s.amount_paid,
                        s.balance,
                        s.payment_status,
                        s.sale_status,
                        s.notes,
                        s.created_at,
                        s.updated_at
                    FROM sales s
                    LEFT JOIN agents a ON a.id = s.agent_id
                    LEFT JOIN customers c ON c.id = s.customer_id
                    $where
                    ORDER BY s.sale_date DESC, s.id DESC
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
                'message' => 'Sales retrieved successfully.',
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
                'message' => 'Unable to retrieve sales.'
            ]);
        }
    }

    // POST /api/v1/sales
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

        // Validate monetary values.
        $moneyFields = ['subtotal', 'discount', 'tax_amount', 'amount_paid'];
        $amounts = [];

        foreach ($moneyFields as $field) {
            $value = $data[$field] ?? 0;

            if (!is_numeric($value) || !is_finite((float)$value) ||
                (float)$value < 0) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'message' => "$field must be a non-negative number."
                ]);
                return;
            }

            $amounts[$field] = round((float)$value, 2);
        }

        $subtotal = $amounts['subtotal'];
        $discount = $amounts['discount'];
        $taxAmount = $amounts['tax_amount'];
        $amountPaid = $amounts['amount_paid'];

        if ($discount > $subtotal) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Discount cannot exceed subtotal.'
            ]);
            return;
        }

        $totalAmount = round($subtotal - $discount + $taxAmount, 2);

        if ($amountPaid > $totalAmount) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Amount paid cannot exceed the sale total.'
            ]);
            return;
        }

        $balance = round($totalAmount - $amountPaid, 2);

        if ($totalAmount > 0 && $amountPaid >= $totalAmount) {
            $paymentStatus = 'paid';
        } elseif ($amountPaid > 0) {
            $paymentStatus = 'partial';
        } else {
            $paymentStatus = 'unpaid';
        }

        $saleStatus = $data['sale_status'] ?? 'confirmed';

        if (!in_array(
            $saleStatus,
            ['draft', 'confirmed', 'cancelled', 'completed'],
            true
        )) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid sale status.'
            ]);
            return;
        }

        $saleDate = $data['sale_date'] ?? date('Y-m-d');

        $date = is_string($saleDate)
            ? DateTime::createFromFormat('!Y-m-d', $saleDate)
            : false;

        if (!$date || $date->format('Y-m-d') !== $saleDate) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'sale_date must use YYYY-MM-DD format.'
            ]);
            return;
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

        $visitId = null;

        if (isset($data['visit_id']) && $data['visit_id'] !== '') {
            $visitId = filter_var($data['visit_id'], FILTER_VALIDATE_INT);

            if ($visitId === false || $visitId < 1) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid visit ID.'
                ]);
                return;
            }
        }

        try {
            $isAdmin = ($user['role'] ?? '') === 'admin';
            $agentId = (int)$user['id'];

            // Admins can optionally create sales for another active agent.
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

            // Customer must exist and be accessible to the current user.
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

            // If a visit is supplied, it must belong to this agent/customer.
            if ($visitId !== null) {
                $visitSql = "SELECT id FROM visits
                             WHERE id = :visit_id
                             AND customer_id = :customer_id
                             AND agent_id = :agent_id";

                $visitCheck = $this->pdo->prepare($visitSql);
                $visitCheck->execute([
                    'visit_id' => $visitId,
                    'customer_id' => $customerId,
                    'agent_id' => $agentId
                ]);

                if (!$visitCheck->fetch()) {
                    http_response_code(422);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Visit not found for this customer and agent.'
                    ]);
                    return;
                }
            }

            $saleNumber = 'SAL-' . date('Ymd') . '-' .
                strtoupper(bin2hex(random_bytes(4)));

            $stmt = $this->pdo->prepare(
                "INSERT INTO sales (
                    sale_number,
                    agent_id,
                    customer_id,
                    visit_id,
                    sale_date,
                    subtotal,
                    discount,
                    tax_amount,
                    total_amount,
                    amount_paid,
                    balance,
                    payment_status,
                    sale_status,
                    notes
                ) VALUES (
                    :sale_number,
                    :agent_id,
                    :customer_id,
                    :visit_id,
                    :sale_date,
                    :subtotal,
                    :discount,
                    :tax_amount,
                    :total_amount,
                    :amount_paid,
                    :balance,
                    :payment_status,
                    :sale_status,
                    :notes
                )"
            );

            $stmt->execute([
                'sale_number' => $saleNumber,
                'agent_id' => $agentId,
                'customer_id' => $customerId,
                'visit_id' => $visitId,
                'sale_date' => $saleDate,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'amount_paid' => $amountPaid,
                'balance' => $balance,
                'payment_status' => $paymentStatus,
                'sale_status' => $saleStatus,
                'notes' => $notes
            ]);

            $saleId = (int)$this->pdo->lastInsertId();

            $result = $this->pdo->prepare(
                "SELECT s.*,
                        c.customer_name,
                        a.full_name AS agent_name
                 FROM sales s
                 LEFT JOIN customers c ON c.id = s.customer_id
                 LEFT JOIN agents a ON a.id = s.agent_id
                 WHERE s.id = :id"
            );
            $result->execute(['id' => $saleId]);

            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Sale created successfully.',
                'data' => $result->fetch(PDO::FETCH_ASSOC)
            ]);

        } catch (Throwable $e) {
            error_log($e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Unable to create sale.'
            ]);
        }
    }
}
