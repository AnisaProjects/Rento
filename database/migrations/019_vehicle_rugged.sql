-- "Rugged / off-road capable" flag for vehicles (additive, default off), used by the recommendation
-- engine for mountain and rough-road trips. Set on the ladder-frame 4x4-capable models in the fleet.
ALTER TABLE `tblvehicles` ADD COLUMN `Rugged` tinyint(1) NOT NULL DEFAULT 0 AFTER `DriverOnly`;
UPDATE `tblvehicles` SET `Rugged` = 1 WHERE `id` IN (7, 26, 28); -- Toyota Fortuner, Mahindra Scorpio, Toyota Hilux
