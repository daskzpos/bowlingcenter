<?php
header('Content-Type: application/json');
require_once 'config.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Check if user is logged in as directie or medewerker
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        echo json_encode(['success' => false, 'message' => 'Niet ingelogd']);
        exit;
    }
    
    $role = $_SESSION['rol'] ?? '';
    if ($role !== 'directie' && $role !== 'medewerker' && $role !== 'employee') {
        echo json_encode(['success' => false, 'message' => 'Geen toegang. Alleen directie en medewerkers kunnen klanten toevoegen.']);
        exit;
    }
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (empty($input['first_name']) || empty($input['last_name']) || empty($input['email']) || empty($input['phone'])) {
        echo json_encode(['success' => false, 'message' => 'Voornaam, achternaam, e-mail en telefoonnummer zijn verplicht']);
        exit;
    }
    
    $firstName = trim($input['first_name']);
    $lastName = trim($input['last_name']);
    $email = trim($input['email']);
    $phone = trim($input['phone']);
    
    // Convert phone number to integer (remove spaces and non-digits)
    $phoneNumber = preg_replace('/\D/', '', $phone);
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Ongeldig e-mailadres']);
        exit;
    }
    
    $conn = getDBConnection();
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stmt->close();
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'Dit e-mailadres is al in gebruik']);
        exit;
    }
    $stmt->close();
    
    // Generate a default password
    $defaultPassword = bin2hex(random_bytes(8)); // Generate random password
    $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);
    
    // Get next user_id
    $stmt = $conn->prepare("SELECT COALESCE(MAX(user_id), 0) + 1 as next_id FROM users");
    $stmt->execute();
    $result = $stmt->get_result();
    $nextId = $result->fetch_assoc()['next_id'];
    $stmt->close();
    
    // Insert new customer with role 'gast'
    $stmt = $conn->prepare("INSERT INTO users (user_id, first_name, last_name, email, phone_number, password_hash, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 'gast', NOW(), NOW())");
    $stmt->bind_param("isssis", $nextId, $firstName, $lastName, $email, $phoneNumber, $hashedPassword);
    
    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Klant succesvol toegevoegd',
            'user_id' => $nextId,
            'default_password' => $defaultPassword
        ]);
    } else {
        $error = $stmt->error;
        $stmt->close();
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'Fout bij toevoegen klant: ' . $error]);
    }
    
} catch (Exception $e) {
    error_log("Error adding customer: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Er is een fout opgetreden: ' . $e->getMessage()]);
}
?>
