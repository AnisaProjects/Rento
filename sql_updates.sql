-- Database updates for Car Recommendation System
-- Add CarType column to tblvehicles table

ALTER TABLE `tblvehicles` ADD COLUMN `CarType` VARCHAR(20) DEFAULT 'Sedan' AFTER `SeatingCapacity`;

-- Update existing vehicles with car type classification
UPDATE `tblvehicles` SET `CarType` = 'Hatchback' WHERE `id` = 1; -- Maruti Suzuki Wagon R
UPDATE `tblvehicles` SET `CarType` = 'Sedan' WHERE `id` = 2; -- BMW 5 Series
UPDATE `tblvehicles` SET `CarType` = 'SUV' WHERE `id` = 3; -- Audi Q8
UPDATE `tblvehicles` SET `CarType` = 'SUV' WHERE `id` = 4; -- Nissan Kicks
UPDATE `tblvehicles` SET `CarType` = 'Sedan' WHERE `id` = 5; -- Nissan GT-R
UPDATE `tblvehicles` SET `CarType` = 'Sedan' WHERE `id` = 6; -- Nissan Sunny 2020
UPDATE `tblvehicles` SET `CarType` = 'SUV' WHERE `id` = 7; -- Toyota Fortuner

-- Add index for better performance
CREATE INDEX idx_cartype ON `tblvehicles` (`CarType`);
CREATE INDEX idx_price ON `tblvehicles` (`PricePerDay`);
