<?php
/**
 * Konfigurasi Database
 * File ini berisi pengaturan koneksi database MySQL
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'wifi_monitoring');
define('DB_PORT', 3306);

// Create Connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Check Connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF8
$conn->set_charset("utf8");

// Function to execute query safely
function executeQuery($conn, $query) {
    $result = $conn->query($query);
    if (!$result) {
        return ['status' => 'error', 'message' => $conn->error];
    }
    return ['status' => 'success', 'result' => $result];
}

// Function to prepare and bind statement
function prepareStatement($conn, $query, $types = '', $params = []) {
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return ['status' => 'error', 'message' => $conn->error];
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    
    if ($stmt->error) {
        return ['status' => 'error', 'message' => $stmt->error];
    }
    
    return ['status' => 'success', 'stmt' => $stmt];
}

// Function to fetch single row
function fetchOne($result) {
    if (!$result) return null;
    return $result->fetch_assoc();
}

// Function to fetch all rows
function fetchAll($result) {
    if (!$result) return [];
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    return $data;
}

// Function to get last insert ID
function getLastInsertId($conn) {
    return $conn->insert_id;
}

// Function to get affected rows
function getAffectedRows($conn) {
    return $conn->affected_rows;
}

?>
