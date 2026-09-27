<?php
/**
 * Kept for compatibility: dynamic_price_quote() now reads the precomputed smart-pricing
 * forecast (includes/pricing.php, tbl_price_forecast) instead of starting Python on every
 * page view. The Python scripts run in the background (ml/batch_forecast.py) and never get
 * database credentials as command-line arguments.
 */

if (!function_exists('dynamic_price_quote')) {
    // Returns ['status' => 'forecast'|'base'|'disabled', 'recommended_price' => per-day price,
    //          'multiplier', 'quote' => pricing_quote() result].
    function dynamic_price_quote(PDO $dbh, int $vehicleId, array $options = []): array
    {
        $start = $options['start_date'] ?? date('Y-m-d', strtotime('+1 day'));
        $end = $options['end_date'] ?? $start;
        $car = $dbh->prepare("SELECT id, PricePerDay FROM tblvehicles WHERE id = :id");
        $car->execute([':id' => $vehicleId]);
        $car = $car->fetch(PDO::FETCH_OBJ);
        $base = (float)($options['base_price'] ?? ($car->PricePerDay ?? 0));
        if (!$car || !PRICING_ENABLED) {
            return ['status' => PRICING_ENABLED ? 'base' : 'disabled', 'vehicle_id' => $vehicleId, 'base_price' => $base,
                'recommended_price' => $base, 'multiplier' => 1.0];
        }
        $quote = pricing_quote($dbh, $car, $start, $end);
        $perDay = $quote['ok'] ? $quote['total'] / max(1, $quote['rental_days']) : $base;
        return ['status' => $quote['source'] ?? 'base', 'vehicle_id' => $vehicleId, 'base_price' => $base,
            'recommended_price' => round($perDay), 'multiplier' => $base > 0 ? round($perDay / $base, 4) : 1.0, 'quote' => $quote];
    }
}
