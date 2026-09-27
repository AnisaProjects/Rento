-- Price stored with each booking (additive). Set on the server when the booking is created
-- (the browser's price is never used). Old bookings keep NULL and use days x base price.
ALTER TABLE `tblbooking`
  ADD COLUMN `quoted_price` int DEFAULT NULL AFTER `Status`,
  ADD COLUMN `pricing_details` text COMMENT 'JSON: rental_days, base_total, per_day [{date, price, pct, reasons}], pricing, source' AFTER `quoted_price`,
  ADD COLUMN `priced_at` datetime DEFAULT NULL AFTER `pricing_details`;
