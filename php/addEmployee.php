<?php
header('Content-Type: application/json');
require_once 'config.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Check if user is logged in as directie
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        echo json_encode(['success' => false, 'message' => 'Niet ingelogd']);
        exit;
    }
    
    $role = $_SESSION['rol'] ?? '';
    if ($role !== 'directie') {
        echo json_encode(['success' => false, 'message' => 'Geen toegang. Alleen directie kan medewerkers toevoegen. Huidige rol: ' . $role]);
        exit;
    }
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (empty($input['first_name']) || empty($input['last_name']) || empty($input['email']) || empty($input['password'])) {
        echo json_encode(['success' => false, 'message' => 'Voornaam, achternaam, e-mail en wachtwoord zijn verplicht']);
        exit;
    }
    
    $firstName = trim($input['first_name']);
    $lastName = trim($input['last_name']);
    $email = trim($input['email']);
    $role = trim($input['role'] ?? 'medewerker');
    $password = $input['password'];
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Ongeldig e-mailadres']);
        exit;
    }
    
    // Map role to database value
    $roleMap = [
        'Medewerker' => 'medewerker',
        'medewerker' => 'medewerker',
        'Directie' => 'directie',
        'directie' => 'directie'
    ];
    
    $dbRole = $roleMap[$role] ?? 'medewerker';
    
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
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Determine which table to insert into based on role
    if ($dbRole === 'directie') {
        // Insert into directie table - get next ID
        $stmt = $conn->prepare("SELECT COALESCE(MAX(derectie_id), 0) + 1 as next_id FROM directie");
        $stmt->execute();
        $result = $stmt->get_result();
        $nextId = $result->fetch_assoc()['next_id'];
        $stmt->close();
        
        $stmt = $conn->prepare("INSERT INTO directie (derectie_id, first_name, last_name, email, password_hash, phone_number, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 0, 'directie', NOW(), NOW())");
        $stmt->bind_param("issss", $nextId, $firstName, $lastName, $email, $hashedPassword);
    } else {
        // Insert into employee table - get next ID
        $stmt = $conn->prepare("SELECT COALESCE(MAX(employee_id), 0) + 1 as next_id FROM employee");
        $stmt->execute();
        $result = $stmt->get_result();
        $nextId = $result->fetch_assoc()['next_id'];
        $stmt->close();
        
        $stmt = $conn->prepare("INSERT INTO employee (employee_id, first_name, last_name, email, password_hash, phone_number, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 0, 'medewerker', NOW(), NOW())");
        $stmt->bind_param("issss", $nextId, $firstName, $lastName, $email, $hashedPassword);
    }
    
    if ($stmt->execute()) {
        $userId = $nextId;
        $stmt->close();
        $conn->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Medewerker succesvol toegevoegd',
            'user_id' => $userId
        ]);
    } else {
        $error = $stmt->error;
        $stmt->close();
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'Fout bij toevoegen medewerker: ' . $error]);
    }
    
} catch (Exception $e) {
    error_log("Error adding employee: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Er is een fout opgetreden: ' . $e->getMessage()]);
}
?>
