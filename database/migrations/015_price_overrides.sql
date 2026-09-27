-- Manual price overrides (additive), set in admin -> AI Insights. The forecast uses the
-- override's price for those days (labelled "Manual price"); the newest override wins.
CREATE TABLE IF NOT EXISTS `tbl_price_overrides` (
  `id` int NOT NULL AUTO_INCREMENT,
  `VehicleId` int NOT NULL,
  `FromDate` date NOT NULL,
  `ToDate` date NOT NULL,
  `Price` int NOT NULL,
  `Note` varchar(200) DEFAULT NULL,
  `CreatedBy` varchar(100) DEFAULT NULL,
  `CreatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `VehicleDates` (`VehicleId`, `FromDate`, `ToDate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
