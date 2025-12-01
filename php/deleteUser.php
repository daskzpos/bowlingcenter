<?php
// Start output buffering to prevent any unwanted output
ob_start();

require_once 'config.php';

// Clear any output that might have been generated
ob_end_clean();

// Now set the JSON header
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Get JSON data
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate input
        if (empty($data['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Gebruiker ID is verplicht']);
            exit;
        }
        
        $user_id = $data['user_id'];
        
        $conn = getDBConnection();
        
        // Delete user from users table
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Gebruiker succesvol verwijderd']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gebruiker niet gevonden']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Database fout: ' . $stmt->error]);
        }
        
        $stmt->close();
        $conn->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Ongeldige aanvraag']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server fout: ' . $e->getMessage()]);
}
?>
