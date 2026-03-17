<?php
session_start();
include 'db_connect.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// 1. LOGIN (Updated for new RBAC DB)
if ($action == 'login') {
    $data = json_decode(file_get_contents("php://input"), true);
    $user = $data['username'];
    $pass = md5($data['password']); 
    $appType = $data['app'] ?? 'admin'; 

    // NEW: Join with roles table
    $stmt = $conn->prepare("SELECT u.id, r.role_name as role FROM users u JOIN roles r ON u.role_id = r.id WHERE u.username=? AND u.password=?");
    $stmt->bind_param("ss", $user, $pass);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if ($appType === 'kiosk') {
            $_SESSION['kiosk_session'] = ['id' => $row['id'], 'role' => $row['role']];
        } else {
            $_SESSION['admin_session'] = ['id' => $row['id'], 'role' => $row['role']];
        }
        echo json_encode(["status" => "success", "role" => $row['role']]);
    } else {
        echo json_encode(["status" => "error"]);
    }
    exit;
}

// 2. CHECK SESSION (Updated to send allowed modules to the Dashboard)
if ($action == 'check_session') {
    $appType = $_GET['app'] ?? 'admin';

    if ($appType === 'kiosk' && isset($_SESSION['kiosk_session'])) {
        echo json_encode(["status" => "logged_in", "role" => $_SESSION['kiosk_session']['role']]);
    } 
    elseif ($appType === 'admin' && isset($_SESSION['admin_session'])) {
        $role = $_SESSION['admin_session']['role'];
        
        // Fetch dynamic modules for this specific role
        $stmt = $conn->prepare("SELECT p.module_name FROM roles r JOIN role_permissions rp ON r.id = rp.role_id JOIN permissions p ON rp.permission_id = p.id WHERE r.role_name = ?");
        $stmt->bind_param("s", $role);
        $stmt->execute();
        $res = $stmt->get_result();
        
        $allowed_modules = [];
        while($row = $res->fetch_assoc()) {
            $allowed_modules[] = $row['module_name'];
        }

        echo json_encode(["status" => "logged_in", "role" => $role, "allowed_modules" => $allowed_modules]);
    } 
    else {
        echo json_encode(["status" => "logged_out"]);
    }
    exit;
}

// 3. LOGOUT
if ($action == 'logout') {
    $appType = $_GET['app'] ?? 'admin';
    if ($appType === 'kiosk') unset($_SESSION['kiosk_session']);
    else unset($_SESSION['admin_session']);
    echo json_encode(["status" => "success"]);
    exit;
}

// 4. USERS MANAGEMENT (Admin Only)
function isAdmin() {
    return isset($_SESSION['admin_session']) && $_SESSION['admin_session']['role'] === 'admin';
}

// GET USERS (Updated for new RBAC DB)
if ($action == 'get_users') {
    if (!isAdmin()) { echo json_encode([]); exit; }
    
    // NEW: Join with roles table to get the name
    $result = $conn->query("SELECT u.id, u.username, r.role_name as role FROM users u JOIN roles r ON u.role_id = r.id");
    $users = [];
    if ($result) {
        while($row = $result->fetch_assoc()) {
            $row['created_at'] = 'N/A'; // Safe fallback
            $users[] = $row;
        }
    }
    echo json_encode($users);
    exit;
}

// ADD USER (Updated to save role_id instead of role text)
if ($action == 'add_user') {
    if (!isAdmin()) exit;
    $data = json_decode(file_get_contents("php://input"), true);
    
    $check = $conn->prepare("SELECT id FROM users WHERE username=?");
    $check->bind_param("s", $data['username']);
    $check->execute();
    if($check->get_result()->num_rows > 0) {
        echo json_encode(["status"=>"error", "message"=>"Username already taken"]);
        exit;
    }

    // Convert string 'admin'/'staff' to ID (1 or 2)
    $role_id = ($data['role'] === 'admin') ? 1 : 2;
    $pass = md5($data['password']);

    $stmt = $conn->prepare("INSERT INTO users (username, password, role_id) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $data['username'], $pass, $role_id);
    echo $stmt->execute() ? json_encode(["status"=>"success"]) : json_encode(["status"=>"error"]);
    exit;
}

// UPDATE USER (Updated to save role_id)
if ($action == 'update_user') {
    if (!isAdmin()) exit;
    $data = json_decode(file_get_contents("php://input"), true);
    
    $id = $data['id'];
    $user = $data['username'];
    $role_id = ($data['role'] === 'admin') ? 1 : 2;
    
    if (isset($data['password']) && !empty($data['password'])) {
        $pass = md5($data['password']);
        $stmt = $conn->prepare("UPDATE users SET username=?, role_id=?, password=? WHERE id=?");
        $stmt->bind_param("sisi", $user, $role_id, $pass, $id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET username=?, role_id=? WHERE id=?");
        $stmt->bind_param("sii", $user, $role_id, $id);
    }
    
    echo $stmt->execute() ? json_encode(["status"=>"success"]) : json_encode(["status"=>"error"]);
    exit;
}

// DELETE USER
if ($action == 'delete_user') {
    if (!isAdmin()) exit;
    $id = $_POST['id'];
    if ($id == $_SESSION['admin_session']['id']) { 
        echo json_encode(["status"=>"error", "message"=>"Cannot delete self"]); exit; 
    }
    $conn->query("DELETE FROM users WHERE id=$id");
    echo json_encode(["status"=>"success"]);
    exit;
}
?>