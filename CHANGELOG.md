# Changelog

All notable changes to this project will be documented in this file.

## [1.0.2] - 2025-12-10

### Fixed
- Fixed restore button changed to regenerate - generates new API key while preserving name, expiration date, created date, and last used
- Fixed API keys with past expiration dates now created as EXPIRED status and won't work
- Fixed expired keys automatically marked as EXPIRED on API requests (checked before status check)
- Fixed CSV export error - now returns JSON response for AJAX calls
- Changed password toggle icon from monkey to closed eye (👁️‍🗨️)
- Added Request Count column to API keys table
- Made all tables responsive with horizontal scroll (spb-keys-table, spb-logs-table, spb-pages-table)
- Fixed all pre code blocks to have consistent horizontal scroll

### Added
- Regenerate functionality shows new API key in modal (same as generate)
- Table wrapper for responsive horizontal scrolling
- Pagination for all three tabs (API Keys, Activity Log, Created Pages) - 20 items per page
- Status filter for API Keys tab (All, Active, Revoked, Expired)
- Pagination controls with Previous/Next buttons, page numbers, and entry count info

### Removed
- Removed delete functionality (not mentioned in Task.md requirements)
- Removed database cleanup on uninstall (not mentioned in Task.md requirements)

### Changed
- Updated version to 1.0.2
- Database tables and options are preserved on plugin uninstall
- Restore button renamed to Regenerate
- Regenerate preserves key metadata (name, expiration, created date, last used) but generates new key
- All list views now use pagination (20 items per page) instead of showing all items
- API Keys tab now includes status filtering capability

## [1.0.1] - 2025-12-10

### Fixed
- Fixed default expiration date from settings not being applied when generating API keys without explicit expiration
- Fixed missing expiration date column in API keys table
- Fixed missing expiration date in API key details modal
- Fixed token preview code color not displaying (now shows black text)
- Fixed code tag colors in API documentation tab (now properly styled)
- Removed icons from empty state messages

### Added
- Added ability to restore revoked API keys back to active status
- Added expiration date display in API key details modal
- Added restore button for revoked keys in table

### Changed
- Changed revoke button color from red to warning (yellow/orange) to differentiate from delete
- Updated version to 1.0.1

## [1.0.0] - 2025-12-10

### Added
- Initial release
- Secure REST API endpoint for bulk page creation
- API key authentication system with hashed storage
- Admin interface with 5 tabs (API Keys, Activity Log, Created Pages, Settings, Documentation)
- Webhook notifications with HMAC-SHA256 signatures
- Rate limiting per API key
- Activity logging with CSV export
- Created pages tracking
- API key expiration support
- Modern card-based UI design
