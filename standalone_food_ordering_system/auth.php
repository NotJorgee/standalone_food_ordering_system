<?php
session_start();
include 'db_connect.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// 1. LOGIN
if ($action == 'login') {
    $data = json_decode(file_get_contents("php://input"), true);
    $user = $data['username'];
    $pass = md5($data['password']); 
    $appType = $data['app'] ?? 'admin'; // 'kiosk' or 'admin'

    $stmt = $conn->prepare("SELECT id, role FROM users WHERE username=? AND password=?");
    $stmt->bind_param("ss", $user, $pass);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // STORE IN SEPARATE SESSIONS
        if ($appType === 'kiosk') {
            $_SESSION['kiosk_session'] = ['id' => $row['id'], 'role' => $row['role']];
        } else {
            $_SESSION['admin_session'] = ['id' => $row['id'], 'role' => $row['role']];
        }
        echo json_encode(["status" => "success", "role" => $row['role']]);
    } else {
        echo json_encode(["status" => "error"]);
    }
}

// 2. CHECK SESSION (Specific to App)
if ($action == 'check_session') {
    $appType = $_GET['app'] ?? 'admin';

    if ($appType === 'kiosk' && isset($_SESSION['kiosk_session'])) {
        echo json_encode(["status" => "logged_in", "role" => $_SESSION['kiosk_session']['role']]);
    } 
    elseif ($appType === 'admin' && isset($_SESSION['admin_session'])) {
        echo json_encode(["status" => "logged_in", "role" => $_SESSION['admin_session']['role']]);
    } 
    else {
        echo json_encode(["status" => "logged_out"]);
    }
}

// 3. LOGOUT (Specific to App)
if ($action == 'logout') {
    $appType = $_GET['app'] ?? 'admin';
    if ($appType === 'kiosk') unset($_SESSION['kiosk_session']);
    else unset($_SESSION['admin_session']);
    echo json_encode(["status" => "success"]);
}

// 4. USERS MANAGEMENT (Admin Only)
function isAdmin() {
    return isset($_SESSION['admin_session']) && $_SESSION['admin_session']['role'] === 'admin';
}

if ($action == 'get_users') {
    if (!isAdmin()) { echo json_encode([]); exit; }
    $result = $conn->query("SELECT id, username, role, created_at FROM users");
    $users = [];
    while($row = $result->fetch_assoc()) $users[] = $row;
    echo json_encode($users);
}

if ($action == 'add_user') {
    if (!isAdmin()) exit;
    $data = json_decode(file_get_contents("php://input"), true);
    
    // Check if user already exists
    $check = $conn->prepare("SELECT id FROM users WHERE username=?");
    $check->bind_param("s", $data['username']);
    $check->execute();
    if($check->get_result()->num_rows > 0) {
        echo json_encode(["status"=>"error", "message"=>"Username already taken"]);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    $pass = md5($data['password']);
    $stmt->bind_param("sss", $data['username'], $pass, $data['role']);
    echo $stmt->execute() ? json_encode(["status"=>"success"]) : json_encode(["status"=>"error"]);
}

// --- NEW UPDATE USER LOGIC ---
if ($action == 'update_user') {
    if (!isAdmin()) exit;
    $data = json_decode(file_get_contents("php://input"), true);
    
    $id = $data['id'];
    $user = $data['username'];
    $role = $data['role'];
    
    // Check if password was provided
    if (isset($data['password']) && !empty($data['password'])) {
        $pass = md5($data['password']);
        $stmt = $conn->prepare("UPDATE users SET username=?, role=?, password=? WHERE id=?");
        $stmt->bind_param("sssi", $user, $role, $pass, $id);
    } else {
        // Keep old password
        $stmt = $conn->prepare("UPDATE users SET username=?, role=? WHERE id=?");
        $stmt->bind_param("ssi", $user, $role, $id);
    }
    
    echo $stmt->execute() ? json_encode(["status"=>"success"]) : json_encode(["status"=>"error"]);
}
// -----------------------------

if ($action == 'delete_user') {
    if (!isAdmin()) exit;
    $id = $_POST['id'];
    if ($id == $_SESSION['admin_session']['id']) { echo json_encode(["status"=>"error", "message"=>"Cannot delete self"]); exit; }
    $conn->query("DELETE FROM users WHERE id=$id");
    echo json_encode(["status"=>"success"]);
}
?>