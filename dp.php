Database connection file (
includes/db.php
):
<?php
require_once 'config.php';

// Create database connection
function getDbConnection() {
    static $conn;
    
    if ($conn === null) {
        $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if (!$conn) {
            die("Connection failed: " . mysqli_connect_error());
        }
        
        mysqli_set_charset($conn, "utf8mb4");
    }
    
    return $conn;
}

// Execute query and return result
function executeQuery($sql, $params = []) {
    $conn = getDbConnection();
    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt === false) {
        die("Error preparing statement: " . mysqli_error($conn));
    }
    
    if (!empty($params)) {
        $types = str_repeat('s', count($params)); // Assume all strings for simplicity
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    return $result;
}

// Fetch all rows from a query
function fetchAll($sql, $params = []) {
    $result = executeQuery($sql, $params);
    $rows = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    
    return $rows;
}

// Fetch a single row
function fetchOne($sql, $params = []) {
    $result = executeQuery($sql, $params);
    return mysqli_fetch_assoc($result);
}

// Insert data and return the last inserted ID
function insert($sql, $params = []) {
    $conn = getDbConnection();
    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt === false) {
        die("Error preparing statement: " . mysqli_error($conn));
    }
    
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    
    mysqli_stmt_execute($stmt);
    return mysqli_insert_id($conn);
}

// Update data and return the number of affected rows
function update($sql, $params = []) {
    $conn = getDbConnection();
    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt === false) {
        die("Error preparing statement: " . mysqli_error($conn));
    }
    
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    
    mysqli_stmt_execute($stmt);
    return mysqli_affected_rows($conn);
}

// Delete data and return the number of affected rows
function delete($sql, $params = []) {
    return update($sql, $params);
}
?>
