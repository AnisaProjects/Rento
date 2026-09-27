-- Packages can opt in to the festival and travel-day surcharges (additive, default off).
ALTER TABLE `tblpackages` ADD COLUMN `FestivalPricing` tinyint(1) NOT NULL DEFAULT 0 AFTER `StartingPrice`;
