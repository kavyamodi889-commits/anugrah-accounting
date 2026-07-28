<?php
session_start();
require_once 'db_config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

// Get filter parameters
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';
$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';

// Build query with filters
$whereConditions = array();
$params = array();
$types = '';

if ($statusFilter != 'all') {
    $whereConditions[] = "is_active = ?";
    $params[] = ($statusFilter == 'active') ? 1 : 0;
    $types .= 'i';
}

if (!empty($searchQuery)) {
    $whereConditions[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ? OR company_name LIKE ?)";
    $searchParam = "%$searchQuery%";
    $params = array_merge($params, array($searchParam, $searchParam, $searchParam, $searchParam));
    $types .= 'ssss';
}

if (!empty($whereConditions)) {
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
} else {
    $whereClause = "";
}

// Build query
$query = "SELECT * FROM users ";
if (!empty($whereClause)) {
    $query .= $whereClause . " ";
}
$query .= "ORDER BY created_at DESC";

// Execute query
if (!empty($params)) {
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $refs = array();
        $refs[] = $types;
        foreach($params as $key => $value) {
            $refs[] = &$params[$key];
        }
        call_user_func_array(array($stmt, 'bind_param'), $refs);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        die("Error preparing statement");
    }
} else {
    $result = $conn->query($query);
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=users_export_' . date('Y-m-d_H-i-s') . '.csv');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for proper Excel UTF-8 encoding
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add CSV headers
fputcsv($output, array(
    'ID',
    'Name',
    'Email',
    'Phone',
    'Company Name',
    'PAN',
    'GSTIN',
    'Address',
    'City',
    'State',
    'Pincode',
    'Status',
    'Registration Date'
));

// Add data rows
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, array(
            $row['id'],
            $row['name'],
            $row['email'],
            $row['phone'],
            $row['company_name'],
            $row['pan'],
            $row['gstin'],
            $row['address'],
            $row['city'],
            $row['state'],
            $row['pincode'],
            $row['is_active'] ? 'Active' : 'Inactive',
            date('Y-m-d H:i:s', strtotime($row['created_at']))
        ));
    }
}

fclose($output);
exit();
?>