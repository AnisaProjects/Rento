<?php
// Throttles repeated attempts (failed logins, reset requests, email checks) per IP and per email.
// Limits are set in includes/config.php.

const RATE_LIMIT_MESSAGE = 'Too many attempts. Please try again in ' . RATE_LIMIT_WINDOW_MINUTES . ' minutes.';

function rate_limit_client_ip(): string
{
    // REMOTE_ADDR only: forwarded headers are set by the client and can't be trusted here.
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 45);
}

function rate_limit_email(?string $email): ?string
{
    return $email === null ? null : substr(strtolower(trim($email)), 0, 191);
}

// True when this IP, or this email, already used up $maxAttempts for $action within the window.
function rate_limit_exceeded(PDO $dbh, string $action, ?string $email = null, int $maxAttempts = RATE_LIMIT_MAX_ATTEMPTS): bool
{
    $window = "CreatedAt > NOW() - INTERVAL " . (int)RATE_LIMIT_WINDOW_MINUTES . " MINUTE";
    $ip = rate_limit_client_ip();
    $query = $dbh->prepare("SELECT COUNT(*) FROM tblratelimits WHERE Action=:action AND IpAddress=:ip AND $window");
    $query->bindParam(':action', $action, PDO::PARAM_STR);
    $query->bindParam(':ip', $ip, PDO::PARAM_STR);
    $query->execute();
    if ((int)$query->fetchColumn() >= $maxAttempts) {
        return true;
    }
    $email = rate_limit_email($email);
    if ($email === null || $email === '') {
        return false;
    }
    $query = $dbh->prepare("SELECT COUNT(*) FROM tblratelimits WHERE Action=:action AND Email=:email AND $window");
    $query->bindParam(':action', $action, PDO::PARAM_STR);
    $query->bindParam(':email', $email, PDO::PARAM_STR);
    $query->execute();
    return (int)$query->fetchColumn() >= $maxAttempts;
}

// Records one attempt for $action from this IP (and email, when given).
function rate_limit_record(PDO $dbh, string $action, ?string $email = null): void
{
    $ip = rate_limit_client_ip();
    $email = rate_limit_email($email);
    $query = $dbh->prepare("INSERT INTO tblratelimits(Action, IpAddress, Email) VALUES(:action, :ip, :email)");
    $query->bindParam(':action', $action, PDO::PARAM_STR);
    $query->bindParam(':ip', $ip, PDO::PARAM_STR);
    $query->bindParam(':email', $email, $email === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $query->execute();
    // Occasionally drop rows that can no longer matter.
    if (random_int(1, 50) === 1) {
        $dbh->exec("DELETE FROM tblratelimits WHERE CreatedAt < NOW() - INTERVAL 1 DAY");
    }
}

// Forgets an email's failed attempts after it logs in successfully (the IP's record stays).
function rate_limit_clear(PDO $dbh, string $action, string $email): void
{
    $email = rate_limit_email($email);
    $query = $dbh->prepare("DELETE FROM tblratelimits WHERE Action=:action AND Email=:email");
    $query->bindParam(':action', $action, PDO::PARAM_STR);
    $query->bindParam(':email', $email, PDO::PARAM_STR);
    $query->execute();
}
?>
