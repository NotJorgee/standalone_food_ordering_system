<?php
session_start();
include 'db_connect.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// --- AUTH HELPERS ---
function isAdmin() {
    return isset($_SESSION['admin_session']) && $_SESSION['admin_session']['role'] === 'admin';
}
function isLogged() {
    return isset($_SESSION['admin_session']) || isset($_SESSION['kiosk_session']);
}
// --------------------

// 1. GET ALL PRODUCTS
if ($action == 'get_all') {
    if(!isLogged()) { echo json_encode([]); exit; }
    $result = $conn->query("SELECT * FROM products ORDER BY sort_order ASC");
    $products = [];
    while($row = $result->fetch_assoc()) {
        $row['image_url'] = $row['image'] ? "uploads/" . $row['image'] : null;
        $products[] = $row;
    }
    echo json_encode($products);
}

// 2. SAVE PRODUCT (Updated with Ingredients)
if ($action == 'save') {
    if (!isAdmin()) { echo json_encode(["status"=>"error"]); exit; }
    
    $name = $_POST['name']; 
    $price = $_POST['price']; 
    $cat = $_POST['category']; 
    $ing = $_POST['ingredients']; // <--- NEW FIELD
    $id = $_POST['id'] ?? ''; 
    $avail = $_POST['is_available'];
    
    $imagePath = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $imagePath = time() . "_" . $_FILES['image']['name'];
        move_uploaded_file($_FILES['image']['tmp_name'], "uploads/" . $imagePath);
    }

    if ($id) {
        if ($imagePath) {
            $stmt = $conn->prepare("UPDATE products SET name=?, price=?, category=?, ingredients=?, is_available=?, image=? WHERE id=?");
            $stmt->bind_param("sdssisi", $name, $price, $cat, $ing, $avail, $imagePath, $id);
        } else {
            $stmt = $conn->prepare("UPDATE products SET name=?, price=?, category=?, ingredients=?, is_available=? WHERE id=?");
            $stmt->bind_param("sdssii", $name, $price, $cat, $ing, $avail, $id);
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO products (name, price, category, ingredients, is_available, image) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sdssis", $name, $price, $cat, $ing, $avail, $imagePath);
    }
    $stmt->execute();
    echo json_encode(["status" => "success"]);
}

// 3. DELETE PRODUCT
if ($action == 'delete') {
    if (!isAdmin()) exit;
    $id = $_POST['id'];
    $conn->query("DELETE FROM products WHERE id=$id");
    echo json_encode(["status" => "success"]);
}

// 4. TOGGLE STOCK
if ($action == 'toggle_stock') {
    if (!isset($_SESSION['admin_session'])) exit;
    $id = $_POST['id']; $status = $_POST['status'];
    $stmt = $conn->prepare("UPDATE products SET is_available=? WHERE id=?");
    $stmt->bind_param("ii", $status, $id);
    $stmt->execute();
    echo json_encode(["status" => "success"]);
}

// 5. GET CATEGORIES
if ($action == 'get_categories') {
    if(!isLogged()) { echo json_encode([]); exit; }
    $result = $conn->query("SELECT * FROM categories ORDER BY sort_order ASC");
    $cats = [];
    while($row = $result->fetch_assoc()) $cats[] = $row;
    echo json_encode($cats);
}

// 6. SAVE CATEGORY
if ($action == 'save_category') {
    if (!isAdmin()) exit;
    $data = json_decode(file_get_contents("php://input"), true);
    $id = $data['id'] ?? null; $name = $data['name']; $icon = $data['icon'];
    
    if ($id) {
        $oldName = $conn->query("SELECT name FROM categories WHERE id=$id")->fetch_assoc()['name'];
        $stmt = $conn->prepare("UPDATE categories SET name=?, icon=? WHERE id=?");
        $stmt->bind_param("ssi", $name, $icon, $id);
        $stmt->execute();
        $conn->query("UPDATE products SET category='$name' WHERE category='$oldName'");
    } else {
        $stmt = $conn->prepare("INSERT INTO categories (name, icon) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $icon);
        $stmt->execute();
    }
    echo json_encode(["status" => "success"]);
}

// 7. DELETE CATEGORY
if ($action == 'delete_category') {
    if (!isAdmin()) exit;
    $id = $_POST['id'];
    $conn->query("DELETE FROM categories WHERE id=$id");
    echo json_encode(["status" => "success"]);
}

// 8. REORDER
if ($action == 'reorder_products' || $action == 'reorder_categories') {
    if (!isAdmin()) exit;
    $data = json_decode(file_get_contents("php://input"), true);
    $table = ($action == 'reorder_products') ? 'products' : 'categories';
    $stmt = $conn->prepare("UPDATE $table SET sort_order=? WHERE id=?");
    foreach ($data['items'] as $index => $item) {
        $stmt->bind_param("ii", $index, $item['id']);
        $stmt->execute();
    }
    echo json_encode(["status" => "success"]);
}
?>