<?php
// Booking helpers shared by the booking lists (includes/bookings-list.php) and bookig-details.php.
// Status: 0 (or NULL) = new / pending, 1 = confirmed, 2 = cancelled.

const BOOKING_SELECT = "SELECT b.id, b.BookingNumber, b.userEmail, b.VehicleId AS vid, b.FromDate, b.ToDate, b.message,
    b.Status, b.PostingDate, b.LastUpdationDate, b.quoted_price, b.pricing_details, b.priced_at, u.id AS uid, u.FullName, u.ContactNo, u.Address, u.City, u.Country, u.RegDate,
    v.VehiclesTitle, v.PricePerDay, v.Vimage1, br.BrandName
  FROM tblbooking b
  LEFT JOIN tblusers u ON u.EmailId = b.userEmail
  LEFT JOIN tblvehicles v ON v.id = b.VehicleId
  LEFT JOIN tblbrands br ON br.id = v.VehiclesBrand";

// Handles the confirm (aeid) and cancel (eid) POST actions. Returns true when one was handled.
function admin_booking_actions(PDO $dbh): bool
{
    if (isset($_POST['aeid'])) {
        [$type, $message] = admin_booking_set_status($dbh, intval($_POST['aeid']), 1);
    } elseif (isset($_POST['eid'])) {
        [$type, $message] = admin_booking_set_status($dbh, intval($_POST['eid']), 2);
    } else {
        return false;
    }
    flash_set($type, $message);
    return true;
}

// Confirms (1) or cancels (2) a booking. Returns [flash type, message].
function admin_booking_set_status(PDO $dbh, int $id, int $status): array
{
    $query = $dbh->prepare("SELECT id, BookingNumber, VehicleId, FromDate, ToDate, Status FROM tblbooking WHERE id = :id");
    $query->bindParam(':id', $id, PDO::PARAM_INT);
    $query->execute();
    $booking = $query->fetch(PDO::FETCH_OBJ);
    if (!$booking) {
        return ['error', 'That booking no longer exists.'];
    }
    $number = '#' . $booking->BookingNumber;
    if ((int)$booking->Status === $status) {
        return ['info', "Booking $number is already " . ($status === 1 ? 'confirmed.' : 'cancelled.')];
    }
    if ($status === 1) {
        if ((int)$booking->Status === 2) {
            return ['error', "Booking $number was cancelled, so it can't be confirmed."];
        }
        // Don't confirm two bookings for the same car on overlapping dates.
        $clash = $dbh->prepare("SELECT BookingNumber FROM tblbooking WHERE VehicleId = :vid AND id <> :id AND Status = 1
            AND FromDate <= :todate AND ToDate >= :fromdate LIMIT 1");
        $clash->execute([':vid' => $booking->VehicleId, ':id' => $id, ':todate' => $booking->ToDate, ':fromdate' => $booking->FromDate]);
        $other = $clash->fetchColumn();
        if ($other) {
            return ['error', "Booking $number overlaps confirmed booking #$other for the same car. Cancel one of them first."];
        }
    }
    $update = $dbh->prepare("UPDATE tblbooking SET Status = :status WHERE id = :id");
    $update->bindParam(':status', $status, PDO::PARAM_INT);
    $update->bindParam(':id', $id, PDO::PARAM_INT);
    $update->execute();
    // Availability and occupancy changed.
    forecast_request_recompute($dbh, 'booking ' . $number . ($status === 1 ? ' confirmed' : ' cancelled'));
    if ($status === 1 && mail_booking_confirmed($dbh, $id)) {
        return ['success', "Booking $number confirmed. The customer was emailed."];
    }
    return ['success', $status === 1 ? "Booking $number confirmed." : "Booking $number cancelled."];
}

// Confirm / cancel buttons for a booking (each opens the confirmation modal).
function admin_booking_buttons(object $b, bool $large = false): string
{
    $status = (int)$b->Status;
    $html = '';
    if ($status === 0) {
        $html .= $large
            ? admin_booking_big_button(['aeid' => $b->id], 'fa-check', 'Confirm booking', 'success', 'Confirm booking #' . $b->BookingNumber . '? The customer will see it as confirmed.')
            : admin_action_button(['aeid' => $b->id], 'fa-check', 'Confirm booking', 'success', 'Confirm booking #' . $b->BookingNumber . '? The customer will see it as confirmed.');
    }
    if ($status !== 2) {
        $html .= $large
            ? admin_booking_big_button(['eid' => $b->id], 'fa-xmark', 'Cancel booking', 'danger', 'Cancel booking #' . $b->BookingNumber . '? This can\'t be undone.')
            : admin_action_button(['eid' => $b->id], 'fa-xmark', 'Cancel booking', 'danger', 'Cancel booking #' . $b->BookingNumber . '? This can\'t be undone.');
    }
    return $html;
}

// Full-size version of admin_action_button() for the booking detail page.
function admin_booking_big_button(array $fields, string $icon, string $label, string $variant, string $confirm): string
{
    $html = '<form method="post" class="a-inline-form" data-confirm="' . h($confirm) . '" data-confirm-title="' . h($label) . '?" data-confirm-ok="' . h($label) . '"'
        . ($variant === 'danger' ? ' data-confirm-danger' : '') . '>' . csrf_field();
    foreach ($fields as $name => $value) {
        $html .= '<input type="hidden" name="' . h($name) . '" value="' . h($value) . '">';
    }
    return $html . '<button type="submit" class="btn btn-' . ($variant === 'danger' ? 'light text-danger' : 'success') . '"><i class="fa ' . $icon . '" aria-hidden="true"></i>' . h($label) . '</button></form>';
}
