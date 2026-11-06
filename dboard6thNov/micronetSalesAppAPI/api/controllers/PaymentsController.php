<?php

class PaymentsController
{
    public static function index(PDO $pdo, array $user): void
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $where = [];
        $params = [];

        // Agents can only see their own payments.
        if (($user['role'] ?? '') !== 'admin') {
            $where[] = 'p.agent_id = :auth_agent_id';
            $params['auth_agent_id'] = (int)$user['id'];
        }

        if (!empty($_GET['customer_id'])) {
            $where[] = 'p.customer_id = :customer_id';
            $params['customer_id'] = (int)$_GET['customer_id'];
        }

        if (!empty($_GET['sale_id'])) {
            $where[] = 'p.sale_id = :sale_id';
            $params['sale_id'] = (int)$_GET['sale_id'];
        }

        if (!empty($_GET['invoice_id'])) {
            $where[] = 'p.invoice_id = :invoice_id';
            $params['invoice_id'] = (int)$_GET['invoice_id'];
        }

        if (!empty($_GET['status'])) {
            $allowedStatuses = ['pending', 'completed', 'failed', 'reversed'];

            if (!in_array($_GET['status'], $allowedStatuses, true)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid payment status.'
                ]);
                return;
            }

            $where[] = 'p.status = :status';
            $params['status'] = $_GET['status'];
        }

        if (!empty($_GET['date_from'])) {
            $where[] = 'p.payment_date >= :date_from';
            $params['date_from'] = $_GET['date_from'] . ' 00:00:00';
        }

        if (!empty($_GET['date_to'])) {
            $where[] = 'p.payment_date <= :date_to';
            $params['date_to'] = $_GET['date_to'] . ' 23:59:59';
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM payments p
            $whereSql
        ");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT
                p.id,
                p.payment_reference,
                p.customer_id,
                c.customer_name,
                p.agent_id,
                a.full_name AS agent_name,
                p.sale_id,
                p.invoice_id,
                p.amount,
                p.currency,
                p.payment_method,
                p.transaction_reference,
                p.payment_date,
                p.status,
                p.notes,
                p.created_at
            FROM payments p
            LEFT JOIN customers c ON c.id = p.customer_id
            LEFT JOIN agents a ON a.id = p.agent_id
            $whereSql
            ORDER BY p.payment_date DESC, p.id DESC
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

    public static function create(PDO $pdo, array $user, array $data): void
    {
        $customerId = filter_var(
            $data['customer_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $amount = $data['amount'] ?? null;

        if (!$customerId || !is_numeric($amount) || (float)$amount <= 0) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'A valid customer_id and positive amount are required.'
            ]);
            return;
        }

        $amount = number_format((float)$amount, 2, '.', '');

        $currency = strtoupper(trim($data['currency'] ?? 'UGX'));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Currency must be a three-letter code.'
            ]);
            return;
        }

        $methods = [
            'cash',
            'mobile_money',
            'bank_transfer',
            'card',
            'cheque',
            'other'
        ];

        $method = $data['payment_method'] ?? 'cash';

        if (!in_array($method, $methods, true)) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid payment method.'
            ]);
            return;
        }

        $status = $data['status'] ?? 'completed';

        // This endpoint records new pending or completed payments.
        if (!in_array($status, ['pending', 'completed'], true)) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'New payments can only be pending or completed.'
            ]);
            return;
        }

        $saleId = !empty($data['sale_id'])
            ? filter_var($data['sale_id'], FILTER_VALIDATE_INT)
            : null;

        $invoiceId = !empty($data['invoice_id'])
            ? filter_var($data['invoice_id'], FILTER_VALIDATE_INT)
            : null;

        if ((!empty($data['sale_id']) && !$saleId) ||
            (!empty($data['invoice_id']) && !$invoiceId)) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid sale_id or invoice_id.'
            ]);
            return;
        }

        $paymentDate = $data['payment_date'] ?? date('Y-m-d H:i:s');

        $date = DateTime::createFromFormat('Y-m-d H:i:s', $paymentDate);
        if (!$date || $date->format('Y-m-d H:i:s') !== $paymentDate) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'payment_date must use YYYY-MM-DD HH:MM:SS format.'
            ]);
            return;
        }

        try {
            $pdo->beginTransaction();

            // Confirm customer exists and enforce agent ownership.
            $customerStmt = $pdo->prepare("
                SELECT id, assigned_agent_id
                FROM customers
                WHERE id = :id
                FOR UPDATE
            ");
            $customerStmt->execute(['id' => $customerId]);
            $customer = $customerStmt->fetch();

            if (!$customer) {
                throw new InvalidArgumentException('Customer not found.');
            }

            if (($user['role'] ?? '') !== 'admin' &&
                (int)$customer['assigned_agent_id'] !== (int)$user['id']) {
                throw new DomainException('You cannot record payments for this customer.');
            }

            // Admin may assign a payment to an active agent.
            $agentId = (int)$user['id'];

            if (($user['role'] ?? '') === 'admin' && !empty($data['agent_id'])) {
                $agentId = filter_var($data['agent_id'], FILTER_VALIDATE_INT);

                if (!$agentId) {
                    throw new InvalidArgumentException('Invalid agent_id.');
                }

                $agentStmt = $pdo->prepare("
                    SELECT id
                    FROM agents
                    WHERE id = :id AND status = 'active'
                ");
                $agentStmt->execute(['id' => $agentId]);

                if (!$agentStmt->fetch()) {
                    throw new InvalidArgumentException('Selected agent does not exist or is inactive.');
                }
            }

            // Agents can only record payments against their own sales.
            $sale = null;

            if ($saleId) {
                $saleStmt = $pdo->prepare("
                    SELECT id, customer_id, agent_id, total_amount,
                           amount_paid, balance, payment_status
                    FROM sales
                    WHERE id = :id
                    FOR UPDATE
                ");
                $saleStmt->execute(['id' => $saleId]);
                $sale = $saleStmt->fetch();

                if (!$sale) {
                    throw new InvalidArgumentException('Sale not found.');
                }

                if ((int)$sale['customer_id'] !== (int)$customerId) {
                    throw new InvalidArgumentException('Sale does not belong to this customer.');
                }

                if (($user['role'] ?? '') !== 'admin' &&
                    (int)$sale['agent_id'] !== (int)$user['id']) {
                    throw new DomainException('You cannot record payment for this sale.');
                }

                if (($user['role'] ?? '') === 'admin') {
                    $agentId = (int)$sale['agent_id'];
                }
            }

            $invoice = null;

            if ($invoiceId) {
                $invoiceStmt = $pdo->prepare("
                    SELECT id, sale_id, customer_id, agent_id, total_amount,
                           amount_paid, balance, status
                    FROM invoices
                    WHERE id = :id
                    FOR UPDATE
                ");
                $invoiceStmt->execute(['id' => $invoiceId]);
                $invoice = $invoiceStmt->fetch();

                if (!$invoice) {
                    throw new InvalidArgumentException('Invoice not found.');
                }

                if ((int)$invoice['customer_id'] !== (int)$customerId) {
                    throw new InvalidArgumentException('Invoice does not belong to this customer.');
                }

                if (($user['role'] ?? '') !== 'admin' &&
                    (int)$invoice['agent_id'] !== (int)$user['id']) {
                    throw new DomainException('You cannot record payment for this invoice.');
                }

                if ($saleId && (int)$invoice['sale_id'] !== (int)$saleId) {
                    throw new InvalidArgumentException('Invoice does not belong to the selected sale.');
                }

                if (!$saleId) {
                    $saleId = (int)$invoice['sale_id'];

                    $saleStmt = $pdo->prepare("
                        SELECT id, customer_id, agent_id, total_amount,
                               amount_paid, balance, payment_status
                        FROM sales
                        WHERE id = :id
                        FOR UPDATE
                    ");
                    $saleStmt->execute(['id' => $saleId]);
                    $sale = $saleStmt->fetch();
                }

                if (($user['role'] ?? '') === 'admin') {
                    $agentId = (int)$invoice['agent_id'];
                }
            }

            if ($status === 'completed') {
                if ($sale && (float)$amount > (float)$sale['balance']) {
                    throw new InvalidArgumentException(
                        'Payment exceeds the outstanding sale balance.'
                    );
                }

                if ($invoice && (float)$amount > (float)$invoice['balance']) {
                    throw new InvalidArgumentException(
                        'Payment exceeds the outstanding invoice balance.'
                    );
                }
            }

            $reference = trim($data['payment_reference'] ?? '');

            if ($reference === '') {
                $reference = 'PAY-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
            }

            $insert = $pdo->prepare("
                INSERT INTO payments (
                    payment_reference,
                    customer_id,
                    agent_id,
                    sale_id,
                    invoice_id,
                    amount,
                    currency,
                    payment_method,
                    transaction_reference,
                    payment_date,
                    status,
                    notes
                ) VALUES (
                    :payment_reference,
                    :customer_id,
                    :agent_id,
                    :sale_id,
                    :invoice_id,
                    :amount,
                    :currency,
                    :payment_method,
                    :transaction_reference,
                    :payment_date,
                    :status,
                    :notes
                )
            ");

            $insert->execute([
                'payment_reference' => $reference,
                'customer_id' => $customerId,
                'agent_id' => $agentId,
                'sale_id' => $saleId,
                'invoice_id' => $invoiceId,
                'amount' => $amount,
                'currency' => $currency,
                'payment_method' => $method,
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'payment_date' => $paymentDate,
                'status' => $status,
                'notes' => $data['notes'] ?? null
            ]);

            $paymentId = (int)$pdo->lastInsertId();

            // Only completed payments affect outstanding balances.
            if ($status === 'completed') {
                if ($sale) {
                    $updateSale = $pdo->prepare("
                        UPDATE sales
                        SET
                            amount_paid = amount_paid + :amount,
                            balance = GREATEST(balance - :balance_amount, 0),
                            payment_status = CASE
                                WHEN balance <= 0 THEN 'paid'
                                WHEN amount_paid > 0 THEN 'partial'
                                ELSE 'unpaid'
                            END
                        WHERE id = :id
                    ");

                    $updateSale->execute([
                        'amount' => $amount,
                        'balance_amount' => $amount,
                        'id' => $saleId
                    ]);
                }

                if ($invoice) {
                    $updateInvoice = $pdo->prepare("
                        UPDATE invoices
                        SET
                            amount_paid = amount_paid + :amount,
                            balance = GREATEST(balance - :balance_amount, 0),
                            status = CASE
                                WHEN balance <= 0 THEN 'paid'
                                WHEN amount_paid > 0 THEN 'partially_paid'
                                ELSE status
                            END
                        WHERE id = :id
                    ");

                    $updateInvoice->execute([
                        'amount' => $amount,
                        'balance_amount' => $amount,
                        'id' => $invoiceId
                    ]);
                }
            }

            $pdo->commit();

            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Payment recorded successfully.',
                'data' => [
                    'id' => $paymentId,
                    'payment_reference' => $reference,
                    'customer_id' => $customerId,
                    'agent_id' => $agentId,
                    'sale_id' => $saleId,
                    'invoice_id' => $invoiceId,
                    'amount' => $amount,
                    'currency' => $currency,
                    'payment_method' => $method,
                    'status' => $status
                ]
            ]);

        } catch (InvalidArgumentException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);

        } catch (DomainException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());

            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Payment could not be recorded. Check the reference and database constraints.'
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());

            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'An unexpected error occurred while recording payment.'
            ]);
        }
    }
}
