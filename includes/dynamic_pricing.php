<?php
/**
 * Bridge between PHP and the Python-based dynamic pricing model.
 */

if (!class_exists('DynamicPricingService')) {
    class DynamicPricingService
    {
        private PDO $dbh;
        private string $pythonBinary;
        private ?string $scriptPath;
        private int $defaultNights;
        private array $cache = [];

        public function __construct(PDO $dbh, array $options = [])
        {
            $this->dbh = $dbh;
            $this->pythonBinary = $options['python'] ?? getenv('PRICING_PYTHON_BIN') ?? 'python';
            $this->scriptPath = $options['script'] ?? realpath(__DIR__ . '/../ml/predict_price.py');
            $this->defaultNights = (int)($options['default_nights'] ?? 1);
        }

        public function quote(int $vehicleId, array $options = []): array
        {
            $startDate = $options['start_date'] ?? date('Y-m-d', strtotime('+1 day'));
            $endDate = $options['end_date'] ?? date('Y-m-d', strtotime("+{$this->defaultNights} day", strtotime($startDate)));
            $basePrice = $options['base_price'] ?? $this->fetchBasePrice($vehicleId);

            $cacheKey = implode('|', [$vehicleId, $startDate, $endDate, number_format((float)$basePrice, 2, '.', '')]);
            if (isset($this->cache[$cacheKey])) {
                return $this->cache[$cacheKey];
            }

            if (!$basePrice) {
                return $this->storeAndReturn($cacheKey, $this->fallbackQuote($vehicleId, $basePrice));
            }

            if (!$this->scriptPath || !file_exists($this->scriptPath)) {
                return $this->storeAndReturn($cacheKey, $this->fallbackQuote($vehicleId, $basePrice, 'script_missing'));
            }

            $command = $this->buildCommand($vehicleId, $basePrice, $startDate, $endDate, $options);
            $raw = shell_exec($command);

            if (!$raw) {
                return $this->storeAndReturn($cacheKey, $this->fallbackQuote($vehicleId, $basePrice, 'no_output'));
            }

            $decoded = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->storeAndReturn($cacheKey, $this->fallbackQuote($vehicleId, $basePrice, 'json_error'));
            }

            $decoded['status'] = $decoded['status'] ?? 'model';
            return $this->storeAndReturn($cacheKey, $decoded);
        }

        private function buildCommand(int $vehicleId, float $basePrice, string $startDate, string $endDate, array $options): string
        {
            $python = escapeshellarg($this->pythonBinary);
            $parts = [
                $python,
                escapeshellarg($this->scriptPath),
                '--vehicle-id=' . escapeshellarg((string)$vehicleId),
                '--base-price=' . escapeshellarg((string)$basePrice),
                '--start-date=' . escapeshellarg($startDate),
                '--end-date=' . escapeshellarg($endDate),
                '--db-host=' . escapeshellarg(DB_HOST),
                '--db-user=' . escapeshellarg(DB_USER),
                '--db-pass=' . escapeshellarg(DB_PASS),
                '--db-name=' . escapeshellarg(DB_NAME),
            ];

            if (!empty($options['model_path'])) {
                $parts[] = '--model-path=' . escapeshellarg($options['model_path']);
            }

            return implode(' ', $parts);
        }

        private function fetchBasePrice(int $vehicleId): ?float
        {
            $sql = "SELECT PricePerDay FROM tblvehicles WHERE id = :vehicle";
            $stmt = $this->dbh->prepare($sql);
            $stmt->bindParam(':vehicle', $vehicleId, PDO::PARAM_INT);
            $stmt->execute();
            $price = $stmt->fetchColumn();
            return $price !== false ? (float)$price : null;
        }

        private function fallbackQuote(int $vehicleId, ?float $basePrice, string $reason = 'fallback'): array
        {
            return [
                'status' => $reason,
                'vehicle_id' => $vehicleId,
                'base_price' => $basePrice,
                'recommended_price' => $basePrice,
                'multiplier' => 1.0,
                'probability' => 0.5,
            ];
        }

        private function storeAndReturn(string $cacheKey, array $payload): array
        {
            $this->cache[$cacheKey] = $payload;
            return $payload;
        }
    }
}

if (!function_exists('dynamic_price_quote')) {
    function dynamic_price_quote(PDO $dbh, int $vehicleId, array $options = []): array
    {
        static $service = null;
        if (!$service) {
            $service = new DynamicPricingService($dbh);
        }
        return $service->quote($vehicleId, $options);
    }
}

