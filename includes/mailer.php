<?php
// Email through SMTP (PHPMailer, installed by Composer into vendor/). Settings: admin -> Email
// (tblmailsettings, migration 021). The SMTP password is encrypted with APP_KEY (includes/config.php).
//
//   mail_configured($dbh)                      true when SMTP host + from address are set and PHPMailer is installed
//   mail_send($dbh, $to, $subject, $html, $text, &$error)   false (and $error) when it could not be sent
//   mail_password_reset / mail_booking_received / mail_booking_confirmed / mail_enquiry_received
//
// Without SMTP nothing is sent: dev mode shows reset links on screen (reset-password.php,
// admin/forgot-password.php), as before. Sending never interrupts a booking or enquiry; failures are
// logged to logs/mail.log (addresses masked, never passwords).

const MAIL_SETTING_KEYS = ['smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password', 'mail_from', 'mail_from_name', 'mail_reply_to'];
const MAIL_ENCRYPTIONS = ['tls' => 'STARTTLS (usually port 587)', 'ssl' => 'SSL/TLS (usually port 465)', 'none' => 'None (local testing only)'];
const MAIL_LOG = __DIR__ . '/../logs/mail.log';

// Saved settings; the password stays encrypted unless $withPassword.
function mail_settings(PDO $dbh, bool $withPassword = false): array
{
    $values = array_fill_keys(MAIL_SETTING_KEYS, '');
    $values['smtp_port'] = '587';
    $values['smtp_encryption'] = 'tls';
    try {
        foreach ($dbh->query("SELECT SettingKey, SettingValue FROM tblmailsettings") as $row) {
            if (array_key_exists($row['SettingKey'], $values)) $values[$row['SettingKey']] = (string)$row['SettingValue'];
        }
    } catch (PDOException $e) {
        return $values; // migration 021 not applied
    }
    $values['has_password'] = $values['smtp_password'] !== '';
    $values['smtp_password'] = $withPassword ? mail_decrypt($values['smtp_password']) : '';
    return $values;
}

// Saves the settings. An empty password keeps the saved one unless $clearPassword.
function mail_save_settings(PDO $dbh, array $in, bool $clearPassword = false): void
{
    $upsert = $dbh->prepare("INSERT INTO tblmailsettings (SettingKey, SettingValue) VALUES (:k, :v) ON DUPLICATE KEY UPDATE SettingValue = VALUES(SettingValue)");
    foreach (MAIL_SETTING_KEYS as $key) {
        if ($key === 'smtp_password') {
            if ($clearPassword) $upsert->execute([':k' => $key, ':v' => '']);
            elseif (($in[$key] ?? '') !== '') $upsert->execute([':k' => $key, ':v' => mail_encrypt((string)$in[$key])]);
            continue;
        }
        $upsert->execute([':k' => $key, ':v' => trim((string)($in[$key] ?? ''))]);
    }
}

function mail_library_available(): bool
{
    static $ok = null;
    if ($ok === null) {
        $autoload = __DIR__ . '/../vendor/autoload.php';
        $ok = is_file($autoload) && (require_once $autoload) && class_exists(\PHPMailer\PHPMailer\PHPMailer::class);
    }
    return $ok;
}

function mail_configured(PDO $dbh): bool
{
    $s = mail_settings($dbh);
    return $s['smtp_host'] !== '' && filter_var($s['mail_from'], FILTER_VALIDATE_EMAIL) && mail_library_available();
}

// libsodium secretbox with APP_KEY; "v1:" + base64(nonce . ciphertext).
function mail_encrypt(string $plain): string
{
    $key = mail_key();
    if ($key === null) throw new RuntimeException('APP_KEY is not set, so the SMTP password cannot be stored safely.');
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return 'v1:' . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $key));
}

function mail_decrypt(string $stored): string
{
    $key = mail_key();
    if ($stored === '' || $key === null || !str_starts_with($stored, 'v1:')) return '';
    $raw = base64_decode(substr($stored, 3), true);
    if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) return '';
    $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $key);
    return $plain === false ? '' : $plain;
}

function mail_key(): ?string
{
    $key = defined('APP_KEY') ? base64_decode((string)APP_KEY, true) : false;
    return $key !== false && strlen($key) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES ? $key : null;
}

// "t***@example.com" for the log.
function mail_mask(string $address): string
{
    [$user, $domain] = array_pad(explode('@', $address, 2), 2, '');
    return mb_substr($user, 0, 1) . '***@' . $domain;
}

function mail_log(string $line): void
{
    @file_put_contents(MAIL_LOG, date('Y-m-d H:i:s') . ' ' . $line . "\n", FILE_APPEND | LOCK_EX);
}

// Sends one message. $kind names it in the log ("password-reset", "test", ...).
function mail_send(PDO $dbh, string $to, string $subject, string $html, string $text, ?string &$error = null, string $kind = 'mail', string $toName = ''): bool
{
    $error = null;
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { $error = 'The recipient address is not valid.'; return false; }
    if (!mail_configured($dbh)) { $error = 'Email is not set up (admin -> Email).'; return false; }
    $s = mail_settings($dbh, true);
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $s['smtp_host'];
        $mail->Port = (int)$s['smtp_port'] ?: 587;
        $mail->Timeout = 10;
        $mail->CharSet = 'UTF-8';
        if ($s['smtp_encryption'] === 'ssl') $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        elseif ($s['smtp_encryption'] === 'tls') $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        else { $mail->SMTPSecure = ''; $mail->SMTPAutoTLS = false; }
        if ($s['smtp_username'] !== '') {
            $mail->SMTPAuth = true;
            $mail->Username = $s['smtp_username'];
            $mail->Password = $s['smtp_password'];
        }
        $mail->setFrom($s['mail_from'], $s['mail_from_name'] !== '' ? $s['mail_from_name'] : content_raw('site_name'));
        if (filter_var($s['mail_reply_to'], FILTER_VALIDATE_EMAIL)) $mail->addReplyTo($s['mail_reply_to']);
        $mail->addAddress($to, $toName);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $html;
        $mail->AltBody = $text;
        $mail->send();
        mail_log("sent $kind to " . mail_mask($to));
        return true;
    } catch (\Throwable $e) {
        // PHPMailer's messages don't contain the password; keep them short for the admin.
        $error = preg_replace('/\s+/', ' ', mb_substr(strip_tags($e->getMessage()), 0, 240));
        mail_log("FAILED $kind to " . mail_mask($to) . ': ' . $error);
        return false;
    }
}

// A simple, accessible HTML email: heading, paragraphs, optional button and detail rows.
//   $rows: [[label, value], ...] shown as a small table (booking details)
function mail_layout(string $heading, array $paragraphs, ?array $button = null, array $rows = []): array
{
    $site = content_raw('site_name');
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width">'
        . '<title>' . $e($heading) . '</title></head><body style="margin:0;padding:24px;background:#f1f2f4;font-family:Arial,Helvetica,sans-serif;color:#2b2d36;">'
        . '<div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:12px;padding:32px;">'
        . '<p style="margin:0 0 24px;font-size:22px;font-weight:bold;color:#101114;">' . $e(mb_substr($site, 0, -1)) . '<span style="color:#d23f1c;">' . $e(mb_substr($site, -1)) . '</span></p>'
        . '<h1 style="margin:0 0 16px;font-size:20px;color:#101114;">' . $e($heading) . '</h1>';
    $text = $heading . "\n" . str_repeat('=', mb_strlen($heading)) . "\n\n";
    foreach ($paragraphs as $p) {
        $html .= '<p style="margin:0 0 14px;font-size:15px;line-height:1.55;">' . $e($p) . '</p>';
        $text .= wordwrap($p, 76) . "\n\n";
    }
    if ($rows) {
        $html .= '<table role="presentation" style="width:100%;border-collapse:collapse;margin:8px 0 18px;font-size:14px;">';
        foreach ($rows as [$label, $value]) {
            $html .= '<tr><td style="padding:6px 0;color:#6b6f7d;border-bottom:1px solid #e2e5e9;">' . $e($label) . '</td>'
                . '<td style="padding:6px 0;text-align:right;font-weight:bold;border-bottom:1px solid #e2e5e9;">' . $e($value) . '</td></tr>';
            $text .= str_pad($label . ':', 18) . $value . "\n";
        }
        $html .= '</table>';
        $text .= "\n";
    }
    if ($button) {
        [$label, $url] = $button;
        $html .= '<p style="margin:20px 0;"><a href="' . $e($url) . '" style="display:inline-block;background:#d23f1c;color:#ffffff;text-decoration:none;font-weight:bold;padding:12px 22px;border-radius:999px;">' . $e($label) . '</a></p>'
            . '<p style="margin:0 0 14px;font-size:13px;color:#6b6f7d;">If the button doesn\'t work, copy this address into your browser:<br><span style="word-break:break-all;">' . $e($url) . '</span></p>';
        $text .= $label . ': ' . $url . "\n\n";
    }
    $html .= '<p style="margin:24px 0 0;font-size:12px;color:#6b6f7d;">' . $e($site) . ' · ' . $e(seo_url()) . '</p></div></body></html>';
    $text .= '-- ' . $site . ' · ' . seo_url() . "\n";
    return [$html, $text];
}

function mail_password_reset(PDO $dbh, string $to, string $link, int $minutes, bool $admin = false, ?string &$error = null): bool
{
    [$html, $text] = mail_layout($admin ? 'Reset your admin password' : 'Reset your password', [
        'Someone (hopefully you) asked to reset the password for your ' . content_raw('site_name') . ($admin ? ' admin account.' : ' account.'),
        "The link works once, for $minutes minutes. If you didn't ask for it, you can ignore this email: your password stays the same.",
    ], ['Choose a new password', $link]);
    return mail_send($dbh, $to, ($admin ? 'Admin password reset' : 'Reset your password') . ' | ' . content_raw('site_name'), $html, $text, $error, $admin ? 'admin-password-reset' : 'password-reset');
}

// Booking rows for the emails: dates, days and the stored price.
function mail_booking_rows(object $booking, string $carName): array
{
    $total = booking_total($booking); // the stored quoted price
    $days = $total['rental_days'];
    return [
        ['Booking', '#' . $booking->BookingNumber],
        ['Car', $carName],
        ['Pickup', date('D j M Y', strtotime($booking->FromDate))],
        ['Return', date('D j M Y', strtotime($booking->ToDate))],
        ['Days', (string)$days],
        ['Total', format_price($total['total'])],
    ];
}

// Booking + car + customer name for a booking id.
function mail_booking_lookup(PDO $dbh, int $bookingId): ?array
{
    $q = $dbh->prepare("SELECT b.*, v.VehiclesTitle, v.PricePerDay, br.BrandName, u.FullName FROM tblbooking b
        JOIN tblvehicles v ON v.id = b.VehicleId LEFT JOIN tblbrands br ON br.id = v.VehiclesBrand
        LEFT JOIN tblusers u ON u.EmailId = b.userEmail WHERE b.id = :id");
    $q->execute([':id' => $bookingId]);
    $b = $q->fetch(PDO::FETCH_OBJ);
    return $b ? [$b, vehicle_display_name($b->BrandName, $b->VehiclesTitle)] : null;
}

function mail_booking_received(PDO $dbh, int $bookingId): bool
{
    if (!mail_configured($dbh) || !($found = mail_booking_lookup($dbh, $bookingId))) return false;
    [$b, $car] = $found;
    [$html, $text] = mail_layout('We received your booking request', [
        'Hello' . ($b->FullName ? ' ' . $b->FullName : '') . ', thank you for booking with ' . content_raw('site_name') . '.',
        'We are checking that the car is ready for your dates and will confirm the booking shortly. The total below is the price we saved for your request.',
    ], ['See your booking', seo_url('my-booking')], mail_booking_rows($b, $car));
    return mail_send($dbh, $b->userEmail, 'Booking request #' . $b->BookingNumber . ' received | ' . content_raw('site_name'), $html, $text, $error, 'booking-received', (string)$b->FullName);
}

function mail_booking_confirmed(PDO $dbh, int $bookingId): bool
{
    if (!mail_configured($dbh) || !($found = mail_booking_lookup($dbh, $bookingId))) return false;
    [$b, $car] = $found;
    [$html, $text] = mail_layout('Your booking is confirmed', [
        'Hello' . ($b->FullName ? ' ' . $b->FullName : '') . ', your ' . $car . ' is booked.',
        'Please bring your driving licence and a photo ID at pickup. You can print an invoice from My bookings.',
    ], ['View invoice', seo_url('invoice?booking=' . rawurlencode((string)$b->BookingNumber))], mail_booking_rows($b, $car));
    return mail_send($dbh, $b->userEmail, 'Booking #' . $b->BookingNumber . ' confirmed | ' . content_raw('site_name'), $html, $text, $error, 'booking-confirmed', (string)$b->FullName);
}

// $in: the enquiry fields (fullname, email, traveldate, passengers, vehicletype, pickuplocation); $title: package or car.
function mail_enquiry_received(PDO $dbh, array $in, string $title): bool
{
    if ($in['email'] === '' || !mail_configured($dbh)) return false;
    $rows = [['Request', $title]];
    if (!empty($in['traveldate'])) $rows[] = ['Travel date', date('D j M Y', strtotime($in['traveldate']))];
    if (!empty($in['passengers'])) $rows[] = ['Passengers', (string)$in['passengers']];
    if (!empty($in['vehicletype'])) $rows[] = ['Vehicle', (string)$in['vehicletype']];
    if (!empty($in['pickuplocation'])) $rows[] = ['Pickup', (string)$in['pickuplocation']];
    [$html, $text] = mail_layout('We received your request', [
        'Hello ' . $in['fullname'] . ', thank you for your request.',
        'We will call you on the number you gave us to confirm the price and the details. Nothing is booked or paid yet.',
    ], null, $rows);
    return mail_send($dbh, $in['email'], 'Request received: ' . $title . ' | ' . content_raw('site_name'), $html, $text, $error, 'enquiry-received', (string)$in['fullname']);
}
