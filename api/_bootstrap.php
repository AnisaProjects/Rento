<?php
// Shared setup for the JSON API (api/*.php): read-only GET endpoints, JSON errors with
// proper status codes, per-IP rate limiting, and never a PHP or database error in a response.
//   api_require_get();  api_rate_limit('api_quote', 120);
//   api_error(400, 'invalid_dates', 'The return date must be on or after the pickup date.');
//   api_json([...]);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

// No session for the API: include config (DB, helpers, pricing) but not security.php.
$isAdminArea = false;
require_once __DIR__ . '/../includes/config.php';

// Any uncaught error becomes a generic 500; the details go to the PHP log only.
set_exception_handler(function (Throwable $e) {
    error_log('[Rento API] ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    api_error(500, 'server_error', 'Something went wrong. Please try again.');
});
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

function api_json(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    exit;
}

function api_error(int $status, string $code, string $message, array $extra = []): void
{
    api_json(['ok' => false, 'error' => ['code' => $code, 'message' => $message] + $extra], $status);
}

function api_require_get(): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, ['GET', 'HEAD'], true)) {
        header('Allow: GET');
        api_error(405, 'method_not_allowed', 'Use GET.');
    }
}

// Per-IP limit: $max requests per RATE_LIMIT_WINDOW_MINUTES for this endpoint.
function api_rate_limit(string $action, int $max): void
{
    global $dbh;
    if (rate_limit_exceeded($dbh, $action, null, $max)) {
        header('Retry-After: ' . (RATE_LIMIT_WINDOW_MINUTES * 60));
        api_error(429, 'rate_limited', 'Too many requests. Please try again in ' . RATE_LIMIT_WINDOW_MINUTES . ' minutes.');
    }
    rate_limit_record($dbh, $action, null);
}

// A GET parameter as a string ('' when missing or not a string).
function api_param(string $key): string
{
    $value = $_GET[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function api_date(string $key): ?string
{
    $value = api_param($key);
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return ($date && $date->format('Y-m-d') === $value) ? $value : null;
}

// Active car by id, or a 404/400 response.
function api_car(): object
{
    global $dbh;
    $id = api_param('car');
    if (!ctype_digit($id) || (int)$id < 1) {
        api_error(400, 'invalid_car', 'car must be a vehicle id.');
    }
    $query = $dbh->prepare("SELECT v.id, v.VehiclesTitle, v.PricePerDay, v.CarType, v.SeatingCapacity, v.FuelType, v.DriverOnly, br.BrandName
        FROM tblvehicles v LEFT JOIN tblbrands br ON br.id = v.VehiclesBrand WHERE v.id = :id AND v.IsActive = 1");
    $query->execute([':id' => (int)$id]);
    $car = $query->fetch(PDO::FETCH_OBJ);
    if (!$car) {
        api_error(404, 'car_not_found', 'No active car with that id.');
    }
    return $car;
}

function api_car_summary(object $car): array
{
    return [
        'id' => (int)$car->id,
        'name' => vehicle_display_name($car->BrandName, $car->VehiclesTitle),
        'type' => $car->CarType,
        'seats' => (int)$car->SeatingCapacity,
        'fuel' => $car->FuelType,
        'base_price' => (int)$car->PricePerDay,
        'driver_only' => (bool)$car->DriverOnly,
        'url' => vehicle_url($car->id, $car->BrandName, $car->VehiclesTitle),
    ];
}
