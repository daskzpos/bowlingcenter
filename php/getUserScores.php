<?php
require_once 'config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Niet ingelogd']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $conn = getDBConnection();
    
    // Get all reservations for this user
    // Note: reservations table uses 'reservation_id' and 'user_id'
    $sql = "SELECT r.reservation_id, r.date, r.start_time, r.end_time, r.user_id FROM reservations r WHERE r.user_id = ? ORDER BY r.date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $reservations = $stmt->get_result();
    
    $debug_info = [
        'user_id_searched' => $user_id,
        'reservations_found' => $reservations->num_rows
    ];

$scores = [];
$debug_reservations = [];
while ($reservation = $reservations->fetch_assoc()) {
    $reservation_id = $reservation['reservation_id'];
    $score_sql = "SELECT player_name, score FROM scores WHERE reservation_id = ?";
    $score_stmt = $conn->prepare($score_sql);
    $score_stmt->bind_param('i', $reservation_id);
    $score_stmt->execute();
    $score_result = $score_stmt->get_result();
    $players = [];
    while ($row = $score_result->fetch_assoc()) {
        $players[] = $row;
    }
    $debug_reservations[] = [
        'reservation_id' => $reservation_id,
        'players_count' => count($players)
    ];
    // Only add to scores array if there are players with scores
    if (count($players) > 0) {
        $scores[] = [
            'reservationDate' => $reservation['date'],
            'start_time' => $reservation['start_time'],
            'end_time' => $reservation['end_time'],
            'players' => $players
        ];
    }
}

$debug_info['reservations_checked'] = $debug_reservations;
$debug_info['scores_with_data'] = count($scores);

$conn->close();

header('Content-Type: application/json');
echo json_encode(['success' => true, 'scores' => $scores, 'debug' => $debug_info]);

} catch (Exception $e) {
    error_log('getUserScores.php error: ' . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Serverfout: ' . $e->getMessage()]);
}
