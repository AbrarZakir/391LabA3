<?php

header('Cache-Control: no-store');
$passwordHash = getenv('WORKSHOP_ADMIN_PASSWORD_HASH');

if ($passwordHash === false || $passwordHash === '') {
    http_response_code(503);
    exit('Set WORKSHOP_ADMIN_PASSWORD_HASH on the server to enable admin access.');
}

if (($_SERVER['PHP_AUTH_USER'] ?? '') !== 'admin'
    || !password_verify($_SERVER['PHP_AUTH_PW'] ?? '', $passwordHash)) {
    header('WWW-Authenticate: Basic realm="Workshop admin"', true, 401);
    exit('Admin sign-in required.');
}

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
$_SESSION['admin_csrf'] ??= bin2hex(random_bytes(32));

require __DIR__ . '/appointment_rules.php';

try {
    require __DIR__ . '/db.php';
} catch (PDOException $error) {
    error_log('Workshop admin connection error: ' . $error->getCode());
    http_response_code(503);
    exit('The workshop database is currently unavailable.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['admin_csrf'], $token)) {
        http_response_code(403);
        exit('Reload the admin page and try again.');
    }

    try {
        $appointmentId = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
        $mechanicId = filter_input(INPUT_POST, 'mechanic_id', FILTER_VALIDATE_INT);
        $date = $_POST['date'] ?? '';

        if ($appointmentId < 1 || $mechanicId < 1 || !isAppointmentDate($date)) {
            throw new DomainException('Choose a valid appointment date and mechanic.');
        }

        $pdo->beginTransaction();
        lockMechanic($pdo, $mechanicId);

        $appointment = $pdo->prepare('SELECT phone FROM appointments WHERE id = ? FOR UPDATE');
        $appointment->execute([$appointmentId]);
        $phone = $appointment->fetchColumn();
        if ($phone === false) {
            throw new DomainException('This appointment could not be found.');
        }

        checkAppointmentAvailability($pdo, $phone, $date, $mechanicId, $appointmentId);

        $update = $pdo->prepare('UPDATE appointments SET appointment_date = ?, mechanic_id = ? WHERE id = ?');
        $update->execute([$date, $mechanicId, $appointmentId]);
        $pdo->commit();
        $_SESSION['admin_message'] = 'Appointment updated.';
    } catch (DomainException $error) {
        $_SESSION['admin_message'] = $error->getMessage();
    } catch (PDOException $error) {
        if ((int) ($error->errorInfo[1] ?? 0) === 1062) {
            $_SESSION['admin_message'] = 'This customer already has an appointment on this date.';
        } else {
            error_log('Workshop admin update error: ' . $error->getCode());
            $_SESSION['admin_message'] = 'The appointment could not be updated. Please try again.';
        }
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }

    header('Location: admin.php', true, 303);
    exit;
}

$message = $_SESSION['admin_message'] ?? '';
unset($_SESSION['admin_message']);

try {
    $mechanics = $pdo->query('SELECT id, name FROM mechanics ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $appointments = $pdo->query('
        SELECT a.id, a.customer_name, a.phone, a.car_registration, a.appointment_date,
               a.mechanic_id, m.name AS mechanic_name
        FROM appointments AS a
        JOIN mechanics AS m ON m.id = a.mechanic_id
        ORDER BY a.appointment_date, a.id
    ')->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $error) {
    error_log('Workshop admin list error: ' . $error->getCode());
    http_response_code(503);
    exit('The appointment list is currently unavailable.');
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Workshop Admin</title>
    <link rel="stylesheet" href="workshop.css">
</head>
<body>
    <main class="shell">
    <header class="page-header">
        <div>
            <p class="eyebrow">Service desk</p>
            <h1>Workshop Admin</h1>
            <p>Change the date or mechanic for an appointment, then select Save.</p>
        </div>
        <a href="index.php">Customer booking page</a>
    </header>

    <section class="panel" aria-label="Manage appointments">
    <?php if ($message !== ''): ?>
        <p role="status"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <?php if (!$appointments): ?>
        <p>No appointments yet.</p>
    <?php else: ?>
        <div class="table-scroll" tabindex="0" role="region" aria-label="Appointment table">
        <table>
            <caption>Workshop appointments</caption>
            <thead>
                <tr>
                    <th scope="col">Customer</th>
                    <th scope="col">Phone</th>
                    <th scope="col">Car registration</th>
                    <th scope="col">Date</th>
                    <th scope="col">Mechanic</th>
                    <th scope="col">Reschedule</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($appointments as $appointment): ?>
                    <tr>
                        <td><?= htmlspecialchars($appointment['customer_name']) ?></td>
                        <td><?= htmlspecialchars($appointment['phone']) ?></td>
                        <td><?= htmlspecialchars($appointment['car_registration']) ?></td>
                        <td><?= htmlspecialchars($appointment['appointment_date']) ?></td>
                        <td><?= htmlspecialchars($appointment['mechanic_name']) ?></td>
                        <td>
                            <form class="reschedule" method="post" action="admin.php">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['admin_csrf']) ?>">
                                <input type="hidden" name="appointment_id" value="<?= (int) $appointment['id'] ?>">
                                <label>
                                    Date
                                    <input type="date" name="date"
                                           value="<?= htmlspecialchars($appointment['appointment_date']) ?>" required>
                                </label>
                                <label>
                                    Mechanic
                                    <select name="mechanic_id" required>
                                        <?php foreach ($mechanics as $mechanic): ?>
                                            <option value="<?= (int) $mechanic['id'] ?>"
                                                <?= (int) $mechanic['id'] === (int) $appointment['mechanic_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($mechanic['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <button type="submit">Save</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
    </section>
    </main>
</body>
</html>
