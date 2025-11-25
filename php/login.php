<?php
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    if (empty($data['email']) || empty($data['wachtwoord'])) {
        echo json_encode(['success' => false, 'message' => 'E-mail en wachtwoord zijn verplicht']);
        exit;
    }
    
    $email = trim($data['email']);
    $wachtwoord = $data['wachtwoord'];
    
    $conn = getDBConnection();
    
    // Get user from database
    $stmt = $conn->prepare("SELECT user_id, first_name, last_name, email, password_hash, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Onjuiste inloggegevens']);
        $stmt->close();
        $conn->close();
        exit;
    }
    
    $user = $result->fetch_assoc();
    
    // Verify password
    if (password_verify($wachtwoord, $user['password_hash'])) {
        // Set session variables
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['voornaam'] = $user['first_name'];
        $_SESSION['achternaam'] = $user['last_name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['rol'] = $user['role'];
        $_SESSION['logged_in'] = true;
        
        // Determine redirect based on role
        $redirect = '';
        switch ($user['role']) {
            case 'directie':
                $redirect = '/pages/dashboardDirectie.html';
                break;
            case 'medewerker':
                $redirect = '/pages/dashboardMedewerker.html';
                break;
            case 'gast':
            default:
                $redirect = '/pages/dashboardUser.html';
                break;
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Succesvol ingelogd!',
            'redirect' => $redirect,
            'user' => [
                'voornaam' => $user['first_name'],
                'achternaam' => $user['last_name'],
                'rol' => $user['role']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Onjuiste inloggegevens']);
    }
    
    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Ongeldige aanvraag']);
}
?>
