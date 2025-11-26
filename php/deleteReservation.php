<?php
header('Content-Type: application/json');
require_once 'config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Ongeldige aanvraag']);
        exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['reservation_id'])) {
        echo json_encode(['success' => false, 'message' => 'Reservering ID is verplicht']);
        exit;
    }
    
    $reservation_id = $data['reservation_id'];
    $conn = getDBConnection();
    
    // Check if user is authorized to cancel
    // Either the user who made the reservation, or an employee/directie
    $is_authorized = false;
    
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
        $role = $_SESSION['rol'] ?? '';
        
        // Employee or directie can cancel any reservation
        if ($role === 'employee' || $role === 'medewerker' || $role === 'directie') {
            $is_authorized = true;
        } else {
            // Regular user can only cancel their own reservations
            $stmt = $conn->prepare("SELECT user_id FROM reservations WHERE reservation_id = ?");
            $stmt->bind_param("i", $reservation_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                if ($row['user_id'] == $_SESSION['user_id']) {
                    $is_authorized = true;
                }
            }
            $stmt->close();
        }
    }
    
    if (!$is_authorized) {
        echo json_encode(['success' => false, 'message' => 'Geen toegang om deze reservering te annuleren']);
        exit;
    }
    
    // Delete lane assignments first (due to foreign key constraints)
    $stmt = $conn->prepare("DELETE FROM reervation_lane WHERE reservation_id = ?");
    $stmt->bind_param("i", $reservation_id);
    $stmt->execute();
    $stmt->close();
    
    // Delete extras if any
    $stmt = $conn->prepare("DELETE FROM extras WHERE reservation_id = ?");
    $stmt->bind_param("i", $reservation_id);
    $stmt->execute();
    $stmt->close();
    
    // Delete the reservation
    $stmt = $conn->prepare("DELETE FROM reservations WHERE reservation_id = ?");
    $stmt->bind_param("i", $reservation_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Reservering succesvol geannuleerd']);
    } else {
        throw new Exception("Fout bij annuleren reservering: " . $stmt->error);
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    error_log("Delete reservation error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Er is een fout opgetreden bij het annuleren van de reservering']);
}
?>
