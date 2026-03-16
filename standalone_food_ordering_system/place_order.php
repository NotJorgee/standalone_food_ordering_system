<?php
include 'db_connect.php';

// Get JSON input from Kiosk
$data = json_decode(file_get_contents("php://input"), true);

if(isset($data['items'])) {
    $total = $data['total'];
    $items = $data['items'];

    // 1. Insert Order
    $stmt = $conn->prepare("INSERT INTO orders (total_price) VALUES (?)");
    $stmt->bind_param("d", $total);
    $stmt->execute();
    $order_id = $stmt->insert_id; // Get the new Order ID (e.g., 101)

    // 2. Insert Items
    $stmt_item = $conn->prepare("INSERT INTO order_items (order_id, product_name, quantity) VALUES (?, ?, ?)");
    foreach($items as $item) {
        $stmt_item->bind_param("isi", $order_id, $item['name'], $item['qty']);
        $stmt_item->execute();
    }

    echo json_encode(["status" => "success", "order_id" => $order_id]);
} else {
    echo json_encode(["status" => "error"]);
}
?>