<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Get POST data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['date'])) {
    echo json_encode(['success' => false, 'message' => 'Datum is vereist']);
    exit;
}

$date = $data['date'];
$timeSlot = $data['time_slot'] ?? 'Alle tijden';

try {
    $conn = getDBConnection();
    
    // Define time ranges for time slots
    $timeRanges = [
        'Alle tijden' => ['00:00:00', '23:59:59'],
        '12:00-14:00' => ['12:00:00', '14:00:00'],
        '14:00-16:00' => ['14:00:00', '16:00:00'],
        '16:00-18:00' => ['16:00:00', '18:00:00'],
        '18:00-20:00' => ['18:00:00', '20:00:00'],
        '20:00-22:00' => ['20:00:00', '22:00:00']
    ];
    
    $startTime = $timeRanges[$timeSlot][0] ?? '00:00:00';
    $endTime = $timeRanges[$timeSlot][1] ?? '23:59:59';
    
    // Get all lanes
    $lanesQuery = "SELECT lane_number, kinder FROM lane ORDER BY lane_number";
    $lanesResult = $conn->query($lanesQuery);
    
    $lanes = [];
    
    while ($lane = $lanesResult->fetch_assoc()) {
        $laneNumber = $lane['lane_number'];
        $isChildLane = ($lane['kinder'] === 'yes' || $lane['kinder'] === '1');
        
        // Check if lane has any reservations in the specified time range
        $reservationQuery = "
            SELECT r.reservation_id, r.date, r.start_time, r.end_time,
                   u.first_name, u.last_name
            FROM reervation_lane rl
            JOIN reservations r ON rl.reservations_reservation_id = r.reservation_id
            LEFT JOIN users u ON r.users_user_id = u.user_id
            JOIN lane l ON rl.lane_lane_id = l.lane_id
            WHERE l.lane_number = ?
              AND r.date = ?
              AND (
                (r.start_time < ? AND r.end_time > ?)
                OR (r.start_time >= ? AND r.start_time < ?)
              )
            ORDER BY r.start_time ASC
            LIMIT 1
        ";
        
        $stmt = $conn->prepare($reservationQuery);
        $stmt->bind_param('isssss', $laneNumber, $date, $endTime, $startTime, $startTime, $endTime);
        $stmt->execute();
        $reservationResult = $stmt->get_result();
        
        $status = 'available';
        $nextReservation = null;
        
        if ($reservation = $reservationResult->fetch_assoc()) {
            $status = 'reserved';
            $nextReservation = [
                'start_time' => $reservation['start_time'],
                'end_time' => $reservation['end_time'],
                'customer_name' => $reservation['first_name'] . ' ' . $reservation['last_name']
            ];
        } else {
            // If no reservation in the time slot, find the next upcoming reservation today
            $nextQuery = "
                SELECT r.reservation_id, r.date, r.start_time, r.end_time,
                       u.first_name, u.last_name
                FROM reervation_lane rl
                JOIN reservations r ON rl.reservations_reservation_id = r.reservation_id
                LEFT JOIN users u ON r.users_user_id = u.user_id
                JOIN lane l ON rl.lane_lane_id = l.lane_id
                WHERE l.lane_number = ?
                  AND r.date = ?
                  AND r.start_time >= ?
                ORDER BY r.start_time ASC
                LIMIT 1
            ";
            
            $nextStmt = $conn->prepare($nextQuery);
            $nextStmt->bind_param('iss', $laneNumber, $date, $endTime);
            $nextStmt->execute();
            $nextResult = $nextStmt->get_result();
            
            if ($nextRes = $nextResult->fetch_assoc()) {
                $nextReservation = [
                    'start_time' => $nextRes['start_time'],
                    'end_time' => $nextRes['end_time'],
                    'customer_name' => $nextRes['first_name'] . ' ' . $nextRes['last_name']
                ];
            }
            $nextStmt->close();
        }
        
        $lanes[] = [
            'lane_number' => $laneNumber,
            'is_child_lane' => $isChildLane,
            'status' => $status,
            'next_reservation' => $nextReservation
        ];
        
        $stmt->close();
    }
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'lanes' => $lanes,
        'date' => $date,
        'time_slot' => $timeSlot
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
