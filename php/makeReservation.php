<?php
header('Content-Type: application/json');
require_once 'config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Ongeldige aanvraag']);
        exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['first_name', 'last_name', 'email', 'date', 'start_time', 'end_time', 'lanes', 'people'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            echo json_encode(['success' => false, 'message' => "Veld '$field' is verplicht"]);
            exit;
        }
    }
    
    $conn = getDBConnection();
    
    // Always check if a user exists with the provided email, regardless of who is logged in
    // This ensures the reservation is linked to the actual customer
    $user_id = null;
    
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $data['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // User exists, use their ID
        $user = $result->fetch_assoc();
        $user_id = $user['user_id'];
        
        // Update their info in case it changed
        $stmt_update = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, phone_number = ?, updated_at = NOW() WHERE user_id = ?");
        $phone = $data['phone_number'] ?? 0;
        $stmt_update->bind_param("ssii", $data['first_name'], $data['last_name'], $phone, $user_id);
        $stmt_update->execute();
        $stmt_update->close();
    } else {
        // Create a new user with the provided details
        $stmt_insert = $conn->prepare("INSERT INTO users (user_id, first_name, last_name, email, password_hash, phone_number, role, created_at, updated_at) VALUES (NULL, ?, ?, ?, '', ?, 'gast', NOW(), NOW())");
        $phone = $data['phone_number'] ?? 0;
        $stmt_insert->bind_param("sssi", $data['first_name'], $data['last_name'], $data['email'], $phone);
        
        if (!$stmt_insert->execute()) {
            throw new Exception("Fout bij aanmaken gebruiker: " . $stmt_insert->error);
        }
        
        $user_id = $conn->insert_id;
        $stmt_insert->close();
    }
    $stmt->close();
    
    // Get employee_id and directie_id (we'll use default values for now)
    // In a real system, you'd want to properly handle these relationships
    $employee_id = 1; // Default employee
    $directie_id = 1; // Default directie
    
    // Check for conflicting reservations
    // A conflict occurs when the requested time overlaps with existing reservations
    $stmt = $conn->prepare("SELECT COUNT(*) as conflict_count 
                            FROM reservations 
                            WHERE date = ? 
                            AND (
                                (start_time < ? AND end_time > ?) OR
                                (start_time < ? AND end_time > ?) OR
                                (start_time >= ? AND end_time <= ?)
                            )
                            AND status = 1");
    
    $stmt->bind_param("sssssss", 
        $data['date'],
        $data['end_time'], $data['start_time'],  // Check if existing starts before our end and ends after our start
        $data['end_time'], $data['end_time'],    // Check if existing starts during our time
        $data['start_time'], $data['end_time']   // Check if existing is completely within our time
    );
    $stmt->execute();
    $result = $stmt->get_result();
    $conflict = $result->fetch_assoc();
    $stmt->close();
    
    // Count how many lanes are needed vs available
    $lanes_needed = $data['lanes'] ?? 1;
    $lanes_occupied = $conflict['conflict_count'];
    $total_lanes = 8;
    
    if ($lanes_occupied + $lanes_needed > $total_lanes) {
        echo json_encode([
            'success' => false, 
            'message' => "Niet genoeg banen beschikbaar. Er zijn al {$lanes_occupied} banen gereserveerd op dit tijdstip. U vraagt {$lanes_needed} banen, maar er zijn maar " . ($total_lanes - $lanes_occupied) . " banen beschikbaar."
        ]);
        $conn->close();
        exit;
    }
    
    // Get the next reservation_id
    $result = $conn->query("SELECT COALESCE(MAX(reservation_id), 0) + 1 as next_id FROM reservations");
    $row = $result->fetch_assoc();
    $reservation_id = $row['next_id'];
    
    // Create the reservation
    $stmt = $conn->prepare("INSERT INTO reservations (reservation_id, user_id, date, start_time, end_time, number_of_adults, number_of_kids, notes, status, created_by_user_id, last_modified_by_user_id, created_at, updated_at, employee_employee_id, users_user_id, directie_derectie_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, NOW(), NOW(), ?, ?, ?)");
    
    $adults = $data['adults'] ?? 0;
    $kids = $data['kids'] ?? 0;
    $notes = $data['notes'] ?? '';
    
    $stmt->bind_param("iisssiisiiiii", 
        $reservation_id,
        $user_id,
        $data['date'], 
        $data['start_time'], 
        $data['end_time'], 
        $adults, 
        $kids, 
        $notes, 
        $user_id, 
        $user_id, 
        $employee_id, 
        $user_id, 
        $directie_id
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Fout bij aanmaken reservering: " . $stmt->error);
    }
    
    $stmt->close();
    
    // Automatically assign lanes
    $lanes_needed = $data['lanes'] ?? 1;
    $child_lanes_needed = $data['child_lanes'] ?? 0;
    
    // Find available lanes for this time slot
    $stmt = $conn->prepare("SELECT l.lane_id, l.lane_number, l.kinder 
                            FROM lane l 
                            WHERE l.lane_id NOT IN (
                                SELECT rl.lane_id 
                                FROM reervation_lane rl
                                JOIN reservations r ON rl.reservation_id = r.reservation_id
                                WHERE r.date = ? 
                                AND (
                                    (r.start_time < ? AND r.end_time > ?) OR
                                    (r.start_time < ? AND r.end_time > ?) OR
                                    (r.start_time >= ? AND r.end_time <= ?)
                                )
                                AND r.status = 1
                            )
                            ORDER BY l.lane_number ASC");
    
    $stmt->bind_param("sssssss", 
        $data['date'],
        $data['end_time'], $data['start_time'],
        $data['end_time'], $data['end_time'],
        $data['start_time'], $data['end_time']
    );
    $stmt->execute();
    $result = $stmt->get_result();
    
    $available_lanes = [];
    while ($row = $result->fetch_assoc()) {
        $available_lanes[] = $row;
    }
    $stmt->close();
    
    // Separate child lanes and regular lanes
    $child_lanes = array_filter($available_lanes, function($lane) {
        return $lane['kinder'] === 'ja';
    });
    $regular_lanes = array_filter($available_lanes, function($lane) {
        return $lane['kinder'] === 'nee';
    });
    
    $assigned_lanes = [];
    
    // If there are kids in the reservation, automatically prioritize child lanes
    $has_kids = ($kids > 0);
    
    if ($has_kids) {
        // Prioritize child lanes when kids are present
        // Assign all requested lanes from child lanes first if available
        $child_lanes_array = array_values($child_lanes);
        for ($i = 0; $i < $lanes_needed && $i < count($child_lanes_array); $i++) {
            $assigned_lanes[] = $child_lanes_array[$i];
        }
        
        // If we still need more lanes, use regular lanes
        $remaining_lanes_needed = $lanes_needed - count($assigned_lanes);
        $regular_lanes_array = array_values($regular_lanes);
        for ($i = 0; $i < $remaining_lanes_needed && $i < count($regular_lanes_array); $i++) {
            $assigned_lanes[] = $regular_lanes_array[$i];
        }
    } else {
        // No kids - use regular lanes first, then child lanes if needed
        $regular_lanes_array = array_values($regular_lanes);
        for ($i = 0; $i < $lanes_needed && $i < count($regular_lanes_array); $i++) {
            $assigned_lanes[] = $regular_lanes_array[$i];
        }
        
        // If we still need more lanes, use child lanes
        $remaining_lanes_needed = $lanes_needed - count($assigned_lanes);
        $child_lanes_array = array_values($child_lanes);
        for ($i = 0; $i < $remaining_lanes_needed && $i < count($child_lanes_array); $i++) {
            $assigned_lanes[] = $child_lanes_array[$i];
        }
    }
    
    // Check if we have enough lanes
    if (count($assigned_lanes) < $lanes_needed) {
        // Rollback the reservation
        $conn->query("DELETE FROM reservations WHERE reservation_id = $reservation_id");
        echo json_encode([
            'success' => false, 
            'message' => "Niet genoeg banen beschikbaar. Kon maar " . count($assigned_lanes) . " van de " . $lanes_needed . " gevraagde banen toewijzen."
        ]);
        $conn->close();
        exit;
    }
    
    // Insert lane assignments
    foreach ($assigned_lanes as $lane) {
        // Get next id for reervation_lane
        $result = $conn->query("SELECT COALESCE(MAX(id), 0) + 1 as next_id FROM reervation_lane");
        $row = $result->fetch_assoc();
        $rl_id = $row['next_id'];
        
        $stmt = $conn->prepare("INSERT INTO reervation_lane (id, reservation_id, lane_id, created_at, reservations_reservation_id, reservations_user_id, reservations_employee_employee_id, reservations_users_user_id, reservations_directie_derectie_id, lane_lane_id) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("iiiiiiiii", 
            $rl_id,
            $reservation_id,
            $lane['lane_id'],
            $reservation_id,
            $user_id,
            $employee_id,
            $user_id,
            $directie_id,
            $lane['lane_id']
        );
        
        $stmt->execute();
        $stmt->close();
    }
    
    // Add extras if selected
    if (isset($data['snackpakket_basis']) || isset($data['snackpakket_luxe']) || isset($data['vrijgezellenfeest']) || isset($data['kinderpartij'])) {
        // Get next extras_id
        $result = $conn->query("SELECT COALESCE(MAX(extras_id), 0) + 1 as next_id FROM extras");
        $row = $result->fetch_assoc();
        $extras_id = $row['next_id'];
        
        $stmt = $conn->prepare("INSERT INTO extras (extras_id, reservation_id, snackpakket_basis, snackpakket_luxe, vrijgezellenfeest, kinderpartij, reservations_reservation_id, reservations_user_id, reservations_employee_employee_id, reservations_users_user_id, reservations_directie_derectie_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $snackpakket_basis = ($data['snackpakket_basis'] ?? false) ? 1 : 0;
        $snackpakket_luxe = ($data['snackpakket_luxe'] ?? false) ? 1 : 0;
        $vrijgezellenfeest = ($data['vrijgezellenfeest'] ?? false) ? 1 : 0;
        $kinderpartij = ($data['kinderpartij'] ?? false) ? 1 : 0;
        
        $stmt->bind_param("iiiiiiiiiii", 
            $extras_id,
            $reservation_id, 
            $snackpakket_basis, 
            $snackpakket_luxe, 
            $vrijgezellenfeest, 
            $kinderpartij,
            $reservation_id,
            $user_id,
            $employee_id,
            $user_id,
            $directie_id
        );
        
        $stmt->execute();
        $stmt->close();
    }
    
    $conn->close();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Reservering succesvol aangemaakt!',
        'reservation_id' => $reservation_id
    ]);
    
} catch (Exception $e) {
    error_log("Reservation error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Er is een fout opgetreden bij het aanmaken van de reservering: ' . $e->getMessage()]);
}
?>
