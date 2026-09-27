<?php
// Exports facts used by the report evaluations (tests/evaluation/*.py) as JSON on stdout:
// active cars, the current price forecast, effective pricing settings and festivals. No personal data.
//   php tools/export-report-data.php > /tmp/report-data.json
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
chdir(dirname(__DIR__));
require __DIR__ . '/../includes/config.php';
$cars = $dbh->query("SELECT v.id, v.VehiclesTitle, b.BrandName, v.CarType, v.SeatingCapacity, v.FuelType, v.PricePerDay, v.IsActive, v.DriverOnly, v.Rugged
    FROM tblvehicles v JOIN tblbrands b ON b.id = v.VehiclesBrand ORDER BY v.id")->fetchAll(PDO::FETCH_ASSOC);
$forecast = $dbh->query("SELECT VehicleId, ForecastDate, BasePrice, Multiplier, Price, Reasons, IsAvailable FROM tbl_price_forecast ORDER BY VehicleId, ForecastDate")->fetchAll(PDO::FETCH_ASSOC);
foreach ($forecast as &$row) $row['Reasons'] = json_decode((string)$row['Reasons'], true);
unset($row);
$settings = [];
foreach (array_keys(SETTINGS) as $key) $settings[$key] = setting($key);
$festivals = $dbh->query("SELECT FestivalName, StartDate, EndDate, TravelFrom, TravelTo, TravelPct, DemandLevel, Verification FROM tblfestivals ORDER BY StartDate")->fetchAll(PDO::FETCH_ASSOC);
$counts = ['bookings' => (int)$dbh->query("SELECT COUNT(*) FROM tblbooking")->fetchColumn(),
    'confirmed' => (int)$dbh->query("SELECT COUNT(*) FROM tblbooking WHERE Status = 1")->fetchColumn()];
echo json_encode(['generated' => date('c'), 'cars' => $cars, 'forecast' => $forecast, 'settings' => $settings, 'festivals' => $festivals, 'counts' => $counts], JSON_PRETTY_PRINT);
