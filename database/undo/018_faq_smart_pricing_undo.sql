-- Restores the FAQ answer from before migration 018.
SET NAMES utf8mb4;
UPDATE `tblpages` SET `detail` = REPLACE(`detail`,
  '<p>Prices are in Nepalese rupees and can change from day to day. Each car has a usual daily price, and the price of each day is adjusted within set limits for weekends, festivals and travel days, and the tourist season. Your total is the sum of the days you book, and a same-day rental counts as one day.</p><p>The price calendar on each car''s page shows which days are cheaper. Before you send a request you see the price of each day and the total. That total is saved with your booking and is the price on your invoice, even if prices change later.</p>',
  '<p>Every car has one daily price in Nepalese rupees. Your total is the number of days multiplied by the daily price, and a same-day rental counts as one day. You see the total before you send the request.</p>')
WHERE `type` = 'faqs';
