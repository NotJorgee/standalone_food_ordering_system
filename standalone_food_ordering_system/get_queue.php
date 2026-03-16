<?php
include 'db_connect.php';
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *"); 

// FIX: Changed sorting to use 'id' instead of the missing 'updated_at' column
$sql = "SELECT id, status FROM orders WHERE status IN ('preparing', 'ready') ORDER BY id DESC";

$result = $conn->query($sql);

$orders = [];
if ($result) {
    while($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}

echo json_encode($orders);
?>