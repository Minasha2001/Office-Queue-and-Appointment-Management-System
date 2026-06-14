<?php
require_once 'db.php';

header('Content-Type: application/json');

$service_id = intval($_GET['service_id'] ?? 0);
$date_str = trim($_GET['date'] ?? '');

if (empty($service_id) || empty($date_str)) {
    echo json_encode(['error' => 'Missing parameters.']);
    exit();
}

// Validate date format
$date = DateTime::createFromFormat('Y-m-d', $date_str);
if (!$date || $date->format('Y-m-d') !== $date_str) {
    echo json_encode(['error' => 'Invalid date format.']);
    exit();
}

$today = new DateTime('today');
$selected_date = new DateTime($date_str);

if ($selected_date < $today) {
    echo json_encode(['error' => 'Cannot book appointments in the past.', 'slots' => []]);
    exit();
}

// 1. Check Holidays
$holiday_query = "SELECT description FROM holidays WHERE holiday_date = ?";
$stmt = $conn->prepare($holiday_query);
$stmt->bind_param("s", $date_str);
$stmt->execute();
$res = $stmt->get_result();
if ($holiday = $res->fetch_assoc()) {
    echo json_encode(['error' => "The office is closed on this date: " . $holiday['description'], 'slots' => []]);
    $stmt->close();
    exit();
}
$stmt->close();

// 2. Check Day of Week Working Hours
$day_of_week = $selected_date->format('l'); // e.g., 'Monday'
$schedule_query = "SELECT start_time, end_time, slot_duration_minutes FROM schedules WHERE day_of_week = ?";
$stmt = $conn->prepare($schedule_query);
$stmt->bind_param("s", $day_of_week);
$stmt->execute();
$res = $stmt->get_result();
$schedule = $res->fetch_assoc();
$stmt->close();

if (!$schedule) {
    echo json_encode(['error' => "The office is closed on {$day_of_week}s.", 'slots' => []]);
    exit();
}

$start_time = new DateTime($schedule['start_time']);
$end_time = new DateTime($schedule['end_time']);
$slot_duration = $schedule['slot_duration_minutes'];

// 3. Calculate Service Capacity (Active Staff count for this service)
$staff_query = "SELECT COUNT(*) as active_staff FROM staff WHERE service_id = ? AND status = 'active'";
$stmt = $conn->prepare($staff_query);
$stmt->bind_param("i", $service_id);
$stmt->execute();
$res = $stmt->get_result();
$staff_data = $res->fetch_assoc();
$stmt->close();

$capacity = intval($staff_data['active_staff'] ?? 0);
if ($capacity === 0) {
    $capacity = 1; // Default to 1 slot capacity if no staff is assigned yet
}

// 4. Fetch Existing Bookings count for this day and service
$bookings_query = "SELECT time_slot, COUNT(*) as booked_count FROM appointments WHERE service_id = ? AND appointment_date = ? AND status IN ('pending', 'completed') GROUP BY time_slot";
$stmt = $conn->prepare($bookings_query);
$stmt->bind_param("is", $service_id, $date_str);
$stmt->execute();
$res = $stmt->get_result();
$booked_slots = [];
while ($row = $res->fetch_assoc()) {
    // Standardize time format to H:i:s
    $formatted_time = date('H:i:s', strtotime($row['time_slot']));
    $booked_slots[$formatted_time] = intval($row['booked_count']);
}
$stmt->close();

// 5. Generate Time Slots
$slots = [];
$current_time = clone $start_time;

$now = new DateTime(); // Current system time
$is_today = ($selected_date->format('Y-m-d') === $now->format('Y-m-d'));

while ($current_time < $end_time) {
    $slot_time_str = $current_time->format('H:i:s');
    $display_time = $current_time->format('h:i A');

    // Skip lunch break (typically 12:00 PM to 01:00 PM)
    $lunch_start = new DateTime('12:00:00');
    $lunch_end = new DateTime('13:00:00');
    if ($current_time >= $lunch_start && $current_time < $lunch_end) {
        $current_time->modify("+{$slot_duration} minutes");
        continue;
    }

    // If booking for today, filter out past slots (e.g. only allow slots starting 15+ minutes from now)
    $available = true;
    if ($is_today) {
        $slot_datetime = new DateTime($date_str . ' ' . $slot_time_str);
        $buffer_time = clone $now;
        $buffer_time->modify('+15 minutes');
        if ($slot_datetime < $buffer_time) {
            $available = false;
        }
    }

    // Check capacity
    $booked_count = $booked_slots[$slot_time_str] ?? 0;
    if ($booked_count >= $capacity) {
        $available = false;
    }

    if ($available) {
        $slots[] = [
            'time' => $slot_time_str,
            'display' => $display_time,
            'available' => true,
            'booked' => $booked_count,
            'capacity' => $capacity
        ];
    } else {
        $slots[] = [
            'time' => $slot_time_str,
            'display' => $display_time,
            'available' => false,
            'booked' => $booked_count,
            'capacity' => $capacity
        ];
    }

    $current_time->modify("+{$slot_duration} minutes");
}

echo json_encode(['slots' => $slots]);
?>
