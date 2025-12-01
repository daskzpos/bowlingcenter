<?php
require_once 'config.php';

// Get JSON input
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['reservationId']) || !isset($data['player_name']) || !isset($data['score'])) {
    echo json_encode(['success' => false, 'message' => 'Onvolledige gegevens ontvangen.']);
    exit;
}

$reservationId = intval($data['reservationId']);
$playerNames = $data['player_name'];
$scores = $data['score'];

if (count($playerNames) !== count($scores)) {
    echo json_encode(['success' => false, 'message' => 'Aantal namen en scores komt niet overeen.']);
    exit;
}

try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("INSERT INTO scores (reservation_id, player_name, score) VALUES (?, ?, ?)");
    if (!$stmt) {
        throw new Exception('Statement kon niet worden voorbereid.');
    }
    $success = true;
    for ($i = 0; $i < count($playerNames); $i++) {
        $name = trim($playerNames[$i]);
        $score = intval($scores[$i]);
        if ($name === '' || !is_numeric($score)) continue;
        $stmt->bind_param('isi', $reservationId, $name, $score);
        if (!$stmt->execute()) {
            $success = false;
            break;
        }
    }
    $stmt->close();
    $conn->close();
    if ($success) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Fout bij opslaan van scores.']);
    }
} catch (Exception $e) {
    error_log('addScores.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Serverfout: ' . $e->getMessage()]);
}
