<?php
// Start output buffering to prevent any unwanted output
ob_start();

require_once 'config.php';

// Clear any output that might have been generated
ob_end_clean();

// Now set the JSON header
header('Content-Type: application/json');

try {
    $conn = getDBConnection();
    
    $employees = [];
    
    // Get employees from employee table
    $sql = "SELECT employee_id as id, first_name, last_name, email, phone_number, role, created_at FROM employee ORDER BY first_name ASC";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $employees[] = [
                'id' => $row['id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'email' => $row['email'],
                'phone_number' => $row['phone_number'] ?? 'N/A',
                'role' => $row['role'],
                'created_at' => $row['created_at']
            ];
        }
    }
    
    // Get directie from directie table
    $sql = "SELECT derectie_id as id, first_name, last_name, email, phone_number, role, created_at FROM directie ORDER BY first_name ASC";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $employees[] = [
                'id' => $row['id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'email' => $row['email'],
                'phone_number' => $row['phone_number'] ?? 'N/A',
                'role' => $row['role'],
                'created_at' => $row['created_at']
            ];
        }
    }
    
    // Sort by first name
    usort($employees, function($a, $b) {
        return strcmp($a['first_name'], $b['first_name']);
    });
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'employees' => $employees,
        'total' => count($employees)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Er is een fout opgetreden bij het ophalen van de medewerkers.',
        'error' => $e->getMessage()
    ]);
}
?>
