# User Manual - BangronDB Admin Panel

Manual penggunaan lengkap BangronDB Admin Panel. Panduan ini mencakup semua fitur, workflows, dan best practices untuk penggunaan sehari-hari.

## 📖 Table of Contents

1. [Dashboard Overview](#dashboard-overview)
2. [Database Management](#database-management)
3. [Collection Management](#collection-management)
4. [Document Management](#document-management)
5. [User Management](#user-management)
6. [Security Features](#security-features)
7. [Monitoring & Analytics](#monitoring--analytics)
8. [Advanced Features](#advanced-features)
9. [Keyboard Shortcuts](#keyboard-shortcuts)
10. [Best Practices](#best-practices)

## 🎯 Dashboard Overview

### Main Dashboard

Dashboard adalah halaman utama yang memberikan overview sistem secara real-time.

#### Key Components

| Component              | Description             | Features                        |
| ---------------------- | ----------------------- | ------------------------------- |
| **System Health**      | Status kesehatan sistem | CPU, memory, disk usage         |
| **Database Metrics**   | Penggunaan database     | Storage, collections, documents |
| **Recent Activity**    | Aktivitas terbaru       | User actions, system events     |
| **Performance Charts** | Visualisasi performa    | Query times, response rates     |
| **Quick Actions**      | Akses cepat             | Common tasks shortcuts          |

#### Dashboard Features

```php
// Access dashboard data
$dashboard = $client->getDashboardMetrics();

// Metrics available
$metrics = [
    'system_health' => ['cpu', 'memory', 'disk'],
    'database_stats' => ['databases', 'collections', 'documents'],
    'performance' => ['queries_per_second', 'avg_response_time'],
    'activity' => ['recent_users', 'active_sessions']
];
```

### Dashboard Customization

#### Layout Options

- **Default**: Full layout with sidebar
- **Compact**: Minimal layout
- **Focus**: Maximize content area
- **Custom**: User-defined layout

#### Widget Management

```javascript
// Add widget
dashboard.addWidget("performance-chart", {
  title: "Performance Overview",
  type: "line-chart",
  data: "performance-metrics",
});

// Remove widget
dashboard.removeWidget("user-activity");

// Configure widget
dashboard.configureWidget("storage-chart", {
  refreshInterval: 30000,
  chartType: "doughnut",
});
```

## 🗄️ Database Management

### Database Operations

#### Create Database

```php
// Method 1: Via API
$client->createDatabase('myapp', [
    'path' => '/data/myapp',
    'encryption' => true,
    'backup_enabled' => true
]);

// Method 2: Via Admin Panel
// Databases → Create Database → Fill form
```

#### Database Properties

| Property         | Type     | Description        |
| ---------------- | -------- | ------------------ |
| `name`           | string   | Database name      |
| `path`           | string   | File system path   |
| `encryption`     | boolean  | Enable encryption  |
| `compression`    | boolean  | Enable compression |
| `backup_enabled` | boolean  | Auto backup        |
| `max_size`       | integer  | Max storage size   |
| `created_at`     | datetime | Creation timestamp |

#### Database Operations

```php
// List all databases
$databases = $client->listDatabases();

// Select database
$db = $client->selectDatabase('myapp');

// Get database info
$info = $db->getInfo();

// Delete database
$client->dropDatabase('myapp');
```

### Database Import/Export

#### Export Database

```bash
# Export to JSON
php artisan db:export myapp --format=json --path=./exports/

# Export with metadata
php artisan db:export myapp --include-meta --encryption-key=your-key
```

#### Import Database

```bash
# Import from JSON
php artisan db:import myapp ./exports/myapp.json

# Import with validation
php artisan db:import myapp ./exports/myapp.json --validate
```

#### Import/Export Formats

| Format     | Description             | Features                         |
| ---------- | ----------------------- | -------------------------------- |
| **JSON**   | Standard JSON format    | Human-readable, metadata support |
| **Binary** | Optimized binary format | Fast, compact, encrypted         |
| **SQL**    | SQLite dump format      | Migration support, compatibility |

### Database Backup

#### Manual Backup

```php
// Create backup
$backup = $db->createBackup([
    'filename' => 'backup_' . date('Y-m-d_H-i-s'),
    'compression' => true,
    'encryption' => true
]);

// List backups
$backups = $db->listBackups();

// Restore backup
$db->restoreBackup('backup_2024-01-15_10-30-00');
```

#### Automated Backup

```php
// Configure automated backup
$db->configureBackup([
    'schedule' => 'daily', // daily, weekly, monthly
    'retention' => 30, // days to keep
    'compression' => true,
    'encryption' => true,
    'notifications' => true
]);
```

## 📊 Collection Management

### Collection Operations

#### Create Collection

```php
// Method 1: Via API
$collection = $db->createCollection('users', [
    'schema' => [
        'name' => ['required' => true, 'type' => 'string'],
        'email' => ['required' => true, 'type' => 'email'],
        'age' => ['type' => 'integer', 'min' => 0]
    ],
    'encryption' => true,
    'indexes' => ['email']
]);

// Method 2: Via Admin Panel
// Collections → Create Collection → Configure schema
```

#### Collection Properties

| Property      | Type     | Description        |
| ------------- | -------- | ------------------ |
| `name`        | string   | Collection name    |
| `schema`      | array    | Validation rules   |
| `encryption`  | boolean  | Enable encryption  |
| `indexes`     | array    | Field indexes      |
| `soft_delete` | boolean  | Enable soft delete |
| `created_at`  | datetime | Creation timestamp |

#### Schema Management

```php
// Define schema
$schema = [
    'name' => [
        'required' => true,
        'type' => 'string',
        'min' => 2,
        'max' => 100
    ],
    'email' => [
        'required' => true,
        'type' => 'email',
        'unique' => true
    ],
    'age' => [
        'type' => 'integer',
        'min' => 0,
        'max' => 150
    ],
    'status' => [
        'type' => 'enum',
        'values' => ['active', 'inactive', 'pending']
    ]
];

// Apply schema
$collection->setSchema($schema);

// Validate document
$isValid = $collection->validate($document);
```

### Index Management

#### Create Index

```php
// Single field index
$collection->createIndex('email');

// Compound index
$collection->createIndex(['name' => 1, 'age' => -1]);

// Text index
$collection->createIndex(['name' => 'text', 'description' => 'text']);
```

#### Index Types

| Type             | Description              | Use Case                      |
| ---------------- | ------------------------ | ----------------------------- |
| **Single Field** | Index on one field       | Simple queries, unique fields |
| **Compound**     | Index on multiple fields | Complex queries, sorting      |
| **Text**         | Full-text search         | Content search                |
| **Geospatial**   | Location-based queries   | Map applications              |

### Collection Operations

```php
// List collections
$collections = $db->listCollections();

// Get collection info
$info = $collection->getInfo();

// Rename collection
$collection->rename('user_profiles');

// Drop collection
$collection->drop();
```

## 📝 Document Management

### Document Operations

#### Insert Documents

```php
// Single document
$document = [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 30,
    'status' => 'active'
];

$id = $collection->insert($document);

// Multiple documents
$documents = [
    ['name' => 'Jane Smith', 'email' => 'jane@example.com', 'age' => 25],
    ['name' => 'Bob Johnson', 'email' => 'bob@example.com', 'age': 35]
];

$ids = $collection->insert($documents);
```

#### Query Documents

```php
// Find all documents
$documents = $collection->find()->toArray();

// Find with criteria
$user = $collection->findOne(['email' => 'john@example.com']);

// Complex query
$users = $collection->find([
    'age' => ['$gt' => 25, '$lt' => 35],
    'status' => 'active',
    '$or' => [
        ['name' => ['$regex' => 'John']],
        ['email' => ['$regex' => '@gmail\.com$']]
    ]
])->sort(['name' => 1])->limit(10);
```

#### Update Documents

```php
// Update single document
$result = $collection->update(
    ['_id' => $id],
    ['$set' => ['age' => 31, 'updated_at' => date('c')]]
);

// Update multiple documents
$result = $collection->update(
    ['status' => 'active'],
    ['$set' => ['last_login' => date('c')]]
);

// Upsert (insert if not exists)
$result = $collection->update(
    ['email' => 'new@example.com'],
    ['$set' => ['name' => 'New User', 'status' => 'active']],
    ['upsert' => true]
);
```

#### Delete Documents

```php
// Soft delete (if enabled)
$result = $collection->remove(['status' => 'inactive']);

// Force delete
$result = $collection->forceDelete(['email' => 'old@example.com']);

// Restore soft-deleted documents
$result = $collection->restore(['status' => 'inactive']);
```

### Document Editor

#### JSON Editor

```javascript
// Open document editor
editor.openDocument(documentId);

// Validate JSON
const isValid = editor.validate();

// Format JSON
editor.format();

// Minify JSON
editor.minify();
```

#### Bulk Operations

```php
// Bulk insert
$collection->bulkInsert($documents);

// Bulk update
$collection->bulkUpdate([
    ['criteria' => ['status' => 'active'], 'data' => ['$set' => ['last_login' => date('c')]]],
    ['criteria' => ['status' => 'inactive'], 'data' => ['$set' => ['archived_at' => date('c')]]]
]);

// Bulk delete
$collection->bulkRemove(['status' => 'archived']);
```

### Document Search

#### Search Methods

```php
// Text search
$results = $collection->find([
    '$text' => ['$search' => 'john doe']
]);

// Fuzzy search
$results = $collection->find([
    'name' => ['$fuzzy' => ['search' => 'jon', 'distance' => 2]]
]);

// Field-specific search
$results = $collection->find([
    'email' => ['$regex' => '@gmail\.com$', '$options' => 'i']
]);
```

## 👥 User Management

### User Operations

#### Create User

```php
// Create admin user
$user = $client->createUser([
    'email' => 'admin@example.com',
    'password' => 'secure-password',
    'role' => 'admin',
    'name' => 'System Administrator',
    'status' => 'active'
]);

// Create regular user
$user = $client->createUser([
    'email' => 'user@example.com',
    'password' => 'user-password',
    'role' => 'user',
    'name' => 'Regular User',
    'status' => 'active'
]);
```

#### User Roles & Permissions

| Role            | Permissions                          | Description            |
| --------------- | ------------------------------------ | ---------------------- |
| **Super Admin** | All permissions                      | Full system access     |
| **Admin**       | Database management, user management | System administration  |
| **Developer**   | CRUD operations, schema management   | Development tasks      |
| **Viewer**      | Read-only access                     | Monitoring and viewing |
| **Custom**      | Defined permissions                  | Custom role            |

#### Permission System

```php
// Define custom role
$role = $client->createRole('editor', [
    'permissions' => [
        'database:create' => true,
        'database:read' => true,
        'database:update' => false,
        'database:delete' => false,
        'collection:create' => true,
        'collection:read' => true,
        'collection:update' => true,
        'collection:delete' => false,
        'document:create' => true,
        'document:read' => true,
        'document:update' => true,
        'document:delete' => false
    ]
]);

// Assign role to user
$user->assignRole('editor');
```

### User Authentication

#### Login/Logout

```php
// User login
$auth = $client->auth->login([
    'email' => 'user@example.com',
    'password' => 'password',
    'remember' => true
]);

// User logout
$client->auth->logout();

// Check authentication status
$isAuthenticated = $client->auth->check();
```

#### Two-Factor Authentication (2FA)

```php
// Enable 2FA
$user->enable2FA();

// Verify 2FA code
$isValid = $user->verify2FA('123456');

// Disable 2FA
$user->disable2FA();
```

### User Management UI

#### User Interface

1. **User List**: View all users with search/filter
2. **User Details**: Edit user information
3. **Role Management**: Assign/modify roles
4. **Permission Editor**: Fine-grained permissions
5. **Activity Log**: User actions history

#### Batch Operations

```php
// Batch activate users
$client->batchUpdateUsers(['user1', 'user2'], ['status' => 'active']);

// Batch delete users
$client->batchDeleteUsers(['user3', 'user4']);

// Batch assign roles
$client->batchAssignRoles(['user5', 'user6'], 'editor');
```

## 🔒 Security Features

### Encryption

#### Collection Encryption

```php
// Enable encryption for collection
$collection->setEncryptionKey('your-secret-key');

// Check if encrypted
$isEncrypted = $collection->isEncrypted();
```

#### Field-Level Encryption

```php
// Configure field encryption
$schema = [
    'name' => ['type' => 'string'],
    'email' => ['type' => 'email', 'encrypted' => true],
    'ssn' => ['type' => 'string', 'encrypted' => true]
];

$collection->setSchema($schema);
```

### Audit Logging

#### Enable Audit Logging

```php
// Enable audit logging
$collection->enableAuditLogging();

// Get audit logs
$logs = $collection->getAuditLogs([
    'start_date' => '2024-01-01',
    'end_date' => '2024-01-31',
    'user' => 'admin@example.com'
]);
```

#### Audit Log Types

| Type           | Description        | Example                 |
| -------------- | ------------------ | ----------------------- |
| **INSERT**     | Document insertion | User created new record |
| **UPDATE**     | Document update    | User modified record    |
| **DELETE**     | Document deletion  | User removed record     |
| **LOGIN**      | User login         | User logged in          |
| **LOGOUT**     | User logout        | User logged out         |
| **PERMISSION** | Permission change  | Role modified           |

### Access Control

#### IP Restrictions

```php
// Configure IP restrictions
$client->configureIPRestrictions([
    'allowed_ips' => ['192.168.1.0/24', '10.0.0.0/8'],
    'deny_all' => false
]);
```

#### Rate Limiting

```php
// Configure rate limiting
$client->configureRateLimiting([
    'requests_per_minute' => 60,
    'burst_limit' => 10,
    'window_size' => 60
]);
```

## 📊 Monitoring & Analytics

### System Monitoring

#### Health Check

```php
// Get system health
$health = $client->getHealth();

// Health status
$status = $health['status']; // 'healthy', 'warning', 'critical'

// Health metrics
$metrics = $health['metrics'];
```

#### Performance Monitoring

```php
// Get performance metrics
$performance = $client->getPerformanceMetrics();

// Metrics available
$metrics = [
    'queries_per_second' => 150,
    'avg_response_time' => 0.05,
    'slow_queries' => 5,
    'connection_pool' => ['used' => 10, 'max' => 100]
];
```

### Analytics

#### Usage Analytics

```php
// Get usage analytics
$analytics = $client->getUsageAnalytics();

// Analytics data
$data = [
    'daily_active_users' => 150,
    'queries_executed' => 50000,
    'storage_used' => '2.5GB',
    'growth_rate' => 15.5
];
```

#### Custom Reports

```php
// Create custom report
$report = $client->createReport([
    'name' => 'Monthly Usage Report',
    'type' => 'usage',
    'schedule' => 'monthly',
    'recipients' => ['admin@example.com'],
    'format' => 'pdf'
]);
```

## 🚀 Advanced Features

### Aggregation Pipeline

```php
// Define aggregation pipeline
$pipeline = [
    ['$match' => ['status' => 'active']],
    ['$group' => ['_id' => '$age', 'count' => ['$sum' => 1]]],
    ['$sort' => ['count' => -1]]
];

// Execute aggregation
$result = $collection->aggregate($pipeline);
```

### Real-time Updates

```javascript
// Subscribe to collection changes
const subscription = collection.subscribe("insert", (doc) => {
  console.log("New document inserted:", doc);
});

// Unsubscribe
subscription.unsubscribe();
```

### Caching

```php
// Enable caching
$collection->enableCache(['ttl' => 3600]);

// Cache management
$collection->clearCache();
$collection->getCacheStats();
```

## ⌨️ Keyboard Shortcuts

### General Shortcuts

| Shortcut       | Action         |
| -------------- | -------------- |
| `Ctrl/Cmd + K` | Quick search   |
| `Ctrl/Cmd + /` | Toggle sidebar |
| `Ctrl/Cmd + N` | New item       |
| `Ctrl/Cmd + S` | Save           |
| `Ctrl/Cmd + Z` | Undo           |
| `Ctrl/Cmd + Y` | Redo           |

### Editor Shortcuts

| Shortcut               | Action            |
| ---------------------- | ----------------- |
| `Ctrl/Cmd + D`         | Duplicate line    |
| `Ctrl/Cmd + /`         | Comment/uncomment |
| `Ctrl/Cmd + F`         | Find              |
| `Ctrl/Cmd + H`         | Replace           |
| `Ctrl/Cmd + Shift + F` | Find in files     |
| `Ctrl/Cmd + Shift + H` | Replace in files  |

### Navigation Shortcuts

| Shortcut               | Action           |
| ---------------------- | ---------------- |
| `Ctrl/Cmd + B`         | Go to file       |
| `Ctrl/Cmd + Shift + O` | Go to symbol     |
| `Ctrl/Cmd + Shift + M` | Go to definition |
| `F12`                  | Go to definition |
| `Shift + F12`          | Go to references |

## 💡 Best Practices

### Database Design

1. **Use meaningful collection names**: `users`, `orders`, `products`
2. **Implement proper indexing**: Index frequently queried fields
3. **Use schema validation**: Ensure data consistency
4. **Enable encryption**: For sensitive data
5. **Regular backups**: Schedule automated backups

### Performance Optimization

1. **Use appropriate indexes**: Don't over-index
2. **Limit query results**: Use `limit()` and `skip()`
3. **Use projections**: Only request needed fields
4. **Enable caching**: For frequently accessed data
5. **Monitor performance**: Regular health checks

### Security Best Practices

1. **Use strong passwords**: Minimum 12 characters
2. **Enable 2FA**: For all users
3. **Regular security audits**: Check for vulnerabilities
4. **Principle of least privilege**: Grant minimal required permissions
5. **Keep software updated**: Latest security patches

### Development Workflow

1. **Use version control**: Git for all changes
2. **Test thoroughly**: Unit and integration tests
3. **Document changes**: Keep changelog updated
4. **Code reviews**: Peer review for quality assurance
5. **Continuous integration**: Automated testing and deployment

---

**Tips & Tricks**:

- Use keyboard shortcuts for faster navigation
- Customize dashboard layout for your workflow
- Set up notifications for important events
- Use bulk operations for efficiency
- Regular backups prevent data loss

For more advanced features and customization options, see the [Developer Documentation](../developer/README.md).
