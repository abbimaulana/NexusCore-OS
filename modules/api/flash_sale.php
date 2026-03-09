<?php
/**
 * NexusCore OS — modules/api/flash_sale.php
 * Returns active flash-sale products as JSON (used by front-end timers).
 */

require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!$db) {
    echo json_encode(['products' => []]);
    exit;
}

$now  = date('Y-m-d H:i:s');
$stmt = $db->prepare(
    'SELECT id, name, price, flash_sale_price, flash_sale_end
     FROM products
     WHERE is_active = 1
       AND flash_sale_price IS NOT NULL
       AND flash_sale_end > ?'
);

if (!$stmt) {
    echo json_encode(['products' => []]);
    exit;
}

$stmt->bind_param('s', $now);
$stmt->execute();
$res      = $stmt->get_result();
$products = [];

while ($row = $res->fetch_assoc()) {
    $products[] = [
        'id'               => (int)$row['id'],
        'name'             => $row['name'],
        'price'            => (float)$row['price'],
        'flash_sale_price' => (float)$row['flash_sale_price'],
        'flash_sale_end'   => $row['flash_sale_end'],
    ];
}

$stmt->close();
echo json_encode(['products' => $products]);
