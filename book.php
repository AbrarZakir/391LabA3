<?php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

require __DIR__ . '/appointment_rules.php';

$fields = [
    'customer_name' => 100,
    'address' => 255,
    'phone' => 20,
    'car_registration' => 30,
    'engine_number' => 50,
];
$booking = [];
$bookingUrl = 'index.php';

try {
    foreach ($fields as $field => $maxLength) {
        $value = $_POST[$field] ?? '';
        $value = is_string($value) ? trim($value) : '';

        if ($value === '' || mb_strlen($value, 'UTF-8') > $maxLength) {
            throw new DomainException('Fill in all customer and vehicle details within the length limits.');
        }

        $booking[$field] = $value;
    }

    if (!preg_match('/\A[0-9]{1,20}\z/', $booking['phone'])) {
        throw new DomainException('Enter a phone number using digits only.');
    }

    if (!preg_match('/\A[0-9]{1,50}\z/', $booking['engine_number'])) {
        throw new DomainException('Enter an engine number using digits only.');
    }

    $date = $_POST['date'] ?? '';
    if (!isAppointmentDate($date)) {
        throw new DomainException('Choose a valid appointment date.');
    }
    $bookingUrl .= '?date=' . $date;

    $mechanicId = filter_input(INPUT_POST, 'mechanic_id', FILTER_VALIDATE_INT);
    if (!$mechanicId || $mechanicId < 1) {
        throw new DomainException('Choose a mechanic.');
    }

    require __DIR__ . '/db.php';
    $pdo->beginTransaction();

    lockMechanic($pdo, $mechanicId);
    checkAppointmentAvailability($pdo, $booking['phone'], $date, $mechanicId);

    $insert = $pdo->prepare('
        INSERT INTO appointments
            (customer_name, address, phone, car_registration, engine_number,
             appointment_date, mechanic_id)
        VALUES
            (:customer_name, :address, :phone, :car_registration, :engine_number,
             :date, :mechanic_id)
    ');
    $insert->execute($booking + ['date' => $date, 'mechanic_id' => $mechanicId]);

    $pdo->commit();
    $confirmed = true;
    $message = 'Appointment confirmed.';
} catch (DomainException $error) {
    $message = $error->getMessage();
} catch (PDOException $error) {
    // The database also catches duplicates submitted to different mechanics at the same time.
    if ((int) ($error->errorInfo[1] ?? 0) === 1062) {
        $message = 'You already have an appointment on this date.';
    } else {
        error_log('Workshop booking database error: ' . $error->getCode());
        $message = 'The booking could not be saved. Please try again.';
    }
} finally {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Workshop Booking</title>
    <link rel="stylesheet" href="workshop.css">
</head>
<body>
    <main class="shell shell-narrow panel">
        <p class="eyebrow">Workshop appointments</p>
        <h1><?= isset($confirmed) ? 'Booking confirmed' : 'Booking unsuccessful' ?></h1>
        <p><?= htmlspecialchars($message) ?></p>
        <p><a href="<?= htmlspecialchars($bookingUrl) ?>">Back to booking</a></p>
    </main>
</body>
</html>
