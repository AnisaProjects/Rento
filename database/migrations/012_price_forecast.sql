-- Smart pricing forecast (additive). Filled by ml/batch_forecast.py for every active car and
-- the next N days (admin -> Smart Pricing, default 60). The website reads prices from here;
-- a missing row falls back to the base price (and is logged).
CREATE TABLE IF NOT EXISTS `tbl_price_forecast` (
  `VehicleId` int NOT NULL,
  `ForecastDate` date NOT NULL,
  `BasePrice` int NOT NULL,
  `Multiplier` decimal(6,4) NOT NULL DEFAULT 1.0000,
  `Price` int NOT NULL,
  `Reasons` text COMMENT 'JSON: [{"label":"Dashain 2026","pct":12}, ...]',
  `IsAvailable` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0 = already booked that day',
  `RunId` int DEFAULT NULL,
  `ComputedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`VehicleId`, `ForecastDate`),
  KEY `ForecastDate` (`ForecastDate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per recompute request (admin button, automatic after changes, nightly job).
CREATE TABLE IF NOT EXISTS `tbl_forecast_runs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `TriggerReason` varchar(120) NOT NULL,
  `Status` varchar(12) NOT NULL DEFAULT 'queued' COMMENT 'queued, running, done, failed',
  `RequestedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `StartedAt` datetime DEFAULT NULL,
  `FinishedAt` datetime DEFAULT NULL,
  `Cars` int DEFAULT NULL,
  `RowsWritten` int DEFAULT NULL,
  `Message` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `Status` (`Status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
