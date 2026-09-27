-- Festival travel days (additive): a window before the main festival days, when people travel
-- home, with its own surcharge. TravelPct NULL = the default (admin -> Smart Pricing).
ALTER TABLE `tblfestivals`
  ADD COLUMN `TravelFrom` date DEFAULT NULL AFTER `EndDate`,
  ADD COLUMN `TravelTo` date DEFAULT NULL AFTER `TravelFrom`,
  ADD COLUMN `TravelPct` decimal(5,2) DEFAULT NULL AFTER `TravelTo`;

-- Starting windows from the verified dates (edit them in admin -> Festivals):
-- Dashain: two days before Ghatasthapana up to Fulpati. Tihar: the three days before Laxmi Puja.
UPDATE `tblfestivals` SET TravelFrom = '2026-10-09', TravelTo = '2026-10-17' WHERE FestivalName = 'Dashain 2026 (BS 2083)' AND TravelFrom IS NULL;
UPDATE `tblfestivals` SET TravelFrom = '2026-11-05', TravelTo = '2026-11-07' WHERE FestivalName = 'Tihar 2026 (BS 2083)' AND TravelFrom IS NULL;
UPDATE `tblfestivals` SET TravelFrom = '2027-09-28', TravelTo = '2027-10-06' WHERE FestivalName = 'Dashain 2027 (BS 2084)' AND TravelFrom IS NULL;
UPDATE `tblfestivals` SET TravelFrom = '2027-10-26', TravelTo = '2027-10-28' WHERE FestivalName = 'Tihar 2027 (BS 2084)' AND TravelFrom IS NULL;
