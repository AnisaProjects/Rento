-- Optional search description per vehicle and package (additive; empty = built from the car or package data).
ALTER TABLE `tblvehicles` ADD COLUMN `SeoDescription` varchar(300) NULL DEFAULT NULL AFTER `VehiclesOverview`;
ALTER TABLE `tblpackages` ADD COLUMN `SeoDescription` varchar(300) NULL DEFAULT NULL AFTER `Description`;
