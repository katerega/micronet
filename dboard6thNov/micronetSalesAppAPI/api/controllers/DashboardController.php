<?php

class DashboardController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function index(array $user): void
    {
        $isAdmin = ($user['role'] ?? '') === 'admin';
        $agentId = (int) $user['id'];

        // Administrators see all records. Agents see their own records.
        $salesFilter = $isAdmin ? '' : ' WHERE agent_id = :agent_id';
        $customerFilter = $isAdmin ? '' : ' WHERE assigned_agent_id = :agent_id';
        $visitFilter = $isAdmin ? '' : ' WHERE agent_id = :agent_id';
        $followupFilter = $isAdmin ? '' : ' WHERE agent_id = :agent_id';
        $invoiceFilter = $isAdmin ? '' : ' WHERE agent_id = :agent_id';
        $commissionFilter = $isAdmin ? '' : ' WHERE agent_id = :agent_id';

        $runCount = function (string $sql, bool $scoped = true) use ($agentId, $isAdmin): int {
            $stmt = $this->pdo->prepare($sql);

            if (!$isAdmin && $scoped) {
                $stmt->execute(['agent_id' => $agentId]);
            } else {
                $stmt->execute();
            }

            return (int) $stmt->fetchColumn();
        };

        $runAmount = function (string $sql, bool $scoped = true) use ($agentId, $isAdmin): float {
            $stmt = $this->pdo->prepare($sql);

            if (!$isAdmin && $scoped) {
                $stmt->execute(['agent_id' => $agentId]);
            } else {
                $stmt->execute();
            }

            return (float) ($stmt->fetchColumn() ?: 0);
        };

        try {
            $totalSales = $runCount(
                "SELECT COUNT(*) FROM sales" . $salesFilter
            );

            $salesToday = $runCount(
                "SELECT COUNT(*) FROM sales" .
                ($isAdmin ? " WHERE" : " WHERE agent_id = :agent_id AND") .
                " DATE(sale_date) = CURRENT_DATE"
            );

            $salesThisMonth = $runCount(
                "SELECT COUNT(*) FROM sales" .
                ($isAdmin ? " WHERE" : " WHERE agent_id = :agent_id AND") .
                " YEAR(sale_date) = YEAR(CURRENT_DATE)
                  AND MONTH(sale_date) = MONTH(CURRENT_DATE)"
            );

            $revenueToday = $runAmount(
                "SELECT COALESCE(SUM(total_amount), 0) FROM sales" .
                ($isAdmin ? " WHERE" : " WHERE agent_id = :agent_id AND") .
                " DATE(sale_date) = CURRENT_DATE
                  AND sale_status NOT IN ('cancelled', 'draft')"
            );

            $revenueThisMonth = $runAmount(
                "SELECT COALESCE(SUM(total_amount), 0) FROM sales" .
                ($isAdmin ? " WHERE" : " WHERE agent_id = :agent_id AND") .
                " YEAR(sale_date) = YEAR(CURRENT_DATE)
                  AND MONTH(sale_date) = MONTH(CURRENT_DATE)
                  AND sale_status NOT IN ('cancelled', 'draft')"
            );

            $totalCustomers = $runCount(
                "SELECT COUNT(*) FROM customers" . $customerFilter
            );

            $visitsToday = $runCount(
                "SELECT COUNT(*) FROM visits" .
                ($isAdmin ? " WHERE" : " WHERE agent_id = :agent_id AND") .
                " DATE(check_in_time) = CURRENT_DATE"
            );

            $pendingFollowups = $runCount(
                "SELECT COUNT(*) FROM followups" .
                ($isAdmin ? " WHERE" : " WHERE agent_id = :agent_id AND") .
                " status IN ('pending', 'overdue')"
            );

            $outstandingInvoices = $runAmount(
                "SELECT COALESCE(SUM(balance), 0) FROM invoices" .
                ($isAdmin ? " WHERE" : " WHERE agent_id = :agent_id AND") .
                " balance > 0 AND status NOT IN ('paid', 'cancelled')"
            );

            $pendingCommissions = $runAmount(
                "SELECT COALESCE(SUM(commission_amount), 0) FROM commissions" .
                ($isAdmin ? " WHERE" : " WHERE agent_id = :agent_id AND") .
                " status = 'pending'"
            );

            http_response_code(200);

            echo json_encode([
                'success' => true,
                'message' => 'Dashboard statistics retrieved successfully.',
                'data' => [
                    'user' => [
                        'id' => $agentId,
                        'name' => $user['full_name'],
                        'role' => $user['role']
                    ],
                    'sales' => [
                        'total' => $totalSales,
                        'today' => $salesToday,
                        'this_month' => $salesThisMonth
                    ],
                    'revenue' => [
                        'today' => $revenueToday,
                        'this_month' => $revenueThisMonth
                    ],
                    'customers' => [
                        'total' => $totalCustomers
                    ],
                    'visits' => [
                        'today' => $visitsToday
                    ],
                    'followups' => [
                        'pending' => $pendingFollowups
                    ],
                    'invoices' => [
                        'outstanding_balance' => $outstandingInvoices
                    ],
                    'commissions' => [
                        'pending' => $pendingCommissions
                    ]
                ]
            ]);

        } catch (Throwable $e) {
            error_log($e->getMessage());

            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Unable to retrieve dashboard statistics.'
            ]);
        }
    }
}
