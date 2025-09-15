<?php
header('Content-Type: application/json');

require_once 'db_connect.php';

$USER_ID = 1;

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'getHabits':
        getHabits($conn);
        break;
    case 'getAwards':
        getAwards($conn);
        break;
    case 'getUserData':
        getUserData($conn, $USER_ID);
        break;
    case 'trackHabit':
        trackHabit($conn, $USER_ID);
        break;
    case 'suggestHabit':
        suggestHabit();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

$conn->close();

function getHabits($conn) {
    $result = $conn->query("SELECT id, name, description, icon FROM habits ORDER BY id");
    $habits = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode($habits);
}

function getAwards($conn) {
    $result = $conn->query("SELECT id, name, description, icon FROM awards ORDER BY value");
    $awards = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode($awards);
}

function getUserData($conn, $userId) {
    $stmt = $conn->prepare("SELECT SUM(h.points) AS total_score FROM user_habits uh JOIN habits h ON uh.habit_id = h.id WHERE uh.user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $eco_score = $stmt->get_result()->fetch_assoc()['total_score'] ?? 0;

    $total_habits = $conn->query("SELECT COUNT(*) as count FROM habits")->fetch_assoc()['count'];

    $stmt = $conn->prepare("SELECT habit_id FROM user_habits WHERE user_id = ? AND date_completed = CURDATE()");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $tracked_today_result = $stmt->get_result();
    $tracked_today = [];
    while ($row = $tracked_today_result->fetch_assoc()) {
        $tracked_today[] = (int)$row['habit_id'];
    }

    $stmt = $conn->prepare("SELECT award_id, name, icon, description FROM user_awards ua JOIN awards a ON ua.award_id = a.id WHERE ua.user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $earned_awards = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $streak = 0;
    $current_date = new DateTime();
    while (true) {
        $date_to_check = $current_date->format('Y-m-d');
        $stmt = $conn->prepare("SELECT 1 FROM user_habits WHERE user_id = ? AND date_completed = ? LIMIT 1");
        $stmt->bind_param("is", $userId, $date_to_check);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $streak++;
            $current_date->modify('-1 day');
        } else {
            break;
        }
    }

    $chart_labels = [];
    $chart_data = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $label = date('D, M j', strtotime("-$i days"));
        $chart_labels[] = $label;
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_habits WHERE user_id = ? AND date_completed = ?");
        $stmt->bind_param("is", $userId, $date);
        $stmt->execute();
        $count = $stmt->get_result()->fetch_assoc()['count'] ?? 0;
        $chart_data[] = $count;
    }
    
    $data = [
        'eco_score' => (int)$eco_score,
        'total_habits' => (int)$total_habits,
        'tracked_today' => $tracked_today,
        'earned_awards' => $earned_awards,
        'streak' => $streak,
        'chart_data' => [
            'labels' => $chart_labels,
            'data' => $chart_data,
        ]
    ];

    echo json_encode($data);
}

function trackHabit($conn, $userId) {
    $data = json_decode(file_get_contents('php://input'), true);
    $habitId = $data['habit_id'] ?? 0;

    if (empty($habitId)) {
        echo json_encode(['success' => false, 'message' => 'Habit ID is required.']);
        return;
    }
    
    $stmt = $conn->prepare("SELECT id FROM user_habits WHERE user_id = ? AND habit_id = ? AND date_completed = CURDATE()");
    $stmt->bind_param("ii", $userId, $habitId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Habit already tracked today.']);
        return;
    }

    $stmt = $conn->prepare("INSERT INTO user_habits (user_id, habit_id, date_completed) VALUES (?, ?, CURDATE())");
    $stmt->bind_param("ii", $userId, $habitId);

    if ($stmt->execute()) {
        $new_awards = checkAndGrantAwards($conn, $userId);
        echo json_encode(['success' => true, 'message' => 'Habit tracked!', 'new_awards' => $new_awards]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to track habit.']);
    }
}

function suggestHabit() {
    $data = json_decode(file_get_contents('php://input'), true);
    $name = $data['name'] ?? '';
    if (!empty($name)) {
        echo json_encode(['success' => true, 'message' => 'Suggestion received.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid data.']);
    }
}

function checkAndGrantAwards($conn, $userId) {
    $newly_earned = [];
    
    $stmt = $conn->prepare("SELECT * FROM awards WHERE id NOT IN (SELECT award_id FROM user_awards WHERE user_id = ?)");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $potential_awards = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmt_total = $conn->prepare("SELECT COUNT(*) as count FROM user_habits WHERE user_id = ?");
    $stmt_total->bind_param("i", $userId);
    $stmt_total->execute();
    $total_habits_tracked = $stmt_total->get_result()->fetch_assoc()['count'];

    $streak = 0;
    $current_date = new DateTime();
    while (true) {
        $date_to_check = $current_date->format('Y-m-d');
        $stmt_streak = $conn->prepare("SELECT 1 FROM user_habits WHERE user_id = ? AND date_completed = ? LIMIT 1");
        $stmt_streak->bind_param("is", $userId, $date_to_check);
        $stmt_streak->execute();
        if ($stmt_streak->get_result()->num_rows > 0) {
            $streak++;
            $current_date->modify('-1 day');
        } else {
            break;
        }
    }

    foreach ($potential_awards as $award) {
        $condition_met = false;
        if ($award['type'] == 'total' && $total_habits_tracked >= $award['value']) {
            $condition_met = true;
        } elseif ($award['type'] == 'streak' && $streak >= $award['value']) {
            $condition_met = true;
        }

        if ($condition_met) {
            $stmt_grant = $conn->prepare("INSERT INTO user_awards (user_id, award_id) VALUES (?, ?)");
            $stmt_grant->bind_param("ii", $userId, $award['id']);
            $stmt_grant->execute();
            $newly_earned[] = $award;
        }
    }
    
    return $newly_earned;
}
?>