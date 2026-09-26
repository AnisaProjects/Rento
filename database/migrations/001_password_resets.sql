-- Phase 0: token-based password reset (additive only).
-- Tokens are stored as SHA-256 hashes; the plain token only ever appears in the reset link.
CREATE TABLE IF NOT EXISTS `tblpasswordresets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `UserId` int NOT NULL,
  `TokenHash` char(64) NOT NULL,
  `ExpiresAt` datetime NOT NULL,
  `UsedAt` datetime DEFAULT NULL,
  `CreatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `TokenHash` (`TokenHash`),
  KEY `UserId` (`UserId`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
