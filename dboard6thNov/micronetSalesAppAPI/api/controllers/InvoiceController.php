<?php

class InvoiceController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // GET /api/v1/invoices
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
                $conditions[] = 'i.agent_id = :agent_id';
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

                $conditions[] = 'i.customer_id = :customer_id';
                $params['customer_id'] = $customerId;
            }

            if (isset($_GET['sale_id']) && $_GET['sale_id'] !== '') {
                $saleId = filter_var($_GET['sale_id'], FILTER_VALIDATE_INT);

                if ($saleId === false || $saleId < 1) {
                    http_response_code(422);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Invalid sale ID.'
                    ]);
                    return;
                }

                $conditions[] = 'i.sale_id = :sale_id';
                $params['sale_id'] = $saleId;
            }

            if (!empty($_GET['status'])) {
                $validStatuses = [
                    'draft', 'sent', 'partially_paid',
                    'paid', 'overdue', 'cancelled'
                ];

                if (!in_array($_GET['status'], $validStatuses, true)) {
                    http_response_code(422);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Invalid invoice status.'
                    ]);
                    return;
                }

                $conditions[] = 'i.status = :status';
                $params['status'] = $_GET['status'];
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

                $conditions[] = 'i.issue_date >= :date_from';
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

                $conditions[] = 'i.issue_date <= :date_to';
                $params['date_to'] = $_GET['date_to'];
            }

            $where = $conditions
                ? ' WHERE ' . implode(' AND ', $conditions)
                : '';

            $count = $this->pdo->prepare(
                "SELECT COUNT(*) FROM invoices i $where"
            );
            $count->execute($params);
            $total = (int)$count->fetchColumn();

            $sql = "SELECT
                        i.id,
                        i.invoice_number,
                        i.sale_id,
                        s.sale_number,
                        i.customer_id,
                        c.customer_code,
                        c.customer_name,
                        i.agent_id,
                        a.full_name AS agent_name,
                        i.issue_date,
                        i.due_date,
                        i.subtotal,
                        i.discount,
                        i.tax_amount,
                        i.total_amount,
                        i.amount_paid,
                        i.balance,
                        i.status,
                        i.notes,
                        i.created_at,
                        i.updated_at
                    FROM invoices i
                    LEFT JOIN sales s ON s.id = i.sale_id
                    LEFT JOIN customers c ON c.id = i.customer_id
                    LEFT JOIN agents a ON a.id = i.agent_id
                    $where
                    ORDER BY i.issue_date DESC, i.id DESC
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
                'message' => 'Invoices retrieved successfully.',
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
                'message' => 'Unable to retrieve invoices.'
            ]);
        }
    }

    // POST /api/v1/invoices
    public function store(array $data, array $user): void
    {
        $saleId = filter_var(
            $data['sale_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if ($saleId === false || $saleId === null || $saleId < 1) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'A valid sale_id is required.'
            ]);
            return;
        }

        $issueDate = $data['issue_date'] ?? date('Y-m-d');
        $dueDate = $data['due_date'] ?? null;

        $issue = is_string($issueDate)
            ? DateTime::createFromFormat('!Y-m-d', $issueDate)
            : false;

        if (!$issue || $issue->format('Y-m-d') !== $issueDate) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'issue_date must use YYYY-MM-DD format.'
            ]);
            return;
        }

        if ($dueDate !== null && $dueDate !== '') {
            $due = is_string($dueDate)
                ? DateTime::createFromFormat('!Y-m-d', $dueDate)
                : false;

            if (!$due || $due->format('Y-m-d') !== $dueDate) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'message' => 'due_date must use YYYY-MM-DD format.'
                ]);
                return;
            }

            if ($dueDate < $issueDate) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'message' => 'Due date cannot be before issue date.'
                ]);
                return;
            }
        } else {
            $dueDate = null;
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

        $requestedStatus = $data['status'] ?? 'sent';

        if (!in_array($requestedStatus, ['draft', 'sent'], true)) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'New invoices can only be draft or sent.'
            ]);
            return;
        }

        try {
            $isAdmin = ($user['role'] ?? '') === 'admin';

            // Load the sale and enforce ownership for agents.
            $saleSql = "SELECT
                            id, sale_number, agent_id, customer_id,
                            subtotal, discount, tax_amount,
                            total_amount, amount_paid, balance,
                            payment_status, sale_status
                        FROM sales
                        WHERE id = :sale_id";

            if (!$isAdmin) {
                $saleSql .= " AND agent_id = :agent_id";
            }

            $saleSql .= " LIMIT 1";

            $saleCheck = $this->pdo->prepare($saleSql);
            $saleCheck->bindValue(':sale_id', $saleId, PDO::PARAM_INT);

            if (!$isAdmin) {
                $saleCheck->bindValue(
                    ':agent_id',
                    (int)$user['id'],
                    PDO::PARAM_INT
                );
            }

            $saleCheck->execute();
            $sale = $saleCheck->fetch(PDO::FETCH_ASSOC);

            if (!$sale) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Sale not found or not accessible.'
                ]);
                return;
            }

            if ($sale['sale_status'] === 'cancelled') {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'message' => 'Cannot invoice a cancelled sale.'
                ]);
                return;
            }

            // Prevent duplicate invoices for the same sale.
            $duplicate = $this->pdo->prepare(
                "SELECT id FROM invoices WHERE sale_id = :sale_id LIMIT 1"
            );
            $duplicate->execute(['sale_id' => $saleId]);

            if ($duplicate->fetch()) {
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'message' => 'An invoice already exists for this sale.'
                ]);
                return;
            }

            // Copy financial figures from the sale.
            $subtotal = (float)$sale['subtotal'];
            $discount = (float)$sale['discount'];
            $taxAmount = (float)$sale['tax_amount'];
            $totalAmount = (float)$sale['total_amount'];
            $amountPaid = (float)$sale['amount_paid'];
            $balance = (float)$sale['balance'];

            if ($balance <= 0) {
                $status = 'paid';
            } elseif ($amountPaid > 0) {
                $status = 'partially_paid';
            } elseif ($requestedStatus === 'draft') {
                $status = 'draft';
            } else {
                $status = 'sent';
            }

            $invoiceNumber = 'INV-' . date('Ymd') . '-' .
                strtoupper(bin2hex(random_bytes(4)));

            $stmt = $this->pdo->prepare(
                "INSERT INTO invoices (
                    invoice_number,
                    sale_id,
                    customer_id,
                    agent_id,
                    issue_date,
                    due_date,
                    subtotal,
                    discount,
                    tax_amount,
                    total_amount,
                    amount_paid,
                    balance,
                    status,
                    notes
                ) VALUES (
                    :invoice_number,
                    :sale_id,
                    :customer_id,
                    :agent_id,
                    :issue_date,
                    :due_date,
                    :subtotal,
                    :discount,
                    :tax_amount,
                    :total_amount,
                    :amount_paid,
                    :balance,
                    :status,
                    :notes
                )"
            );

            $stmt->execute([
                'invoice_number' => $invoiceNumber,
                'sale_id' => $saleId,
                'customer_id' => $sale['customer_id'],
                'agent_id' => $sale['agent_id'],
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'amount_paid' => $amountPaid,
                'balance' => $balance,
                'status' => $status,
                'notes' => $notes
            ]);

            $invoiceId = (int)$this->pdo->lastInsertId();

            $result = $this->pdo->prepare(
                "SELECT i.*,
                        s.sale_number,
                        c.customer_name,
                        a.full_name AS agent_name
                 FROM invoices i
                 LEFT JOIN sales s ON s.id = i.sale_id
                 LEFT JOIN customers c ON c.id = i.customer_id
                 LEFT JOIN agents a ON a.id = i.agent_id
                 WHERE i.id = :id"
            );
            $result->execute(['id' => $invoiceId]);

            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Invoice created successfully.',
                'data' => $result->fetch(PDO::FETCH_ASSOC)
            ]);

        } catch (Throwable $e) {
            error_log($e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Unable to create invoice.'
            ]);
        }
    }
}
