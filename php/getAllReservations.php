<?php
header('Content-Type: application/json');
require_once 'config.php';

try {
    // Check if user is logged in as employee or directie
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        echo json_encode(['success' => false, 'message' => 'Niet ingelogd']);
        exit;
    }
    
    $role = $_SESSION['rol'] ?? '';
    if ($role !== 'employee' && $role !== 'medewerker' && $role !== 'directie') {
        echo json_encode(['success' => false, 'message' => 'Geen toegang']);
        exit;
    }
    
    $conn = getDBConnection();
    
    // Get today's reservations
    $stmt = $conn->prepare("SELECT r.reservation_id, r.date, r.start_time, r.end_time, 
                                   r.number_of_adults, r.number_of_kids, r.notes, r.status,
                                   u.first_name, u.last_name, u.email,
                                   GROUP_CONCAT(l.lane_number ORDER BY l.lane_number SEPARATOR ', ') as lanes
                            FROM reservations r
                            JOIN users u ON r.user_id = u.user_id
                            LEFT JOIN reervation_lane rl ON r.reservation_id = rl.reservation_id
                            LEFT JOIN lane l ON rl.lane_id = l.lane_id
                            WHERE r.date = CURDATE()
                            GROUP BY r.reservation_id, r.date, r.start_time, r.end_time, 
                                     r.number_of_adults, r.number_of_kids, r.notes, r.status,
                                     u.first_name, u.last_name, u.email
                            ORDER BY r.start_time ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    
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
    
    $today = [];
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
        $row['customer_name'] = $row['first_name'] . ' ' . $row['last_name'];
        $row['lanes'] = $row['lanes'] ?? 'Geen banen toegewezen';
        $today[] = $row;
    }
    $stmt->close();
    
    // Get upcoming reservations (future dates)
    $stmt = $conn->prepare("SELECT r.reservation_id, r.date, r.start_time, r.end_time, 
                                   r.number_of_adults, r.number_of_kids, r.notes, r.status,
                                   u.first_name, u.last_name, u.email,
                                   GROUP_CONCAT(l.lane_number ORDER BY l.lane_number SEPARATOR ', ') as lanes
                            FROM reservations r
                            JOIN users u ON r.user_id = u.user_id
                            LEFT JOIN reervation_lane rl ON r.reservation_id = rl.reservation_id
                            LEFT JOIN lane l ON rl.lane_id = l.lane_id
                            WHERE r.date > CURDATE()
                            GROUP BY r.reservation_id, r.date, r.start_time, r.end_time, 
                                     r.number_of_adults, r.number_of_kids, r.notes, r.status,
                                     u.first_name, u.last_name, u.email
                            ORDER BY r.date ASC, r.start_time ASC
                            LIMIT 20");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $upcoming = [];
    while ($row = $result->fetch_assoc()) {
        $date = new DateTime($row['date']);
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
        $row['customer_name'] = $row['first_name'] . ' ' . $row['last_name'];
        $row['lanes'] = $row['lanes'] ?? 'Geen banen toegewezen';
        
        $upcoming[] = $row;
    }
    $stmt->close();
    
    // Get past reservations
    $stmt = $conn->prepare("SELECT r.reservation_id, r.date, r.start_time, r.end_time, 
                                   r.number_of_adults, r.number_of_kids, r.notes, r.status,
                                   u.first_name, u.last_name, u.email,
                                   GROUP_CONCAT(l.lane_number ORDER BY l.lane_number SEPARATOR ', ') as lanes
                            FROM reservations r
                            JOIN users u ON r.user_id = u.user_id
                            LEFT JOIN reervation_lane rl ON r.reservation_id = rl.reservation_id
                            LEFT JOIN lane l ON rl.lane_id = l.lane_id
                            WHERE r.date < CURDATE()
                            GROUP BY r.reservation_id, r.date, r.start_time, r.end_time, 
                                     r.number_of_adults, r.number_of_kids, r.notes, r.status,
                                     u.first_name, u.last_name, u.email
                            ORDER BY r.date DESC, r.start_time DESC
                            LIMIT 10");
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
        $row['customer_name'] = $row['first_name'] . ' ' . $row['last_name'];
        $row['lanes'] = $row['lanes'] ?? 'Geen banen toegewezen';
        
        $past[] = $row;
    }
    $stmt->close();
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'today' => $today,
        'upcoming' => $upcoming,
        'past' => $past
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching all reservations: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Fout bij ophalen reserveringen']);
}
?>
