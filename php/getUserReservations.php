<?php
header('Content-Type: application/json');
require_once 'config.php';

try {
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        echo json_encode(['success' => false, 'message' => 'Niet ingelogd']);
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    $conn = getDBConnection();
    
    // Get upcoming reservations (today or future)
    $stmt = $conn->prepare("SELECT reservation_id, date, start_time, end_time, number_of_adults, number_of_kids, notes, status 
                            FROM reservations 
                            WHERE user_id = ? AND date >= CURDATE() 
                            ORDER BY date ASC, start_time ASC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $upcoming = [];
    while ($row = $result->fetch_assoc()) {
        $date = new DateTime($row['date']);
        $row['date_formatted'] = $date->format('l j F Y');
        // Translate day names to Dutch
        $days_nl = [
            'Monday' => 'Maandag',
            'Tuesday' => 'Dinsdag',
            'Wednesday' => 'Woensdag',
            'Thursday' => 'Donderdag',
            'Friday' => 'Vrijdag',
            'Saturday' => 'Zaterdag',
            'Sunday' => 'Zondag'
        ];
        $months_nl = [
            'January' => 'januari',
            'February' => 'februari',
            'March' => 'maart',
            'April' => 'april',
            'May' => 'mei',
            'June' => 'juni',
            'July' => 'juli',
            'August' => 'augustus',
            'September' => 'september',
            'October' => 'oktober',
            'November' => 'november',
            'December' => 'december'
        ];
        
        $formatted = $date->format('l j F Y');
        foreach ($days_nl as $en => $nl) {
            $formatted = str_replace($en, $nl, $formatted);
        }
        foreach ($months_nl as $en => $nl) {
            $formatted = str_replace($en, $nl, $formatted);
        }
        $row['date_formatted'] = $formatted;
        
        $upcoming[] = $row;
    }
    $stmt->close();
    
    // Get past reservations
    $stmt = $conn->prepare("SELECT reservation_id, date, start_time, end_time, number_of_adults, number_of_kids, notes, status 
                            FROM reservations 
                            WHERE user_id = ? AND date < CURDATE() 
                            ORDER BY date DESC, start_time DESC 
                            LIMIT 10");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $past = [];
    while ($row = $result->fetch_assoc()) {
        $date = new DateTime($row['date']);
        $formatted = $date->format('l j F Y');
        foreach ($days_nl as $en => $nl) {
            $formatted = str_replace($en, $nl, $formatted);
        }
        foreach ($months_nl as $en => $nl) {
            $formatted = str_replace($en, $nl, $formatted);
        }
        $row['date_formatted'] = $formatted;
        
        $past[] = $row;
    }
    $stmt->close();
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'upcoming' => $upcoming,
        'past' => $past
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching reservations: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Fout bij ophalen reserveringen']);
}
?>
