<?php
// Disable error display and catch errors
ini_set('display_errors', 0);
error_reporting(E_ALL);

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
        if (empty($data['voornaam']) || empty($data['achternaam']) || 
            empty($data['telefoonnummer']) || empty($data['email']) || 
            empty($data['wachtwoord'])) {
            echo json_encode(['success' => false, 'message' => 'Alle velden zijn verplicht']);
            exit;
        }
        
        $voornaam = trim($data['voornaam']);
        $achternaam = trim($data['achternaam']);
        $telefoonnummer = trim($data['telefoonnummer']);
        $email = trim($data['email']);
        $wachtwoord = $data['wachtwoord'];
        
        // Convert phone number to integer (remove spaces and non-digits)
        $telefoonnummer = preg_replace('/\D/', '', $telefoonnummer);
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Ongeldig e-mailadres']);
            exit;
        }
        
        // Validate password length
        if (strlen($wachtwoord) < 6) {
            echo json_encode(['success' => false, 'message' => 'Wachtwoord moet minimaal 6 karakters zijn']);
            exit;
        }
        
        $conn = getDBConnection();
        
        // Check if email already exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Dit e-mailadres is al geregistreerd']);
            $stmt->close();
            $conn->close();
            exit;
        }
        $stmt->close();
        
        // Hash password
        $hashed_password = password_hash($wachtwoord, PASSWORD_DEFAULT);
        
        // Insert new user
        $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, phone_number, email, password_hash, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'gast', NOW(), NOW())");
        $stmt->bind_param("ssiss", $voornaam, $achternaam, $telefoonnummer, $email, $hashed_password);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Account succesvol aangemaakt!']);
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
