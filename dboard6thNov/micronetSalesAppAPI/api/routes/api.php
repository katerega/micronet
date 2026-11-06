<?php

require_once __DIR__ . '/../controllers/DashboardController.php';

require_once __DIR__ . '/../controllers/CustomerController.php';
require_once __DIR__ . '/../controllers/VisitController.php';
require_once __DIR__ . '/../controllers/SalesController.php';
require_once __DIR__ . '/../controllers/InvoiceController.php';
require_once __DIR__ . '/../controllers/FollowupController.php';
require_once __DIR__ . '/../controllers/ProductController.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/AgentController.php';
require_once __DIR__ . '/../controllers/PaymentsController.php';
require_once __DIR__ . '/../controllers/CommissionController.php';
require_once __DIR__ . '/../controllers/TargetController.php';
require_once __DIR__ . '/../controllers/NotificationController.php';
require_once __DIR__ . '/../middleware/auth.php';

function dispatchApi(PDO $pdo, string $method, string $path, array $data): void
{
    if ($method === 'POST' && $path === '/api/v1/auth/login') {
        $controller = new AuthController($pdo);
        $controller->login($data);
        return;
    }

    if ($method === 'POST' && $path === '/api/v1/auth/logout') {
        $controller = new AuthController($pdo);
        $controller->logout();
        return;
    }

    if ($method === 'POST' && $path === '/api/v1/auth/refresh') {
        $controller = new AuthController($pdo);
        $controller->refresh($data);
        return;
    }

    if ($method === 'POST' && $path === '/api/v1/auth/forgot-password') {
        $controller = new AuthController($pdo);
        $controller->forgotPassword($data);
        return;
    }

    if ($method === 'POST' && $path === '/api/v1/admin/agents') {
        $admin = AuthMiddleware::requireAdmin($pdo);

        if ($admin === null) {
            return;
        }

        $controller = new AgentController($pdo);
        $controller->create($data, (int) $admin['id']);
        return;
    }

    if ($method === 'GET' && $path === '/api/v1/admin/agents') {
        $admin = AuthMiddleware::requireAdmin($pdo);

        if ($admin === null) {
            return;
        }

        $controller = new AgentController($pdo);
        $controller->index();
        return;
    }


    if ($method === 'PATCH' &&
        preg_match('#^/api/v1/admin/agents/([0-9]+)$#', $path, $matches)) {

        $admin = AuthMiddleware::requireAdmin($pdo);
        if ($admin === null) return;

        $controller = new AgentController($pdo);
        $controller->update((int) $matches[1], $data);
        return;
    }


    if ($method === 'DELETE' &&
        preg_match('#^/api/v1/admin/agents/([0-9]+)$#', $path, $matches)) {

        $admin = AuthMiddleware::requireAdmin($pdo);
        if ($admin === null) return;

        $controller = new AgentController($pdo);
        $controller->deactivate((int) $matches[1]);
        return;
    }


    if ($method === 'GET' && $path === '/api/v1/dashboard') {
        $user = AuthMiddleware::requireAuth($pdo);
        if ($user === null) return;

        $controller = new DashboardController($pdo);
        $controller->index($user);
        return;
    }


    if ($path === '/api/v1/customers' &&
        ($method === 'GET' || $method === 'POST')) {

        $user = AuthMiddleware::requireAuth($pdo);
        if ($user === null) return;

        $controller = new CustomerController($pdo);

        if ($method === 'GET') {
            $controller->index($user);
            return;
        }

        if ($method === 'POST') {
            $controller->store($data, $user);
            return;
        }
    }


    if (preg_match('#^/api/v1/customers/([1-9][0-9]*)$#', $path, $matches)) {
        $user = AuthMiddleware::requireAuth($pdo);
        if ($user === null) return;

        $customerId = (int)$matches[1];
        $controller = new CustomerController($pdo);

        if ($method === 'GET') {
            $controller->show($customerId, $user);
            return;
        }

        if ($method === 'PATCH') {
            $controller->update($customerId, $data, $user);
            return;
        }

        if ($method === 'DELETE') {
            $controller->destroy($customerId, $user);
            return;
        }
    }


    if ($path === '/api/v1/visits' &&
        ($method === 'GET' || $method === 'POST')) {

        $user = AuthMiddleware::requireAuth($pdo);
        if ($user === null) return;

        $controller = new VisitController($pdo);

        if ($method === 'GET') {
            $controller->index($user);
            return;
        }

        if ($method === 'POST') {
            $controller->store($data, $user);
            return;
        }
    }


    if ($path === '/api/v1/sales' &&
        ($method === 'GET' || $method === 'POST')) {

        $user = AuthMiddleware::requireAuth($pdo);
        if ($user === null) return;

        $controller = new SalesController($pdo);

        if ($method === 'GET') {
            $controller->index($user);
            return;
        }

        if ($method === 'POST') {
            $controller->store($data, $user);
            return;
        }
    }


    if ($path === '/api/v1/invoices' &&
        ($method === 'GET' || $method === 'POST')) {

        $user = AuthMiddleware::requireAuth($pdo);
        if ($user === null) return;

        $controller = new InvoiceController($pdo);

        if ($method === 'GET') {
            $controller->index($user);
            return;
        }

        if ($method === 'POST') {
            $controller->store($data, $user);
            return;
        }
    }


    if ($path === '/api/v1/followups' &&
        ($method === 'GET' || $method === 'POST')) {

        $user = AuthMiddleware::requireAuth($pdo);
        if ($user === null) return;

        $controller = new FollowupController($pdo);

        if ($method === 'GET') {
            $controller->index($user);
            return;
        }

        if ($method === 'POST') {
            $controller->store($data, $user);
            return;
        }
    }


    if ($path === '/api/v1/products' &&
        ($method === 'GET' || $method === 'POST')) {

        $user = AuthMiddleware::requireAuth($pdo);
        if ($user === null) return;

        $controller = new ProductController($pdo);

        if ($method === 'GET') {
            $controller->index($user);
            return;
        }

        if ($method === 'POST') {
            $controller->store($data, $user);
            return;
        }
    }

    
    // Payments: list and create
    if ($path === '/api/v1/payments' && in_array($method, ['GET', 'POST'], true)) {
        $user = AuthMiddleware::requireAuth($pdo);
        if ($user === null) return;

        if ($method === 'GET') {
            PaymentsController::index($pdo, $user);
        } else {
            PaymentsController::create($pdo, $user, $data);
        }
        return;
    }


    // Commissions: view commission records
    if ($path === '/api/v1/commissions' && $method === 'GET') {
        $user = AuthMiddleware::requireAuth($pdo);
        if ($user === null) return;

        CommissionController::index($pdo, $user);
        return;
    }


    // Targets: view and create
    if ($path === '/api/v1/targets' && in_array($method, ['GET', 'POST'], true)) {

        if ($method === 'GET') {
            $user = AuthMiddleware::requireAuth($pdo);
            if ($user === null) return;

            TargetController::index($pdo, $user);
            return;
        }

        if ($method === 'POST') {
            $user = AuthMiddleware::requireAdmin($pdo);
            if ($user === null) return;

            TargetController::create($pdo, $user, $data);
            return;
        }
    }


    // Notifications: list notifications for authenticated user
    if ($path === '/api/v1/notifications' && $method === 'GET') {
        $user = AuthMiddleware::requireAuth($pdo);
        if ($user === null) return;

        NotificationController::index($pdo, $user);
        return;
    }


    // Mark notification as read
    if ($method === 'PATCH' && preg_match('#^/api/v1/notifications/([0-9]+)/read$#', $path, $matches)) {
        $user = AuthMiddleware::requireAuth($pdo);
        if ($user === null) return;

        NotificationController::markRead($pdo, $user, (int) $matches[1]);
        return;
    }

http_response_code(404);

    echo json_encode([
        'success' => false,
        'message' => 'API endpoint not found.'
    ]);
}
