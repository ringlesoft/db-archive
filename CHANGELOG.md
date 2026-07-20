# Changelog

All notable changes to this project are documented in this file.

## Version 0.0.3

### Added
- Support for Laravel 13
- Clearer archive summaries so applications can track how many records were processed and moved.
- Optional soft-delete support for teams that need to retain source records after archiving.
- A preview mode for setup, allowing teams to review planned database changes before applying them.

### Changed
- Archived model queries now work correctly when archive table names use a prefix.
- Replacing an existing archive table now asks for confirmation during interactive setup to help prevent accidental data removal.

### Fixed
- Archive activity is now recorded at the intended log level, making operational logs easier to review.
