<?php
header('Content-Type: application/json');
require_once 'config.php';

try {
    // Check if user is logged in
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        echo json_encode(['success' => false, 'message' => 'Niet ingelogd']);
        exit;
    }
    
    // Get request data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['reservation_id']) || !isset($data['start_time']) || 
        !isset($data['end_time']) || !isset($data['number_of_adults']) || 
        !isset($data['number_of_kids'])) {
        echo json_encode(['success' => false, 'message' => 'Onvolledige gegevens']);
        exit;
    }
    
    $reservation_id = intval($data['reservation_id']);
    $start_time = $data['start_time'];
    $end_time = $data['end_time'];
    $number_of_adults = intval($data['number_of_adults']);
    $number_of_kids = intval($data['number_of_kids']);
    $user_id = $_SESSION['user_id'];
    
    // Validate input
    if ($number_of_adults < 1) {
        echo json_encode(['success' => false, 'message' => 'Er moet minimaal 1 volwassene zijn']);
        exit;
    }
    
    if ($number_of_kids < 0) {
        echo json_encode(['success' => false, 'message' => 'Aantal kinderen kan niet negatief zijn']);
        exit;
    }
    
    // Validate time format
    if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $start_time) || 
        !preg_match('/^\d{2}:\d{2}:\d{2}$/', $end_time)) {
        echo json_encode(['success' => false, 'message' => 'Ongeldige tijd formaat']);
        exit;
    }
    
    // Validate that start time is before end time
    if (strtotime($start_time) >= strtotime($end_time)) {
        echo json_encode(['success' => false, 'message' => 'Begintijd moet voor eindtijd zijn']);
        exit;
    }
    
    $conn = getDBConnection();
    
    // Check if reservation exists and belongs to the user (or user is employee/director)
    $stmt = $conn->prepare("SELECT user_id, date FROM reservations WHERE reservation_id = ?");
    $stmt->bind_param("i", $reservation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'Reservering niet gevonden']);
        exit;
    }
    
    $reservation = $result->fetch_assoc();
    $stmt->close();
    
    // Check authorization
    $is_employee = isset($_SESSION['role']) && ($_SESSION['role'] === 'employee' || $_SESSION['role'] === 'director');
    if ($reservation['user_id'] != $user_id && !$is_employee) {
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'Geen toegang om deze reservering te bewerken']);
        exit;
    }
    
    // Check if reservation is in the past
    $reservation_datetime = $reservation['date'] . ' ' . $start_time;
    if (strtotime($reservation_datetime) < time()) {
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'Kan geen reserveringen in het verleden bewerken']);
        exit;
    }
    
    // Calculate how many lanes are needed based on total people
    $total_people = $number_of_adults + $number_of_kids;
    $lanes_needed = ceil($total_people / 6); // 6 people per lane
    
    // Find available lanes for the new time slot (excluding current reservation's lanes)
    $stmt = $conn->prepare("SELECT l.lane_id, l.lane_number, l.kinder 
                            FROM lane l 
                            WHERE l.lane_id NOT IN (
                                SELECT rl.lane_id 
                                FROM reervation_lane rl
                                JOIN reservations r ON rl.reservation_id = r.reservation_id
                                WHERE r.reservation_id != ?
                                AND r.date = ? 
                                AND NOT (r.end_time <= ? OR r.start_time >= ?)
                                AND r.status = 1
                            )
                            ORDER BY l.lane_number ASC");
    
    $stmt->bind_param("isss", 
        $reservation_id,
        $reservation['date'],
        $start_time,
        $end_time
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
    $has_kids = ($number_of_kids > 0);
    
    if ($has_kids) {
        // Prioritize child lanes when kids are present
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
        $conn->close();
        echo json_encode([
            'success' => false, 
            'message' => "Niet genoeg banen beschikbaar voor {$total_people} personen. Kon maar " . count($assigned_lanes) . " van de " . $lanes_needed . " benodigde banen toewijzen."
        ]);
        exit;
    }
    
    // Delete old lane assignments
    $stmt = $conn->prepare("DELETE FROM reervation_lane WHERE reservation_id = ?");
    $stmt->bind_param("i", $reservation_id);
    $stmt->execute();
    $stmt->close();
    
    // Insert new lane assignments
    foreach ($assigned_lanes as $lane) {
        // Get next id for reervation_lane
        $result = $conn->query("SELECT COALESCE(MAX(id), 0) + 1 as next_id FROM reervation_lane");
        $row = $result->fetch_assoc();
        $rl_id = $row['next_id'];
        
        // Get the reservation details for foreign keys
        $stmt = $conn->prepare("SELECT user_id, employee_employee_id, directie_derectie_id FROM reservations WHERE reservation_id = ?");
        $stmt->bind_param("i", $reservation_id);
        $stmt->execute();
        $res_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $stmt = $conn->prepare("INSERT INTO reervation_lane (id, reservation_id, lane_id, created_at, reservations_reservation_id, reservations_user_id, reservations_employee_employee_id, reservations_users_user_id, reservations_directie_derectie_id, lane_lane_id) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("iiiiiiiii", 
            $rl_id,
            $reservation_id,
            $lane['lane_id'],
            $reservation_id,
            $res_data['user_id'],
            $res_data['employee_employee_id'],
            $res_data['user_id'],
            $res_data['directie_derectie_id'],
            $lane['lane_id']
        );
        
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            $conn->close();
            error_log("Error inserting lane assignment: " . $error);
            echo json_encode(['success' => false, 'message' => 'Fout bij toewijzen banen: ' . $error]);
            exit;
        }
        $stmt->close();
    }
    
    // Update the reservation
    $stmt = $conn->prepare("UPDATE reservations 
                            SET start_time = ?, end_time = ?, number_of_adults = ?, number_of_kids = ?
                            WHERE reservation_id = ?");
    $stmt->bind_param("ssiii", $start_time, $end_time, $number_of_adults, $number_of_kids, $reservation_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        echo json_encode(['success' => true, 'message' => 'Reservering succesvol bijgewerkt']);
    } else {
        $error = $stmt->error;
        $stmt->close();
        $conn->close();
        error_log("Error updating reservation: " . $error);
        echo json_encode(['success' => false, 'message' => 'Database fout: ' . $error]);
    }
    
} catch (Exception $e) {
    error_log("Error updating reservation: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
