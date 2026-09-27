-- Phase 3: admin password reset reuses tblpasswordresets (additive only).
-- AccountType says which table UserId points to: 'user' = tblusers.id, 'admin' = admin.id.
-- Existing rows are customer resets, so the default is 'user'.
ALTER TABLE `tblpasswordresets`
  ADD COLUMN `AccountType` varchar(10) NOT NULL DEFAULT 'user' AFTER `UserId`,
  ADD KEY `AccountUser` (`AccountType`, `UserId`);
