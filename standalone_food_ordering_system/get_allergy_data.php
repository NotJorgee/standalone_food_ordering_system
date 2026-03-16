<?php
// This script connects to the OTHER database (AllergyPass) to get the dictionary
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");

$host = "localhost";
$user = "root";
$pass = "";
$db = "allergypass_db"; // <--- Note: Connecting to the Allergy DB

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die(json_encode([])); // Return empty if can't connect
}

// 1. Fetch Common Allergens and their Keywords
$dictionary = [];

$sql = "SELECT ca.name as allergen, ck.word 
        FROM common_allergens ca 
        JOIN common_keywords ck ON ca.id = ck.common_allergen_id";

$result = $conn->query($sql);

while($row = $result->fetch_assoc()) {
    $allergen = $row['allergen'];
    $word = strtolower($row['word']);
    
    if (!isset($dictionary[$allergen])) {
        $dictionary[$allergen] = [];
    }
    // Add the allergen name itself as a keyword (e.g. "Dairy" matches "Dairy")
    if (!in_array(strtolower($allergen), $dictionary[$allergen])) {
        $dictionary[$allergen][] = strtolower($allergen);
    }
    $dictionary[$allergen][] = $word;
}

echo json_encode($dictionary);
?>