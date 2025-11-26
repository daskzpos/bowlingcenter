<?php
header('Content-Type: application/json');
require_once 'config.php';

try {
    $response = [
        'logged_in' => false,
        'user' => null
    ];
    
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
        $response['logged_in'] = true;
        $response['user'] = [
            'first_name' => $_SESSION['voornaam'] ?? '',
            'last_name' => $_SESSION['achternaam'] ?? '',
            'email' => $_SESSION['email'] ?? '',
            'phone_number' => $_SESSION['phone_number'] ?? ''
        ];
    }
    
    echo json_encode($response);
} catch (Exception $e) {
    error_log("Error in getReservationData: " . $e->getMessage());
    echo json_encode(['logged_in' => false, 'user' => null]);
}
?>
