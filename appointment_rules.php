<?php

function isAppointmentDate(mixed $date): bool
{
    return is_string($date)
        && preg_match('/\A([0-9]{4})-([0-9]{2})-([0-9]{2})\z/', $date, $parts)
        && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
}

function lockMechanic(PDO $pdo, int $mechanicId): void
{
    $lock = $pdo->prepare('SELECT id FROM mechanics WHERE id = ? FOR UPDATE');
    $lock->execute([$mechanicId]);

    if (!$lock->fetchColumn()) {
        throw new DomainException('Choose a mechanic from the list.');
    }
}

function checkAppointmentAvailability(
    PDO $pdo,
    string $phone,
    string $date,
    int $mechanicId,
    int $appointmentId = 0
): void {
    $duplicate = $pdo->prepare('SELECT 1 FROM appointments WHERE phone = ? AND appointment_date = ? AND id <> ?');
    $duplicate->execute([$phone, $date, $appointmentId]);

    if ($duplicate->fetchColumn()) {
        throw new DomainException($appointmentId === 0
            ? 'You already have an appointment on this date.'
            : 'This customer already has an appointment on this date.');
    }

    $count = $pdo->prepare('SELECT COUNT(*) FROM appointments WHERE mechanic_id = ? AND appointment_date = ? AND id <> ?');
    $count->execute([$mechanicId, $date, $appointmentId]);

    if ((int) $count->fetchColumn() >= 4) {
        throw new DomainException('That mechanic is fully booked for this date.');
    }
}
