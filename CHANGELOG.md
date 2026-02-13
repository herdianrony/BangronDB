# Changelog

Semua perubahan notable pada project ini akan dicatat di file ini.

Format changelog ini mengikuti [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), dan project ini mengikuti [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2024-01-15

### Added

#### Core Features

- **MongoDB-like API** - Familiar query syntax untuk PHP developers
- **SQLite Backend** - Lightweight, ACID-compliant, zero-configuration database
- **NoSQL Document Storage** - Flexible JSON-based schema
- **Multiple ID Generation Modes**
  - Auto UUID v4 (default)
  - Manual ID assignment
  - Prefix-based auto-increment (e.g., USR-000001)

#### Security Features

- **AES-256 Encryption** - Field-level dan collection-level encryption
- **Searchable Encrypted Fields** - Query encrypted data dengan hashing
- **Encryption Key Validation** - Minimum 32 characters, entropy checks
- **Configuration Value Validation** - Page size, cache size, journal mode validation

#### Query Features

- **Comparison Operators** - $gt, $gte, $lt, $lte, $ne, $exists
- **Array Operators** - $in, $nin
- **Logical Operators** - $or, $and
- **String Operators** - $regex pattern matching
- **Custom Functions** - PHP callbacks untuk complex queries
- **Fuzzy Search** - Levenshtein distance dengan configurable threshold
- **Dot Notation** - Query nested fields dengan path notation

#### Data Management

- **CRUD Operations** - Insert, find, update, remove
- **Batch Operations** - Insert/update multiple documents
- **Soft Deletes** - Logical deletion dengan restore capability
- **Pagination & Sorting** - Skip, limit, dan sort operations
- **Upsert** - Save method untuk insert atau update
- **MongoDB-style Updates** - $set dan $unset operators

#### Advanced Features

- **Hooks System** - Event-driven hooks untuk semua operations
  - beforeInsert, afterInsert
  - beforeUpdate, afterUpdate
  - beforeRemove, afterRemove
- **Relationships / Populate** - Join-like functionality antar collections
  - Single field population
  - Array element population
  - Cross-database relationships
  - Nested population
- **Indexing** - JSON field indexing dengan json_extract
- **Schema Validation** - Type checking, enums, regex, range validation
- **Transactions** - Manual transaction support dengan commit/rollback

#### Monitoring & Maintenance

- **Health Metrics** - Database integrity, performance metrics
- **Health Report** - Status, warnings, recommendations
- **Performance Metrics** - Page count, fragmentation ratio, index info
- **Collection Metrics** - Document count, size, avg document size
- **Change Notification** - Track collection version dan last modified timestamp
- **VACUUM Command** - Database optimization dan space reclamation

#### Configuration

- **Dynamic Configuration** - Save/load collection configuration per collections
- **Custom Configuration** - Store custom key-value pairs per collection
- **WAL Mode** - Write-Ahead Logging untuk better concurrency
- **Configurable Journal & Sync** - Fine-tuned database behavior

#### Utilities

- **Array Query Utilities** - Document matching, UUID generation, fuzzy search
- **Criteria Functions** - Register dan reuse complex query criteria
- **Database Metrics** - Comprehensive metrics API
- **Query Executor** - Prepared statements, logging, monitoring

### Documentation

- Comprehensive README dengan 1500+ lines
- 11+ detailed markdown guides di docs/ folder
- 20+ working examples covering various use cases
- Full API reference documentation
- Security best practices guide
- Troubleshooting & deployment guides

### Testing

- 237 unit tests dengan 754 assertions
- 100% test pass rate
- Comprehensive test coverage untuk semua features
- PHPUnit 9.6+ integration

### Infrastructure

- PSR-4 autoloading
- PSR-12 code style compliance
- Modular architecture dengan 7 traits
- Proper exception handling dengan custom exceptions
- Comprehensive error messages

## Future Roadmap

### v1.1.0 (Planned)

- [ ] JSON Schema validation
- [ ] Full-text search improvements
- [ ] Batch transaction API
- [ ] Performance optimizations

### v1.2.0 (Planned)

- [ ] Read replicas support
- [ ] Query caching layer
- [ ] Collection sharding
- [ ] Advanced indexing strategies

### v2.0.0 (Future)

- [ ] PostgreSQL/MySQL backend support
- [ ] GraphQL query interface
- [ ] Real-time change streams
- [ ] Multi-node clustering

## Notes untuk Contributors

Lihat [CONTRIBUTING.md](CONTRIBUTING.md) untuk guidelines tentang cara berkontribusi.

---

**Changelog dimulai dari v1.0.0. Untuk info tentang pengembangan, lihat Git history.**

[1.0.0]: https://github.com/herdianrony/BangronDB/releases/tag/v1.0.0
