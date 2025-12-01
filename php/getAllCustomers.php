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
    
    // Get all users from the database with role 'gast'
    $sql = "SELECT user_id as id, first_name, last_name, email, phone_number, role, created_at FROM users WHERE role = 'gast' ORDER BY first_name ASC";
    $result = $conn->query($sql);
    
    $customers = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $customers[] = [
                'id' => $row['id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'email' => $row['email'],
                'phone_number' => $row['phone_number'],
                'role' => $row['role'],
                'created_at' => $row['created_at']
            ];
        }
    }
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'customers' => $customers,
        'total' => count($customers)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Er is een fout opgetreden bij het ophalen van de klanten.',
        'error' => $e->getMessage()
    ]);
}
?>
