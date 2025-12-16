<?php
// api/results/list.php
// Returns list of optimization runs with pagination + optional filters (date, keyword, status)

require_once __DIR__ . "/../../app/config/db.php";

header('Content-Type: application/json');

// SESSION AUTH (optional)
if (session_status() === PHP_SESSION_NONE) session_start();

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = max(5, min(200, intval($_GET['limit'] ?? 20))); // sane boundaries
$offset = ($page - 1) * $limit;

// Filters
$keyword = trim($_GET['q'] ?? '');
$status  = trim($_GET['status'] ?? '');   // e.g. "SUCCESS", "FAIL", etc.
$dateFrom = trim($_GET['from'] ?? '');
$dateTo   = trim($_GET['to'] ?? '');

// DB
$db = new Database();
$pdo = $db->getConnection();

// Base query
$sql = "SELECT o.opt_id, o.created_at, o.status, o.notes
        FROM optimizations o
        WHERE 1 ";

// dynamic filters
$params = [];

if ($keyword !== '') {
    $sql .= " AND (o.notes LIKE :kw OR o.opt_id LIKE :kw)";
    $params[':kw'] = "%$keyword%";
}

if ($status !== '') {
    $sql .= " AND o.status = :st";
    $params[':st'] = $status;
}

if ($dateFrom !== '') {
    $sql .= " AND DATE(o.created_at) >= :df";
    $params[':df'] = $dateFrom;
}

if ($dateTo !== '') {
    $sql .= " AND DATE(o.created_at) <= :dt";
    $params[':dt'] = $dateTo;
}

// Count total rows for pagination
$countSql = "SELECT COUNT(*) FROM ($sql) AS sub";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total = intval($stmt->fetchColumn());

// Add order + pagination
$sql .= " ORDER BY o.opt_id DESC LIMIT :offset, :limit";

$stmt = $pdo->prepare($sql);

// Bind numeric separately
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit,  PDO::PARAM_INT);

$stmt->execute();
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'page' => $page,
    'limit' => $limit,
    'total' => $total,
    'pages' => ceil($total / $limit),
    'items' => $items
], JSON_PRETTY_PRINT);
