<?php
include 'db_connect.php';
header('Content-Type: application/json');

$sql = "SELECT * FROM products WHERE is_available = 1 ORDER BY sort_order ASC";
$result = $conn->query($sql);

$products = [];

while($row = $result->fetch_assoc()) {
    $products[] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'price' => $row['price'],
        'category' => $row['category'],
        'image' => $row['image'],
        'ingredients' => $row['ingredients'] // <--- Added this line
    ];
}

echo json_encode($products);
?>