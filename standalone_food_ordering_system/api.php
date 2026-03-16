<?php
session_start();
include 'db_connect.php';
header('Content-Type: application/json');

function isDashUser() { return isset($_SESSION['admin_session']); }

$action = $_GET['action'] ?? '';

if ($action == 'get_orders') {
    if(!isDashUser()) { echo json_encode([]); exit; }
    
    $sql = "SELECT * FROM orders WHERE status NOT IN ('completed', 'cancelled') ORDER BY created_at ASC";
    $result = $conn->query($sql);
    $orders = [];
    while($row = $result->fetch_assoc()) {
        $oid = $row['id'];
        $items = [];
        $i_res = $conn->query("SELECT * FROM order_items WHERE order_id=$oid");
        while($i = $i_res->fetch_assoc()) $items[] = ['name'=>$i['product_name'], 'qty'=>$i['quantity']];
        $row['items'] = $items;
        $orders[] = $row;
    }
    echo json_encode($orders);
}

if ($action == 'get_history') {
    if(!isDashUser()) { echo json_encode([]); exit; }
    
    $start = $_GET['start'] ?? date('Y-m-d', strtotime('-30 days'));
    $end = $_GET['end'] ?? date('Y-m-d');
    $status = $_GET['status'] ?? 'all';
    
    $where = "WHERE DATE(created_at) BETWEEN '$start' AND '$end'";
    if($status == 'completed') $where .= " AND status='completed'";
    elseif($status == 'cancelled') $where .= " AND status='cancelled'";
    else $where .= " AND status IN ('completed', 'cancelled')";
    
    $history = []; $rev = 0; $count = 0;
    $result = $conn->query("SELECT * FROM orders $where ORDER BY created_at DESC");
    while($row = $result->fetch_assoc()) {
        if($row['status'] == 'completed') $rev += $row['total_price'];
        $count++;
        
        $oid = $row['id'];
        $i_res = $conn->query("SELECT product_name, quantity FROM order_items WHERE order_id=$oid");
        $str = [];
        while($i = $i_res->fetch_assoc()) $str[] = $i['quantity']."x ".$i['product_name'];
        $row['items_summary'] = implode(", ", $str);
        $history[] = $row;
    }
    echo json_encode(["stats"=>["total_revenue"=>$rev, "total_orders"=>$count], "history"=>$history]);
}

if ($action == 'update_status') {
    if(!isDashUser()) exit;
    $data = json_decode(file_get_contents("php://input"), true);
    $id = $data['id']; $status = $data['status'];
    
    if($status == 'delete') $conn->query("DELETE FROM orders WHERE id=$id");
    else {
        $stmt = $conn->prepare("UPDATE orders SET status=? WHERE id=?");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();
    }
    echo json_encode(["success"=>true]);
}
?>