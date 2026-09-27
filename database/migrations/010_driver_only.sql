-- Driver-only vehicles (additive): shown as "With driver" on the website, with an enquiry
-- form instead of self-drive booking. Enquiries can now be for a vehicle as well as a package.
ALTER TABLE `tblvehicles` ADD COLUMN `DriverOnly` tinyint(1) NOT NULL DEFAULT 0 AFTER `IsActive`;
ALTER TABLE `tblpackageenquiries` ADD COLUMN `VehicleId` int DEFAULT NULL AFTER `PackageId`, ADD KEY `VehicleId` (`VehicleId`);
-- The 14-seat Hiace needs a heavy-vehicle licence to self-drive, so it is rented with a driver.
UPDATE `tblvehicles` SET `DriverOnly` = 1 WHERE `VehiclesTitle` = 'Toyota Hiace Commuter';
