# BangronDB Changelog

All notable changes to BangronDB are documented here.

## [Unreleased]

### Security

- **BREAKING**: Encryption keys now require minimum 32 characters for AES-256 security
- Added weak encryption key pattern detection (repeated chars, sequential patterns, low entropy)
- Enhanced config value validation to prevent invalid configurations at set time
- Implemented automatic weak reference cleanup to prevent memory leaks in long-running apps

### Added

- `CollectionManager` class for managing collection metadata and configuration caching
- `DatabaseMetrics` class for health monitoring and performance metrics
- `Factory` class for centralized object creation with validation
- `Config` class for static configuration management with comprehensive value validation
- `QueryExecutor` class with prepared statements, logging, and performance monitoring
- Searchable fields with selective hashing support
- Case-insensitive search support
- Composite searchable fields
- Memory safety enforcement with configurable limits (default 10,000 docs)
- Periodic cleanup mechanism for criteria registry (every 5 minutes or 1,000 entries)

### Changed

- Renamed system tables for consistency:
  - `_collection_metadata` → `_meta`
  - `_collections` → `_config` (now in JSON format)
- Improved type safety with strict comparisons (`===` instead of `==`)
- Enhanced security by escaping fields in ORDER BY clauses
- Added sensitive data filtering in query logs
- Deprecated `executeRaw()` and `executeRawUpdate()` methods

### Fixed

- Division by zero in `fuzzy_search()` when no search terms provided
- SQL injection vulnerability in ORDER BY clause
- Sensitive data exposure in query logs
- Type coercion issues with loose comparisons

### Security

- Added SQL injection protection for ORDER BY fields
- Sensitive parameter filtering in query logs
- Proper escaping in all dynamic SQL queries

## [1.0.0] - 2024-01-01

### Added

- Initial release of BangronDB
- Document database with JSON storage
- MongoDB-like query operators
- Schema validation
- Encryption support (AES-256-CBC)
- Hooks system (before/after operations)
- Soft deletes with restore capability
- Population for document relationships
- Indexing support
- ID generation (UUID v4, manual, prefix)
- Cross-database relations
- WAL mode for concurrency
- Health monitoring

[Unreleased]: https://github.com/bangrondb/bangrondb/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/bangrondb/bangrondb/releases/tag/v1.0.0
