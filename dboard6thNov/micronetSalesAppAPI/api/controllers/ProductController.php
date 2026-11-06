<?php

class ProductController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // GET /api/v1/products
    public function index(array $user): void
    {
        try {
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;

            $conditions = [];
            $params = [];

            if (isset($_GET['status']) && $_GET['status'] !== '') {
                $status = $_GET['status'];

                // Adjust these values if your products table uses different enums.
                if (!in_array($status, ['active', 'inactive'], true)) {
                    http_response_code(422);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Invalid product status.'
                    ]);
                    return;
                }

                $conditions[] = 'p.status = :status';
                $params['status'] = $status;
            }

            if (!empty($_GET['category'])) {
                $conditions[] = 'p.category = :category';
                $params['category'] = trim($_GET['category']);
            }

            if (!empty($_GET['search'])) {
                $search = '%' . trim($_GET['search']) . '%';

                $conditions[] = '(
                    p.product_name LIKE :name
                    OR p.product_code LIKE :code
                    OR p.description LIKE :description
                )';

                $params['name'] = $search;
                $params['code'] = $search;
                $params['description'] = $search;
            }

            $where = $conditions
                ? ' WHERE ' . implode(' AND ', $conditions)
                : '';

            $count = $this->pdo->prepare(
                "SELECT COUNT(*) FROM products p $where"
            );
            $count->execute($params);
            $total = (int)$count->fetchColumn();

            $sql = "SELECT
                        p.id,
                        p.product_code,
                        p.product_name,
                        p.description,
                        p.category,
                        p.unit_price,
                        p.cost_price,
                        p.stock_quantity,
                        p.status,
                        p.created_at,
                        p.updated_at
                    FROM products p
                    $where
                    ORDER BY p.created_at DESC, p.id DESC
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
                'message' => 'Products retrieved successfully.',
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
                'message' => 'Unable to retrieve products.'
            ]);
        }
    }

    // POST /api/v1/products
    public function store(array $data, array $user): void
    {
        if (($user['role'] ?? '') !== 'admin') {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Only administrators can create products.'
            ]);
            return;
        }

        $productName = trim((string)($data['product_name'] ?? ''));

        if (strlen($productName) < 2 || strlen($productName) > 150) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Product name must be between 2 and 150 characters.'
            ]);
            return;
        }

        $description = $data['description'] ?? null;
        $category = $data['category'] ?? null;

        if ($description !== null && !is_string($description)) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Description must be text or null.'
            ]);
            return;
        }

        if ($category !== null && !is_string($category)) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Category must be text or null.'
            ]);
            return;
        }

        $category = $category !== null ? trim($category) : null;
        $description = $description !== null ? trim($description) : null;

        if ($category === '') {
            $category = null;
        }

        if ($description === '') {
            $description = null;
        }

        // Validate product prices.
        $unitPrice = $data['unit_price'] ?? null;
        $costPrice = $data['cost_price'] ?? 0;
        $stockQuantity = $data['stock_quantity'] ?? 0;

        foreach ([
            'unit_price' => $unitPrice,
            'cost_price' => $costPrice
        ] as $field => $value) {
            if (!is_numeric($value) ||
                !is_finite((float)$value) ||
                (float)$value < 0) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'message' => "$field must be a non-negative number."
                ]);
                return;
            }
        }

        if (!is_numeric($stockQuantity) ||
            !is_finite((float)$stockQuantity) ||
            (float)$stockQuantity < 0 ||
            floor((float)$stockQuantity) != (float)$stockQuantity) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'stock_quantity must be a non-negative whole number.'
            ]);
            return;
        }

        $unitPrice = round((float)$unitPrice, 2);
        $costPrice = round((float)$costPrice, 2);
        $stockQuantity = (int)$stockQuantity;

        $status = $data['status'] ?? 'active';

        if (!in_array($status, ['active', 'inactive'], true)) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid product status.'
            ]);
            return;
        }

        try {
            $productCode = 'PRD-' . date('Ymd') . '-' .
                strtoupper(bin2hex(random_bytes(4)));

            $stmt = $this->pdo->prepare(
                "INSERT INTO products (
                    product_code,
                    product_name,
                    description,
                    category,
                    unit_price,
                    cost_price,
                    stock_quantity,
                    status
                ) VALUES (
                    :product_code,
                    :product_name,
                    :description,
                    :category,
                    :unit_price,
                    :cost_price,
                    :stock_quantity,
                    :status
                )"
            );

            $stmt->execute([
                'product_code' => $productCode,
                'product_name' => $productName,
                'description' => $description,
                'category' => $category,
                'unit_price' => $unitPrice,
                'cost_price' => $costPrice,
                'stock_quantity' => $stockQuantity,
                'status' => $status
            ]);

            $productId = (int)$this->pdo->lastInsertId();

            $result = $this->pdo->prepare(
                "SELECT id, product_code, product_name,
                        description, category, unit_price,
                        cost_price, stock_quantity, status,
                        created_at, updated_at
                 FROM products
                 WHERE id = :id"
            );

            $result->execute(['id' => $productId]);

            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Product created successfully.',
                'data' => $result->fetch(PDO::FETCH_ASSOC)
            ]);

        } catch (Throwable $e) {
            error_log($e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Unable to create product.'
            ]);
        }
    }
}
