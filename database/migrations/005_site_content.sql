-- Site content edited in admin -> Site content (additive only).
-- Only changed items are stored; includes/site-content.php holds the defaults.
CREATE TABLE IF NOT EXISTS `tblsitecontent` (
  `ContentKey` varchar(80) NOT NULL,
  `ContentValue` mediumtext NOT NULL,
  `UpdatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ContentKey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optional brand logo (file in assets/images/content/), shown on the home page brand cards.
ALTER TABLE `tblbrands` ADD COLUMN `BrandLogo` varchar(160) DEFAULT NULL AFTER `BrandName`;
