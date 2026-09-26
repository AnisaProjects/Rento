-- Phase 0 follow-up: throttle failed logins, reset requests and email checks (additive only).
-- utf8mb4 so any email text a visitor types can be stored without a conversion error.
CREATE TABLE IF NOT EXISTS `tblratelimits` (
  `id` int NOT NULL AUTO_INCREMENT,
  `Action` varchar(30) NOT NULL,
  `IpAddress` varchar(45) NOT NULL,
  `Email` varchar(191) DEFAULT NULL,
  `CreatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ActionIp` (`Action`,`IpAddress`,`CreatedAt`),
  KEY `ActionEmail` (`Action`,`Email`,`CreatedAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
