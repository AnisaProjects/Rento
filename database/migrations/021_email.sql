-- Email (Phase 6, additive): an address for the admin account (password reset links) and the optional
-- SMTP settings edited in admin -> Email. The SMTP password is stored encrypted (includes/mailer.php)
-- with the APP_KEY from includes/config.local.php / RENTO_APP_KEY, never in plain text.
-- The legacy admin.updationDate column has a zero-date default that strict mode rejects on any ALTER;
-- allow it for this session only (the column itself is left as it is).
SET SESSION sql_mode = REPLACE(REPLACE(@@SESSION.sql_mode, 'NO_ZERO_DATE', ''), 'NO_ZERO_IN_DATE', '');
ALTER TABLE `admin` ADD COLUMN `Email` varchar(150) NULL DEFAULT NULL AFTER `UserName`;
CREATE TABLE IF NOT EXISTS `tblmailsettings` (
  `SettingKey` varchar(40) NOT NULL,
  `SettingValue` text NOT NULL,
  `UpdatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`SettingKey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
