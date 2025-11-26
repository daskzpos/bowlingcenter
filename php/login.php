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
        if (empty($data['email']) || empty($data['wachtwoord'])) {
            echo json_encode(['success' => false, 'message' => 'E-mail en wachtwoord zijn verplicht']);
            exit;
        }
        
        $email = trim($data['email']);
        $wachtwoord = $data['wachtwoord'];
        
        $conn = getDBConnection();
        
        // Try to find user in all three tables
        $user = null;
        $table = null;
        
        // Check users table
        $stmt = $conn->prepare("SELECT user_id as id, first_name, last_name, email, password_hash, role FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $table = 'users';
        }
        $stmt->close();
        
        // Check employee table if not found
        if (!$user) {
            $stmt = $conn->prepare("SELECT employee_id as id, first_name, last_name, email, password_hash, role FROM employee WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                $user['role'] = 'employee'; // Override role
                $table = 'employee';
            }
            $stmt->close();
        }
        
        // Check directie table if not found
        if (!$user) {
            $stmt = $conn->prepare("SELECT derectie_id as id, first_name, last_name, email, password_hash, role FROM directie WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                $user['role'] = 'directie'; // Override role
                $table = 'directie';
            }
            $stmt->close();
        }
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'Onjuiste inloggegevens']);
            $conn->close();
            exit;
        }
        
        // Verify password
        if (password_verify($wachtwoord, $user['password_hash'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
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
                case 'employee':
                case 'medewerker':
                    $redirect = '/pages/dashboardMedewerker.html';
                    break;
                case 'user':
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
        
        $conn->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Ongeldige aanvraag']);
    }
} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Er is een fout opgetreden bij het inloggen. Controleer of de database actief is.']);
}
?>
