# APPENDIX D: DATA DICTIONARY

This appendix lists all 23 tables of the rento database with their 200 columns, as exported from the live schema. Key: PRI primary key, UNI unique, MUL indexed. The database has no foreign key constraints; the logical relationships are shown in Figures 4.7 and 4.8. Tables marked "template" come from the original template (some with added columns); the others were added by the migrations of Table 5.2.

Table: Table D.1 admin (template, 5 columns) {widths=1.6,1.35,0.55,0.55,1.7}
| Column | Type | Null | Key | Default / extra |
|---|---|---|---|---|
| id | int | No | PRI | auto_increment |
| UserName | varchar(100) | No |  |  |
| Email | varchar(150) | Yes |  |  |
| Password | varchar(100) | No |  |  |
| updationDate | timestamp | No |  | 0000-00-00 00:00:00 on update CURRENT_TIMESTAMP |

Table: Table D.2 tbl_forecast_runs (added, 9 columns) {widths=1.6,1.35,0.55,0.55,1.7}
| Column | Type | Null | Key | Default / extra |
|---|---|---|---|---|
| id | int | No | PRI | auto_increment |
| TriggerReason | varchar(120) | No |  |  |
| Status | varchar(12) | No | MUL | queued |
| RequestedAt | timestamp | No |  | CURRENT_TIMESTAMP |
| StartedAt | datetime | Yes |  |  |
| FinishedAt | datetime | Yes |  |  |
| Cars | int | Yes |  |  |
| RowsWritten | int | Yes |  |  |
| Message | varchar(500) | Yes |  |  |

Table: Table D.3 tbl_price_forecast (added, 9 columns) {widths=1.6,1.35,0.55,0.55,1.7}
| Column | Type | Null | Key | Default / extra |
|---|---|---|---|---|
| VehicleId | int | No | PRI |  |
| ForecastDate | date | No | PRI |  |
| BasePrice | int | No |  |  |
| Multiplier | decimal(6,4) | No |  | 1.0000 |
| Price | int | No |  |  |
| Reasons | text | Yes |  |  |
| IsAvailable | tinyint(1) | No |  | 1 |
| RunId | int | Yes |  |  |
| ComputedAt | timestamp | No |  | CURRENT_TIMESTAMP |

Table: Table D.4 tbl_price_overrides (added, 8 columns) {widths=1.6,1.35,0.55,0.55,1.7}
| Column | Type | Null | Key | Default / extra |
|---|---|---|---|---|
| id | int | No | PRI | auto_increment |
| VehicleId | int | No | MUL |  |
| FromDate | date | No |  |  |
| ToDate | date | No |  |  |
| Price | int | No |  |  |
| Note | varchar(200) | Yes |  |  |
| CreatedBy | varchar(100) | Yes |  |  |
| CreatedAt | timestamp | No |  | CURRENT_TIMESTAMP |

Table: Table D.5 tblbooking (template, 13 columns) {widths=1.6,1.35,0.55,0.55,1.7}
| Column | Type | Null | Key | Default / extra |
|---|---|---|---|---|
| id | int | No | PRI | auto_increment |
| BookingNumber | bigint | Yes |  |  |
| userEmail | varchar(100) | Yes |  |  |
| VehicleId | int | Yes |  |  |
| FromDate | varchar(20) | Yes |  |  |
| ToDate | varchar(20) | Yes |  |  |
| message | varchar(255) | Yes |  |  |
| Status | int | Yes |  |  |
| quoted_price | int | Yes |  |  |
| pricing_details | text | Yes |  |  |
| priced_at | datetime | Yes |  |  |
| PostingDate | timestamp | No |  | CURRENT_TIMESTAMP |
| LastUpdationDate | timestamp | Yes |  | on update CURRENT_TIMESTAMP |

Table: Table D.6 tblbrands (template, 5 columns) {widths=1.6,1.35,0.55,0.55,1.7}
| Column | Type | Null | Key | Default / extra |
|---|---|---|---|---|
| id | int | No | PRI | auto_increment |
| BrandName | varchar(120) | No |  |  |
| BrandLogo | varchar(160) | Yes |  |  |
| CreationDate | timestamp | Yes |  | CURRENT_TIMESTAMP |
| UpdationDate | timestamp | Yes |  | on update CURRENT_TIMESTAMP |

Table: Table D.7 tblcontactusinfo (template, 4 columns) {widths=1.6,1.35,0.55,0.55,1.7}
| Column | Type | Null | Key | Default / extra |
|---|---|---|---|---|
| id | int | No | PRI | auto_increment |
| Address | tinytext | Yes |  |  |
| EmailId | varchar(255) | Yes |  |  |
| ContactNo | char(11) | Yes |  |  |

Table: Table D.8 tblcontactusquery (template, 7 columns) {widths=1.6,1.35,0.55,0.55,1.7}
| Column | Type | Null | Key | Default / extra |
|---|---|---|---|---|
| id | int | No | PRI | auto_increment |
| name | varchar(100) | Yes |  |  |
| EmailId | varchar(120) | Yes |  |  |
| ContactNumber | char(11) | Yes |  |  |
| Message | longtext | Yes |  |  |
| PostingDate | timestamp | No |  | CURRENT_TIMESTAMP |
| status | int | Yes |  |  |

Table: Table D.9 tblfestivals (added, 13 columns) {widths=1.6,1.35,0.55,0.55,1.7}
| Column | Type | Null | Key | Default / extra |
|---|---|---|---|---|
| id | int | No | PRI | auto_increment |
| FestivalName | varchar(150) | No |  |  |
| StartDate | date | No | MUL |  |
| EndDate | date | No |  |  |
| TravelFrom | date | Yes |  |  |
| TravelTo | date | Yes |  |  |
| TravelPct | decimal(5,2) | Yes |  |  |
| DemandLevel | varchar(10) | No |  | medium |
| Verification | varchar(20) | No |  | unverified |
| SourceUrl | varchar(500) | Yes |  |  |
| Notes | text | Yes |  |  |
| CreatedAt | timestamp | No |  | CURRENT_TIMESTAMP |
| UpdatedAt | timestamp | Yes |  | on update CURRENT_TIMESTAMP |

Table: Table D.10 tblmailsettings (added, 3 columns) {widths=1.6,1.35,0.55,0.55,1.7}
| Column | Type | Null | Key | Default / extra |
|---|---|---|---|---|
| SettingKey | varchar(40) | No | PRI |  |
| SettingValue | text | No |  |  |
| UpdatedAt | timestamp | No |  | CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP |

Table: Table D.11 tblpackagecategories (added, 7 columns) {widths=1.6,1.35,0.55,0.55,1.7}
| Column | Type | Null | Key | Default / extra |
|---|---|---|---|---|
| id | int | No | PRI | auto_increment |
| CategoryName | varchar(80) | No |  |  |
| Slug | varchar(80) | No | UNI |  |
| Icon | varchar(40) | No |  | fa-route |
| SortOrder | int | No |  | 0 |
| IsActive | tinyint(1) | No |  | 1 |
| CreatedAt | timestamp | No |  | CURRENT_TIMESTAMP |

Table: Table D.12 tblpackageenquiries (added, 16 columns) {widths=1.6,1.35,0.55,0.55,1.7}
| Column | Type | Null | Key | Default / extra |
|---|---|---|---|---|
| id | int | No | PRI | auto_increment |
| PackageId | int | Yes | MUL |  |
| VehicleId | int | Yes | MUL |  |
| PackageTitle | varchar(150) | No |  |  |
| FullName | varchar(120) | No |  |  |
| Phone | varchar(20) | No |  |  |
| Email | varchar(120) | Yes |  |  |
| TravelDate | date | No |  |  |
| PickupLocation | varchar(200) | No |  |  |
| Passengers | int | No |  | 1 |
| VehicleType | varchar(30) | Yes |  |  |
| Notes | text | Yes |  |  |
| Status | varchar(12) | No | MUL | new |
| AdminNotes | text | Yes |  |  |
| CreatedAt | timestamp | No |  | CURRENT_TIMESTAMP |
| UpdatedAt | timestamp | Yes |  | on update CURRENT_TIMESTAMP |

Table: Table D.13 tblpackageimages (added, 4 columns) {widths=1.6,1.35,0.55,0.55,1.7}
| Column | Type | Null | Key | Default / extra |
|---|---|---|---|---|
| id | int | No | PRI | auto_increment |
| PackageId | int | No | MUL |  |
| ImagePath | varchar(160) | No |  |  |
| SortOrder | int | No |  | 0 |

Table: Table D.14 tblpackages (added, 21 columns) {widths=1.6,1.35,0.55,0.55,1.7}
| Column | Type | Null | Key | Default / extra |
|---|---|---|---|---|
| id | int | No | PRI | auto_increment |
| CategoryId | int | Yes | MUL |  |
| Title | varchar(150) | No |  |  |
| Slug | varchar(160) | No |  |  |
| Summary | varchar(300) | Yes |  |  |
| Description | text | Yes |  |  |
| SeoDescription | varchar(300) | Yes |  |  |
| DurationLabel | varchar(80) | Yes |  |  |
| DurationDays | int | No |  | 1 |
| RouteStops | varchar(300) | Yes |  |  |
| StartingPrice | int | No |  | 0 |
| FestivalPricing | tinyint(1) | No |  | 0 |
| PricesJson | text | Yes |  |  |
| ItineraryJson | text | Yes |  |  |
| Inclusions | text | Yes |  |  |
| Exclusions | text | Yes |  |  |
| CoverImage | varchar(160) | Yes |  |  |
| SortOrder | int | No |  | 0 |
| IsActive | tinyint(1) | No | MUL | 0 |
| CreatedAt | timestamp | No |  | CURRENT_TIMESTAMP |
| UpdatedAt | timestamp | Yes |  | on update CURRENT_TIMESTAMP |

Table: Table D.15 tblpages (template, 7 columns) {widths=1.6,1.35,0.55,0.55,1.7}
| Column | Type | Null | Key | Default / extra |
|---|---|---|---|---|
| id | int | No | PRI | auto_increment |
| PageName | varchar(255) | Yes |  |  |
| type | varchar(255) | No |  |  |
| detail | longtext | No |  |  |
| DraftDetail | longtext | Yes |  |  |
| DraftNote | varchar(500) | Yes |  |  |
| DraftUpdatedAt | datetime | Yes |  |  |

Table: Table D.16 tblpasswordresets (added, 7 columns) {widths=1.6,1.35,0.55,0.55,1.7}
| Column | Type | Null | Key | Default / extra |
|---|---|---|---|---|
| id | int | No | PRI | auto_increment |
| UserId | int | No | MUL |  |
| AccountType | varchar(10) | No | MUL | user |
| TokenHash | char(64) | No | UNI |  |
| ExpiresAt | datetime | No |  |  |
| UsedAt | datetime | Yes |  |  |
| CreatedAt | timestamp | No |  | CURRENT_TIMESTAMP |

Table: Table D.17 tblratelimits (added, 5 columns) {widths=1.6,1.35,0.55,0.55,1.7}
| Column | Type | Null | Key | Default / extra |
|---|---|---|---|---|
| id | int | No | PRI | auto_increment |
| Action | varchar(30) | No | MUL |  |
| IpAddress | varchar(45) | No |  |  |
| Email | varchar(191) | Yes |  |  |
| CreatedAt | datetime | No |  | CURRENT_TIMESTAMP |

Table: Table D.18 tblsettings (added, 3 columns) {widths=1.6,1.35,0.55,0.55,1.7}
| Column | Type | Null | Key | Default / extra |
|---|---|---|---|---|
| SettingKey | varchar(60) | No | PRI |  |
| SettingValue | varchar(255) | No |  |  |
| UpdatedAt | timestamp | No |  | CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP |

Table: Table D.19 tblsitecontent (added, 3 columns) {widths=1.6,1.35,0.55,0.55,1.7}
| Column | Type | Null | Key | Default / extra |
|---|---|---|---|---|
| ContentKey | varchar(80) | No | PRI |  |
| ContentValue | mediumtext | No |  |  |
| UpdatedAt | timestamp | No |  | CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP |

Table: Table D.20 tblsubscribers (template, 3 columns) {widths=1.6,1.35,0.55,0.55,1.7}
| Column | Type | Null | Key | Default / extra |
|---|---|---|---|---|
| id | int | No | PRI | auto_increment |
| SubscriberEmail | varchar(120) | Yes |  |  |
| PostingDate | timestamp | Yes |  | CURRENT_TIMESTAMP |

Table: Table D.21 tbltestimonial (template, 5 columns) {widths=1.6,1.35,0.55,0.55,1.7}
| Column | Type | Null | Key | Default / extra |
|---|---|---|---|---|
| id | int | No | PRI | auto_increment |
| UserEmail | varchar(100) | No |  |  |
| Testimonial | mediumtext | No |  |  |
| PostingDate | timestamp | No |  | CURRENT_TIMESTAMP |
| status | int | Yes |  |  |

Table: Table D.22 tblusers (template, 11 columns) {widths=1.6,1.35,0.55,0.55,1.7}
| Column | Type | Null | Key | Default / extra |
|---|---|---|---|---|
| id | int | No | PRI | auto_increment |
| FullName | varchar(120) | Yes |  |  |
| EmailId | varchar(100) | Yes | MUL |  |
| Password | varchar(100) | Yes |  |  |
| ContactNo | char(11) | Yes |  |  |
| dob | varchar(100) | Yes |  |  |
| Address | varchar(255) | Yes |  |  |
| City | varchar(100) | Yes |  |  |
| Country | varchar(100) | Yes |  |  |
| RegDate | timestamp | Yes |  | CURRENT_TIMESTAMP |
| UpdationDate | timestamp | Yes |  | on update CURRENT_TIMESTAMP |

Table: Table D.23 tblvehicles (template, 32 columns) {widths=1.6,1.35,0.55,0.55,1.7}
| Column | Type | Null | Key | Default / extra |
|---|---|---|---|---|
| id | int | No | PRI | auto_increment |
| VehiclesTitle | varchar(150) | Yes |  |  |
| VehiclesBrand | int | Yes |  |  |
| VehiclesOverview | longtext | Yes |  |  |
| SeoDescription | varchar(300) | Yes |  |  |
| PricePerDay | int | Yes | MUL |  |
| FuelType | varchar(100) | Yes |  |  |
| ModelYear | int | Yes |  |  |
| SeatingCapacity | int | Yes |  |  |
| CarType | varchar(20) | Yes | MUL | Sedan |
| IsActive | tinyint(1) | No | MUL | 1 |
| DriverOnly | tinyint(1) | No |  | 0 |
| Rugged | tinyint(1) | No |  | 0 |
| Vimage1 | varchar(120) | Yes |  |  |
| Vimage2 | varchar(120) | Yes |  |  |
| Vimage3 | varchar(120) | Yes |  |  |
| Vimage4 | varchar(120) | Yes |  |  |
| Vimage5 | varchar(120) | Yes |  |  |
| AirConditioner | int | Yes |  |  |
| PowerDoorLocks | int | Yes |  |  |
| AntiLockBrakingSystem | int | Yes |  |  |
| BrakeAssist | int | Yes |  |  |
| PowerSteering | int | Yes |  |  |
| DriverAirbag | int | Yes |  |  |
| PassengerAirbag | int | Yes |  |  |
| PowerWindows | int | Yes |  |  |
| CDPlayer | int | Yes |  |  |
| CentralLocking | int | Yes |  |  |
| CrashSensor | int | Yes |  |  |
| LeatherSeats | int | Yes |  |  |
| RegDate | timestamp | No |  | CURRENT_TIMESTAMP |
| UpdationDate | timestamp | Yes |  | on update CURRENT_TIMESTAMP |

