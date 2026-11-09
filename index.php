<?php
require __DIR__ . '/appointment_rules.php';

date_default_timezone_set('Asia/Dhaka');
$today = date('Y-m-d');
$date = $_GET['date'] ?? $today;

$availabilityRequest = ($_GET['availability'] ?? null) === '1';

if ($availabilityRequest) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if (!isAppointmentDate($date)) {
        http_response_code(400);
        echo json_encode(['error' => 'Choose a valid appointment date.']);
        exit;
    }
}

if (!isAppointmentDate($date)) {
    $date = $today;
}

try {
    require __DIR__ . '/db.php';
    $query = $pdo->prepare('
        SELECT m.id, m.name, GREATEST(0, 4 - COUNT(a.id)) AS free_slots
        FROM mechanics AS m
        LEFT JOIN appointments AS a
            ON a.mechanic_id = m.id AND a.appointment_date = :date
        GROUP BY m.id, m.name
        ORDER BY m.id
    ');
    $query->execute(['date' => $date]);
    $mechanics = $query->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $error) {
    error_log('Workshop availability database error: ' . $error->getCode());
    http_response_code(503);
    $message = 'Availability is temporarily unavailable. Please try again.';
    exit($availabilityRequest ? json_encode(['error' => $message]) : $message);
}

if ($availabilityRequest) {
    echo json_encode(['date' => $date, 'mechanics' => $mechanics]);
    exit;
}

$hasAvailability = array_sum(array_column($mechanics, 'free_slots')) > 0;
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Workshop Appointments</title>
    <link rel="stylesheet" href="workshop.css">
    <script src="availability.js" defer></script>
</head>
<body>
    <main class="shell">
        <header class="page-header">
            <div>
                <p class="eyebrow">Service desk</p>
                <h1>Workshop Appointments</h1>
                <p>Choose a service date and book an available mechanic.</p>
            </div>
            <a href="admin.php">Admin sign-in</a>
        </header>

        <div class="booking-layout">
            <section class="panel" aria-labelledby="availability-title">
                <h2 id="availability-title">Mechanic availability</h2>
                <form id="availability-form" method="get">
                    <label for="date">Appointment date</label>
                    <div class="date-controls">
                        <input id="date" name="date" type="date" value="<?= htmlspecialchars($date) ?>" required>
                        <button class="secondary" type="submit">Check availability</button>
                    </div>
                </form>
                <p id="availability-status" class="status" role="status"><?=
                    $hasAvailability ? '' : 'No mechanics are available on this date. Choose another date.'
                ?></p>
                <ul id="mechanic-slots" class="slots">
                    <?php foreach ($mechanics as $mechanic): ?>
                        <li>
                            <strong><?= htmlspecialchars($mechanic['name']) ?></strong>
                            <span><?= (int) $mechanic['free_slots'] ?> of 4 slots available</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <details>
                    <summary>How to book</summary>
                    <ol>
                        <li>Choose a date and check the remaining slots.</li>
                        <li>Enter your contact and vehicle details, then choose an available mechanic.</li>
                        <li>Select <strong>Book appointment</strong> and look for a confirmation.</li>
                    </ol>
                    <p>You can book one appointment per date using the same phone number. If a mechanic is full, choose another mechanic or date.</p>
                </details>
            </section>

            <section class="panel" aria-labelledby="booking-title">
                <h2 id="booking-title">Your booking</h2>
                <p class="booking-date">Booking date <strong id="booking-date"><?= htmlspecialchars($date) ?></strong></p>
                <form id="booking-form" method="post" action="book.php">
                    <input type="hidden" name="date" value="<?= htmlspecialchars($date) ?>">
                    <div class="form-grid">
                        <label class="full-width">Name <input name="customer_name" maxlength="100" autocomplete="name" required></label>
                        <label class="full-width">Address <input name="address" maxlength="255" autocomplete="street-address" required></label>
                        <label>Phone <input name="phone" type="tel" inputmode="numeric" pattern="[0-9]{1,20}" maxlength="20" autocomplete="tel-national" required></label>
                        <label>Car registration <input name="car_registration" maxlength="30" required></label>
                        <label>Engine number <input name="engine_number" inputmode="numeric" pattern="[0-9]{1,50}" maxlength="50" required></label>
                        <label for="mechanic">Mechanic
                            <select id="mechanic" name="mechanic_id" required <?= $hasAvailability ? '' : 'disabled' ?>>
                                <option value="">Choose a mechanic</option>
                                <?php foreach ($mechanics as $mechanic): ?>
                                    <?php if ((int) $mechanic['free_slots'] > 0): ?>
                                        <option value="<?= (int) $mechanic['id'] ?>">
                                            <?= htmlspecialchars($mechanic['name']) ?>
                                            (<?= (int) $mechanic['free_slots'] ?> slots left)
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <p class="field-help">Use digits only for the phone and engine numbers.</p>
                    <button type="submit" <?= $hasAvailability ? '' : 'disabled' ?>>Book appointment</button>
                </form>
            </section>
        </div>
    </main>
</body>
</html>
