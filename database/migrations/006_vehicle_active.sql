-- Vehicles can be hidden from the public site and Find My Car (additive only).
-- Existing vehicles stay active (1); new market-research vehicles are added as inactive (0).
ALTER TABLE `tblvehicles`
  ADD COLUMN `IsActive` tinyint(1) NOT NULL DEFAULT 1 AFTER `CarType`,
  ADD KEY `IsActive` (`IsActive`);
