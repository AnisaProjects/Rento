-- Packages & services (additive): categories, packages, gallery images and enquiries.
-- Seed: the 16 suggested packages from docs/market-research.md, all INACTIVE (IsActive = 0).
-- Prices are suggestions; descriptions are Rento's own. Enquiries only, no online payment.
CREATE TABLE IF NOT EXISTS `tblpackagecategories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `CategoryName` varchar(80) NOT NULL,
  `Slug` varchar(80) NOT NULL,
  `Icon` varchar(40) NOT NULL DEFAULT 'fa-route',
  `SortOrder` int NOT NULL DEFAULT 0,
  `IsActive` tinyint(1) NOT NULL DEFAULT 1,
  `CreatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `Slug` (`Slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `tblpackages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `CategoryId` int DEFAULT NULL,
  `Title` varchar(150) NOT NULL,
  `Slug` varchar(160) NOT NULL,
  `Summary` varchar(300) DEFAULT NULL,
  `Description` text,
  `DurationLabel` varchar(80) DEFAULT NULL,
  `DurationDays` int NOT NULL DEFAULT 1,
  `RouteStops` varchar(300) DEFAULT NULL,
  `StartingPrice` int NOT NULL DEFAULT 0,
  `PricesJson` text COMMENT 'JSON: {"Sedan": 6500, "SUV": 9000}',
  `ItineraryJson` text COMMENT 'JSON: [{"day":1,"title":"...","text":"..."}]',
  `Inclusions` text COMMENT 'one per line',
  `Exclusions` text COMMENT 'one per line',
  `CoverImage` varchar(160) DEFAULT NULL,
  `SortOrder` int NOT NULL DEFAULT 0,
  `IsActive` tinyint(1) NOT NULL DEFAULT 0,
  `CreatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `CategoryId` (`CategoryId`),
  KEY `Active` (`IsActive`, `SortOrder`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `tblpackageimages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `PackageId` int NOT NULL,
  `ImagePath` varchar(160) NOT NULL,
  `SortOrder` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `PackageId` (`PackageId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `tblpackageenquiries` (
  `id` int NOT NULL AUTO_INCREMENT,
  `PackageId` int DEFAULT NULL,
  `PackageTitle` varchar(150) NOT NULL COMMENT 'copy of the title when the request was sent',
  `FullName` varchar(120) NOT NULL,
  `Phone` varchar(20) NOT NULL,
  `Email` varchar(120) DEFAULT NULL,
  `TravelDate` date NOT NULL,
  `PickupLocation` varchar(200) NOT NULL,
  `Passengers` int NOT NULL DEFAULT 1,
  `VehicleType` varchar(30) DEFAULT NULL,
  `Notes` text,
  `Status` varchar(12) NOT NULL DEFAULT 'new' COMMENT 'new, contacted, confirmed, cancelled',
  `AdminNotes` text,
  `CreatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `Status` (`Status`),
  KEY `PackageId` (`PackageId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO tblpackagecategories (CategoryName, Slug, Icon, SortOrder) SELECT 'Tour', 'tours', 'fa-route', 10 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tblpackagecategories WHERE Slug = 'tours');
INSERT INTO tblpackagecategories (CategoryName, Slug, Icon, SortOrder) SELECT 'Airport transfer', 'airport-transfers', 'fa-plane-arrival', 20 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tblpackagecategories WHERE Slug = 'airport-transfers');
INSERT INTO tblpackagecategories (CategoryName, Slug, Icon, SortOrder) SELECT 'City ride', 'city-rides', 'fa-city', 30 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tblpackagecategories WHERE Slug = 'city-rides');
INSERT INTO tblpackagecategories (CategoryName, Slug, Icon, SortOrder) SELECT 'Hourly', 'hourly-hire', 'fa-clock', 40 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tblpackagecategories WHERE Slug = 'hourly-hire');
INSERT INTO tblpackagecategories (CategoryName, Slug, Icon, SortOrder) SELECT 'Event', 'events', 'fa-champagne-glasses', 50 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tblpackagecategories WHERE Slug = 'events');
INSERT INTO tblpackagecategories (CategoryName, Slug, Icon, SortOrder) SELECT 'Corporate', 'corporate', 'fa-briefcase', 60 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tblpackagecategories WHERE Slug = 'corporate');

INSERT INTO tblpackages (CategoryId, Title, Slug, Summary, Description, DurationLabel, DurationDays, RouteStops, StartingPrice, PricesJson, ItineraryJson, Inclusions, Exclusions, CoverImage, SortOrder, IsActive)
  SELECT (SELECT id FROM tblpackagecategories WHERE CategoryName = 'Airport transfer' LIMIT 1), 'Kathmandu Airport Pickup & Drop', 'kathmandu-airport-pickup-drop', 'Stress-free rides between Tribhuvan International Airport and your door anywhere in the valley.', 'Your driver tracks your flight and waits in arrivals with a name board, then takes you straight to your hotel or home in Kathmandu, Lalitpur or Bhaktapur. Drop-offs are timed so you reach the terminal with room to spare.', 'One way (up to 2 hours)', 1, '', 1300, '{"Hatchback": 1300, "Sedan": 1500, "SUV": 2500, "Van": 3500}', '[{"day": 1, "title": "Airport transfer", "text": "Meet at TIA arrivals (or pickup from your address for departures) and a direct ride to the destination within the valley."}]', 'Driver
Fuel
Airport parking
Flight tracking
Name-board meet and greet', 'Waiting beyond 60 minutes after landing
Extra stops en route', 'assets/images/placeholder-package.jpg', 10, 0
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tblpackages WHERE Slug = 'kathmandu-airport-pickup-drop');
INSERT INTO tblpackages (CategoryId, Title, Slug, Summary, Description, DurationLabel, DurationDays, RouteStops, StartingPrice, PricesJson, ItineraryJson, Inclusions, Exclusions, CoverImage, SortOrder, IsActive)
  SELECT (SELECT id FROM tblpackagecategories WHERE CategoryName = 'Airport transfer' LIMIT 1), 'Pokhara & Bhairahawa Airport Transfer', 'pokhara-bhairahawa-airport-transfer', 'Arrival and departure rides for Pokhara International and Gautam Buddha International airports.', 'Land in Pokhara and head to Lakeside, or fly into Bhairahawa and go straight on to Lumbini. The price is fixed, so there''s no haggling at the terminal.', 'One way', 1, '', 1500, '{"Hatchback": 1500, "Sedan": 1800, "SUV": 2800, "Van": 3800}', '[{"day": 1, "title": "Airport transfer", "text": "Pickup at Pokhara or Gautam Buddha airport and a direct drop at your hotel in Pokhara, Bhairahawa or Lumbini (or the reverse)."}]', 'Driver
Fuel
Airport parking', 'Extra stops
Waiting beyond 60 minutes', 'assets/images/placeholder-package.jpg', 20, 0
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tblpackages WHERE Slug = 'pokhara-bhairahawa-airport-transfer');
INSERT INTO tblpackages (CategoryId, Title, Slug, Summary, Description, DurationLabel, DurationDays, RouteStops, StartingPrice, PricesJson, ItineraryJson, Inclusions, Exclusions, CoverImage, SortOrder, IsActive)
  SELECT (SELECT id FROM tblpackagecategories WHERE CategoryName = 'City ride' LIMIT 1), 'City Ride (Point-to-Point)', 'city-ride-point-to-point', 'A private, fixed-fare ride between any two points in Kathmandu valley or Pokhara.', 'Book a clean, air-conditioned car for a single trip across town, whether it''s a meeting, a hospital visit or a family dinner. The fare is agreed upfront and there''s no meter.', 'One way, up to 15 km', 1, '', 1000, '{"Hatchback": 1000, "Sedan": 1200, "SUV": 1800, "Van": 2500}', '[{"day": 1, "title": "Direct ride", "text": "Pickup at your chosen address and a direct drop within 15 km."}]', 'Driver
Fuel', 'Parking at destination
Extra distance beyond 15 km (Rs 60/km)', 'assets/images/placeholder-package.jpg', 30, 0
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tblpackages WHERE Slug = 'city-ride-point-to-point');
INSERT INTO tblpackages (CategoryId, Title, Slug, Summary, Description, DurationLabel, DurationDays, RouteStops, StartingPrice, PricesJson, ItineraryJson, Inclusions, Exclusions, CoverImage, SortOrder, IsActive)
  SELECT (SELECT id FROM tblpackagecategories WHERE CategoryName = 'Hourly' LIMIT 1), '4-Hour Car with Driver', '4-hour-car-with-driver', 'Keep a car and driver on hand for errands, shopping or short meetings.', 'Set your own stops and the driver waits while you finish each one. It suits a busy half day around the valley.', '4 hours / 40 km', 1, '', 3000, '{"Hatchback": 3000, "Sedan": 3500, "SUV": 5000, "Van": 5500}', '[{"day": 1, "title": "Half-day disposal", "text": "Four hours of flexible use inside the valley, up to 40 km."}]', 'Driver
Fuel for 40 km
Tolls', 'Parking
Overtime (Rs 600-900 per hour)
Extra km', 'assets/images/placeholder-package.jpg', 40, 0
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tblpackages WHERE Slug = '4-hour-car-with-driver');
INSERT INTO tblpackages (CategoryId, Title, Slug, Summary, Description, DurationLabel, DurationDays, RouteStops, StartingPrice, PricesJson, ItineraryJson, Inclusions, Exclusions, CoverImage, SortOrder, IsActive)
  SELECT (SELECT id FROM tblpackagecategories WHERE CategoryName = 'Hourly' LIMIT 1), 'Full-Day Car with Driver (8 Hours)', 'full-day-car-with-driver-8-hours', 'A full working day with your own car and driver inside the valley.', 'Plan meetings, family visits or a shopping run without worrying about taxis or parking. The driver stays with you from morning to evening.', '8 hours / 80 km', 1, '', 5000, '{"Hatchback": 5000, "Sedan": 6000, "SUV": 8500, "Van": 10000}', '[{"day": 1, "title": "Full-day disposal", "text": "Eight hours of flexible use inside the valley, up to 80 km."}]', 'Driver
Fuel for 80 km
Tolls', 'Parking
Overtime
Night charge after 8 PM
Driver''s lunch', 'assets/images/placeholder-package.jpg', 50, 0
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tblpackages WHERE Slug = 'full-day-car-with-driver-8-hours');
INSERT INTO tblpackages (CategoryId, Title, Slug, Summary, Description, DurationLabel, DurationDays, RouteStops, StartingPrice, PricesJson, ItineraryJson, Inclusions, Exclusions, CoverImage, SortOrder, IsActive)
  SELECT (SELECT id FROM tblpackagecategories WHERE CategoryName = 'Tour' LIMIT 1), 'Kathmandu Valley Heritage Day', 'kathmandu-valley-heritage-day', 'Five UNESCO-listed landmarks of the valley in one relaxed, chauffeured day.', 'See the temples, stupas and royal squares that define Kathmandu, with a driver who knows the shortcuts and the best parking. You set the pace at each site.', '1 day (8 hours)', 1, '', 6500, '{"Sedan": 6500, "SUV": 9000, "Van": 11000}', '[{"day": 1, "title": "Valley heritage circuit", "text": "Pashupatinath, Boudhanath, Bhaktapur Durbar Square, Patan Durbar Square and Swayambhunath, returning to your hotel by evening."}]', 'Driver
Fuel
Tolls
Parking
Bottled water', 'Monument entry fees
Guide
Meals', 'assets/images/placeholder-package.jpg', 60, 0
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tblpackages WHERE Slug = 'kathmandu-valley-heritage-day');
INSERT INTO tblpackages (CategoryId, Title, Slug, Summary, Description, DurationLabel, DurationDays, RouteStops, StartingPrice, PricesJson, ItineraryJson, Inclusions, Exclusions, CoverImage, SortOrder, IsActive)
  SELECT (SELECT id FROM tblpackagecategories WHERE CategoryName = 'Tour' LIMIT 1), 'Nagarkot Sunrise Escape', 'nagarkot-sunrise-escape', 'An early start to watch the Himalaya light up from Nagarkot, then a stop at Changunarayan.', 'You''re picked up before dawn and reach the ridge in time for sunrise over the mountains. On the way back you stop at the valley''s oldest temple and are home before lunch.', 'Half day (about 6 hours)', 1, '', 5500, '{"Sedan": 5500, "SUV": 7500, "Van": 9000}', '[{"day": 1, "title": "Sunrise and Changunarayan", "text": "4:30 AM pickup, sunrise at Nagarkot viewpoint, breakfast stop, Changunarayan Temple, return to hotel."}]', 'Driver
Fuel
Tolls
Parking', 'Breakfast
Entry fees', 'assets/images/placeholder-package.jpg', 70, 0
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tblpackages WHERE Slug = 'nagarkot-sunrise-escape');
INSERT INTO tblpackages (CategoryId, Title, Slug, Summary, Description, DurationLabel, DurationDays, RouteStops, StartingPrice, PricesJson, ItineraryJson, Inclusions, Exclusions, CoverImage, SortOrder, IsActive)
  SELECT (SELECT id FROM tblpackagecategories WHERE CategoryName = 'Tour' LIMIT 1), 'Manakamana Darshan Day Trip', 'manakamana-darshan-day-trip', 'A same-day pilgrimage to the wish-fulfilling Manakamana temple by car and cable car.', 'Drive along the Trishuli river to Kurintar, ride the cable car up to the hilltop temple for darshan, and be back in Kathmandu by evening. It works well for families with elders.', '1 day', 1, '', 11000, '{"Sedan": 11000, "SUV": 15000, "Van": 18000}', '[{"day": 1, "title": "Kathmandu - Kurintar - Kathmandu", "text": "Morning drive to Kurintar, cable car to Manakamana temple, riverside lunch, return drive."}]', 'Driver
Fuel
Tolls
Parking
Driver''s meal', 'Cable car tickets
Puja items
Passenger meals', 'assets/images/placeholder-package.jpg', 80, 0
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tblpackages WHERE Slug = 'manakamana-darshan-day-trip');
INSERT INTO tblpackages (CategoryId, Title, Slug, Summary, Description, DurationLabel, DurationDays, RouteStops, StartingPrice, PricesJson, ItineraryJson, Inclusions, Exclusions, CoverImage, SortOrder, IsActive)
  SELECT (SELECT id FROM tblpackagecategories WHERE CategoryName = 'Tour' LIMIT 1), 'Kathmandu-Pokhara Lakeside Getaway', 'kathmandu-pokhara-lakeside-getaway', 'A round trip to Pokhara with a full sightseeing day by the lake.', 'Take the scenic Prithvi Highway with a tea stop in Bandipur. Spend a day on Sarangkot, the caves, the waterfalls and Phewa Lake, then drive home at your own pace.', '3 days / 2 nights', 3, 'Kathmandu to Pokhara – Pokhara sightseeing – Pokhara to Kathmandu', 32000, '{"Sedan": 32000, "SUV": 44000, "Van": 52000}', '[{"day": 1, "title": "Kathmandu to Pokhara", "text": "Morning departure, tea break at Bandipur viewpoint, afternoon arrival at Lakeside."}, {"day": 2, "title": "Pokhara sightseeing", "text": "Sarangkot sunrise, Davis Falls, Gupteshwor Cave, World Peace Pagoda and an evening by Phewa Lake."}, {"day": 3, "title": "Pokhara to Kathmandu", "text": "Return drive with lunch stop on the highway."}]', 'Driver
Fuel
Tolls
Parking
Driver''s food and accommodation', 'Hotels
Meals
Entry fees
Boating and paragliding', 'assets/images/placeholder-package.jpg', 90, 0
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tblpackages WHERE Slug = 'kathmandu-pokhara-lakeside-getaway');
INSERT INTO tblpackages (CategoryId, Title, Slug, Summary, Description, DurationLabel, DurationDays, RouteStops, StartingPrice, PricesJson, ItineraryJson, Inclusions, Exclusions, CoverImage, SortOrder, IsActive)
  SELECT (SELECT id FROM tblpackagecategories WHERE CategoryName = 'Tour' LIMIT 1), 'Chitwan Jungle Weekend', 'chitwan-jungle-weekend', 'A private-car weekend in Sauraha for safaris, Tharu culture and river sunsets.', 'Drive down to the edge of Chitwan National Park and keep the car on standby for local outings while you do your safari. You head back to Kathmandu on the third day.', '3 days / 2 nights', 3, 'Kathmandu to Sauraha – Chitwan day – Sauraha to Kathmandu', 28000, '{"Sedan": 28000, "SUV": 36000, "Van": 45000}', '[{"day": 1, "title": "Kathmandu to Sauraha", "text": "Drive via Mugling, check in, sunset by the Rapti river and a Tharu village visit."}, {"day": 2, "title": "Chitwan day", "text": "Car on standby for local trips while you join a jungle safari with a park operator."}, {"day": 3, "title": "Sauraha to Kathmandu", "text": "Return drive after breakfast."}]', 'Driver
Fuel
Tolls
Parking
Driver''s food and accommodation', 'National park entry fees
Safari activities
Hotels
Meals', 'assets/images/placeholder-package.jpg', 100, 0
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tblpackages WHERE Slug = 'chitwan-jungle-weekend');
INSERT INTO tblpackages (CategoryId, Title, Slug, Summary, Description, DurationLabel, DurationDays, RouteStops, StartingPrice, PricesJson, ItineraryJson, Inclusions, Exclusions, CoverImage, SortOrder, IsActive)
  SELECT (SELECT id FROM tblpackagecategories WHERE CategoryName = 'Tour' LIMIT 1), 'Bandipur Heritage Overnight', 'bandipur-heritage-overnight', 'A short break in the car-free Newari hill town of Bandipur.', 'Stroll the old bazaar and catch the Himalayan sunset from Tundikhel. The next morning, explore a cave or nearby village before driving home.', '2 days / 1 night', 2, 'Kathmandu to Bandipur – Bandipur to Kathmandu', 18000, '{"Sedan": 18000, "SUV": 24000, "Van": 28000}', '[{"day": 1, "title": "Kathmandu to Bandipur", "text": "Drive via Dumre, walk the bazaar, sunset at Tundikhel viewpoint."}, {"day": 2, "title": "Bandipur to Kathmandu", "text": "Morning visit to Siddha Cave or Ramkot village, then return drive."}]', 'Driver
Fuel
Tolls
Parking
Driver''s food and accommodation', 'Hotel
Meals
Cave entry fees', 'assets/images/placeholder-package.jpg', 110, 0
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tblpackages WHERE Slug = 'bandipur-heritage-overnight');
INSERT INTO tblpackages (CategoryId, Title, Slug, Summary, Description, DurationLabel, DurationDays, RouteStops, StartingPrice, PricesJson, ItineraryJson, Inclusions, Exclusions, CoverImage, SortOrder, IsActive)
  SELECT (SELECT id FROM tblpackagecategories WHERE CategoryName = 'Tour' LIMIT 1), 'Lumbini Peace Pilgrimage', 'lumbini-peace-pilgrimage', 'A private journey to the birthplace of the Buddha, with time for the monasteries and Tilaurakot.', 'Arrive in Lumbini in time for a quiet evening at the Maya Devi Temple. Spend a full day among the international monasteries, then visit the ancient palace ruins at Tilaurakot on the way home.', '3 days / 2 nights', 3, 'Kathmandu to Lumbini – Lumbini sightseeing – Tilaurakot and return', 34000, '{"Sedan": 34000, "SUV": 45000, "Van": 55000}', '[{"day": 1, "title": "Kathmandu to Lumbini", "text": "Long drive via Narayangarh and Butwal, evening at Maya Devi Temple and the Ashoka Pillar."}, {"day": 2, "title": "Lumbini sightseeing", "text": "Monastic Zone, World Peace Pagoda and Lumbini Museum."}, {"day": 3, "title": "Tilaurakot and return", "text": "Morning at Tilaurakot palace ruins, then drive back to Kathmandu."}]', 'Driver
Fuel
Tolls
Parking
Driver''s food and accommodation', 'Hotels
Meals
Entry fees
Guide', 'assets/images/placeholder-package.jpg', 120, 0
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tblpackages WHERE Slug = 'lumbini-peace-pilgrimage');
INSERT INTO tblpackages (CategoryId, Title, Slug, Summary, Description, DurationLabel, DurationDays, RouteStops, StartingPrice, PricesJson, ItineraryJson, Inclusions, Exclusions, CoverImage, SortOrder, IsActive)
  SELECT (SELECT id FROM tblpackagecategories WHERE CategoryName = 'Tour' LIMIT 1), 'Janakpur Dham Pilgrimage', 'janakpur-dham-pilgrimage', 'Visit Sita''s birthplace and the temples of Janakpur in comfort.', 'Take the BP Highway down to Mithila country for darshan at Janaki Mandir, Ram Mandir and the sacred ponds. The trip includes an evening at the aarati.', '3 days / 2 nights', 3, 'Kathmandu to Janakpur – Janakpur temples – Janakpur to Kathmandu', 30000, '{"Sedan": 30000, "SUV": 40000, "Van": 48000}', '[{"day": 1, "title": "Kathmandu to Janakpur", "text": "Drive via BP Highway through Sindhuli, evening arrival."}, {"day": 2, "title": "Janakpur temples", "text": "Janaki Mandir, Ram Mandir, Vivah Mandap, Dhanush Sagar and evening aarati."}, {"day": 3, "title": "Janakpur to Kathmandu", "text": "Return drive after morning puja."}]', 'Driver
Fuel
Tolls
Parking
Driver''s food and accommodation', 'Hotels
Meals
Temple donations', 'assets/images/placeholder-package.jpg', 130, 0
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tblpackages WHERE Slug = 'janakpur-dham-pilgrimage');
INSERT INTO tblpackages (CategoryId, Title, Slug, Summary, Description, DurationLabel, DurationDays, RouteStops, StartingPrice, PricesJson, ItineraryJson, Inclusions, Exclusions, CoverImage, SortOrder, IsActive)
  SELECT (SELECT id FROM tblpackagecategories WHERE CategoryName = 'Tour' LIMIT 1), 'Muktinath & Jomsom Jeep Yatra', 'muktinath-jomsom-jeep-yatra', 'A 4WD pilgrimage up the Kali Gandaki gorge to the Muktinath temple.', 'An experienced mountain driver takes you from Pokhara through Jomsom and Kagbeni for darshan at Muktinath. You stay overnight in apple-growing Marpha on the way down.', '3 days / 2 nights (from Pokhara)', 3, 'Pokhara to Jomsom – Muktinath darshan – Marpha to Pokhara', 40000, '{"SUV": 40000}', '[{"day": 1, "title": "Pokhara to Jomsom", "text": "Drive via Beni and Tatopani hot springs into Mustang, overnight in Jomsom."}, {"day": 2, "title": "Muktinath darshan", "text": "Kagbeni, Muktinath temple visit, descend to Marpha for the night."}, {"day": 3, "title": "Marpha to Pokhara", "text": "Return drive down the Kali Gandaki valley."}]', '4WD vehicle with mountain-route driver
Fuel
Parking
Driver''s food and accommodation', 'ACAP permit
Hotels
Meals
Kathmandu-Pokhara transfer', 'assets/images/placeholder-package.jpg', 140, 0
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tblpackages WHERE Slug = 'muktinath-jomsom-jeep-yatra');
INSERT INTO tblpackages (CategoryId, Title, Slug, Summary, Description, DurationLabel, DurationDays, RouteStops, StartingPrice, PricesJson, ItineraryJson, Inclusions, Exclusions, CoverImage, SortOrder, IsActive)
  SELECT (SELECT id FROM tblpackagecategories WHERE CategoryName = 'Event' LIMIT 1), 'Wedding & Event Car', 'wedding-event-car', 'A decorated car with a smart chauffeur for your wedding, janti or special event.', 'Arrive in style with floral decoration and a driver who keeps to your programme. Convoys and premium decoration are available on request.', '6 hours within the valley', 1, '', 12000, '{"Sedan": 12000, "SUV": 18000}', '[{"day": 1, "title": "Event day", "text": "Pickup from home, venue arrival, photo stops and return, up to 6 hours and 50 km."}]', 'Uniformed chauffeur
Fuel for 50 km
Basic floral decoration and ribbon', 'Premium decoration (from Rs 4,000 extra)
Overtime (Rs 1,500 per hour)
Outside-valley charge', 'assets/images/placeholder-package.jpg', 150, 0
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tblpackages WHERE Slug = 'wedding-event-car');
INSERT INTO tblpackages (CategoryId, Title, Slug, Summary, Description, DurationLabel, DurationDays, RouteStops, StartingPrice, PricesJson, ItineraryJson, Inclusions, Exclusions, CoverImage, SortOrder, IsActive)
  SELECT (SELECT id FROM tblpackagecategories WHERE CategoryName = 'Corporate' LIMIT 1), 'Corporate Monthly with Driver', 'corporate-monthly-with-driver', 'A dedicated car and driver for your office, billed monthly with a VAT invoice.', 'Cover staff commutes, client visits and airport runs with a single monthly contract. Maintenance, insurance and a replacement car are all handled by Rento.', '30 days (26 working days x 10 hours)', 30, 'Monthly contract', 75000, '{"Hatchback": 75000, "Sedan": 90000, "SUV": 130000, "Van": 140000}', '[{"day": 1, "title": "Monthly contract", "text": "Vehicle and driver assigned to your office for 26 working days, up to 10 hours per day inside the valley."}]', 'Dedicated driver
Maintenance
Insurance
Replacement vehicle
Monthly VAT invoice', 'Fuel (billed at actuals or per km)
Parking
Tolls
Driver overtime and overnight', 'assets/images/placeholder-package.jpg', 160, 0
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tblpackages WHERE Slug = 'corporate-monthly-with-driver');
