-- App settings (additive), edited in admin -> Pricing. Only changed values are stored;
-- the defaults are in includes/settings.php (max discount 15%, max surcharge 25%, ...).
CREATE TABLE IF NOT EXISTS `tblsettings` (
  `SettingKey` varchar(60) NOT NULL,
  `SettingValue` varchar(255) NOT NULL,
  `UpdatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`SettingKey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
