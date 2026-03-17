<?php
session_start();
include 'db_connect.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// 1. LOGIN
if ($action == 'login') {
    $data = json_decode(file_get_contents("php://input"), true);
    $username = $data['username'];
    $password = md5($data['password']); 

    // NEW: Join with roles table to get role_name instead of the old enum
    $stmt = $conn->prepare("SELECT u.id, r.role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.username=? AND u.password=?");
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $_SESSION['admin_session'] = ['id' => $row['id'], 'role' => $row['role_name']];
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
    exit;
}

// 2. CHECK SESSION (Specific to App)
if ($action == 'check_session') {
    if (isset($_SESSION['admin_session'])) {
        $role = $_SESSION['admin_session']['role'];
        
        // NEW: Dynamically fetch what modules this role is allowed to see
        $stmt = $conn->prepare("SELECT p.module_name FROM roles r JOIN role_permissions rp ON r.id = rp.role_id JOIN permissions p ON rp.permission_id = p.id WHERE r.role_name = ?");
        $stmt->bind_param("s", $role);
        $stmt->execute();
        $res = $stmt->get_result();
        
        $allowed_modules = [];
        while($row = $res->fetch_assoc()) {
            $allowed_modules[] = $row['module_name'];
        }

        echo json_encode(['status' => 'logged_in', 'role' => $role, 'allowed_modules' => $allowed_modules]);
    } else {
        echo json_encode(['status' => 'logged_out']);
    }
    exit;
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
    if ($_SESSION['admin_session']['role'] !== 'admin') exit;
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
    if ($_SESSION['admin_session']['role'] !== 'admin') exit;
    $id = $_POST['id'];
    if ($id == $_SESSION['admin_session']['id']) { echo json_encode(["status"=>"error", "message"=>"Cannot delete self"]); exit; }
    $conn->query("DELETE FROM users WHERE id=$id");
    echo json_encode(["status"=>"success"]);
}
?>