# API Documentation - BangronDB Admin Panel

Dokumentasi API lengkap untuk BangronDB Admin Panel. API ini mengikuti standar RESTful dan mendukung autentikasi, rate limiting, dan dokumentasi otomatis.

## 📋 API Overview

### Base URL

- **Development**: `http://localhost:8080/api/v1`
- **Production**: `https://api.yourdomain.com/api/v1`

### Authentication

API menggunakan token-based authentication dengan JWT (JSON Web Token).

```bash
# Login untuk mendapatkan token
curl -X POST "http://localhost:8080/api/v1/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email": "admin@example.com", "password": "password"}'

# Gunakan token untuk request berikutnya
curl -X GET "http://localhost:8080/api/v1/databases" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### Response Format

Semua responses mengikuti format JSON standar:

```json
{
  "success": true,
  "data": {},
  "message": "Operation successful",
  "timestamp": "2024-01-15T10:30:00Z",
  "request_id": "req_123456789"
}
```

### Error Responses

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Invalid input data",
    "details": {
      "field": "email",
      "message": "Email is required"
    }
  },
  "timestamp": "2024-01-15T10:30:00Z",
  "request_id": "req_123456789"
}
```

### HTTP Status Codes

| Code | Description                             |
| ---- | --------------------------------------- |
| 200  | OK - Request successful                 |
| 201  | Created - Resource created              |
| 400  | Bad Request - Invalid request           |
| 401  | Unauthorized - Authentication required  |
| 403  | Forbidden - Insufficient permissions    |
| 404  | Not Found - Resource not found          |
| 429  | Too Many Requests - Rate limit exceeded |
| 500  | Internal Server Error - Server error    |

## 🔧 Authentication API

### Login

**Endpoint**: `POST /auth/login`

**Description**: Authenticate user and get JWT token

**Request Body**:

```json
{
  "email": "admin@example.com",
  "password": "password",
  "remember": false
}
```

**Response**:

```json
{
  "success": true,
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "expires_in": 3600,
    "user": {
      "id": "user_123",
      "email": "admin@example.com",
      "name": "System Administrator",
      "role": "admin"
    }
  }
}
```

### Logout

**Endpoint**: `POST /auth/logout`

**Description**: Invalidate user token and logout

**Headers**:

```
Authorization: Bearer YOUR_JWT_TOKEN
```

**Response**:

```json
{
  "success": true,
  "message": "Logged out successfully"
}
```

### Refresh Token

**Endpoint**: `POST /auth/refresh`

**Description**: Refresh JWT token using refresh token

**Request Body**:

```json
{
  "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

**Response**:

```json
{
  "success": true,
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "expires_in": 3600
  }
}
```

### Change Password

**Endpoint**: `POST /auth/change-password`

**Description**: Change user password

**Headers**:

```
Authorization: Bearer YOUR_JWT_TOKEN
```

**Request Body**:

```json
{
  "current_password": "old_password",
  "new_password": "new_password",
  "confirm_password": "new_password"
}
```

### 2FA Setup

**Endpoint**: `POST /auth/2fa/setup`

**Description**: Setup two-factor authentication

**Headers**:

```
Authorization: Bearer YOUR_JWT_TOKEN
```

**Response**:

```json
{
  "success": true,
  "data": {
    "secret": "JBSWY3DPEHPK3PXP",
    "qr_code": "data:image/png;base64,..."
  }
}
```

### 2FA Verify

**Endpoint**: `POST /auth/2fa/verify`

**Description**: Verify two-factor authentication code

**Headers**:

```
Authorization: Bearer YOUR_JWT_TOKEN
```

**Request Body**:

```json
{
  "code": "123456"
}
```

## 🗄️ Database API

### List Databases

**Endpoint**: `GET /databases`

**Description**: Get list of all databases

**Headers**:

```
Authorization: Bearer YOUR_JWT_TOKEN
```

**Query Parameters**:

- `page` (int): Page number (default: 1)
- `limit` (int): Items per page (default: 20)
- `search` (string): Search term

**Response**:

```json
{
  "success": true,
  "data": {
    "databases": [
      {
        "id": "db_123",
        "name": "myapp",
        "path": "/data/myapp",
        "size": "2.5MB",
        "document_count": 1500,
        "collection_count": 5,
        "created_at": "2024-01-01T10:00:00Z",
        "last_accessed": "2024-01-15T10:30:00Z"
      }
    ],
    "pagination": {
      "total": 1,
      "page": 1,
      "limit": 20,
      "total_pages": 1
    }
  }
}
```

### Create Database

**Endpoint**: `POST /databases`

**Description**: Create new database

**Headers**:

```
Authorization: Bearer YOUR_JWT_TOKEN
```

**Request Body**:

```json
{
  "name": "myapp",
  "path": "/data/myapp",
  "encryption": true,
  "compression": true,
  "backup_enabled": true,
  "max_size": "1GB"
}
```

### Get Database

**Endpoint**: `GET /databases/{id}`

**Description**: Get database details

**Headers**:

```
Authorization: Bearer YOUR_JWT_TOKEN
```

**Response**:

```json
{
  "success": true,
  "data": {
    "id": "db_123",
    "name": "myapp",
    "path": "/data/myapp",
    "size": "2.5MB",
    "document_count": 1500,
    "collection_count": 5,
    "encryption": true,
    "compression": true,
    "backup_enabled": true,
    "created_at": "2024-01-01T10:00:00Z",
    "last_accessed": "2024-01-15T10:30:00Z",
    "health": {
      "status": "healthy",
      "integrity": "ok",
      "performance": "good"
    }
  }
}
```

### Update Database

**Endpoint**: `PUT /databases/{id}`

**Description**: Update database configuration

**Headers**:

```
Authorization: Bearer YOUR_JWT_TOKEN
```

**Request Body**:

```json
{
  "name": "myapp_updated",
  "encryption": true,
  "backup_enabled": true
}
```

### Delete Database

**Endpoint**: `DELETE /databases/{id}`

**Description**: Delete database

**Headers**:

```
Authorization: Bearer YOUR_JWT_TOKEN
```

### Export Database

**Endpoint**: `POST /databases/{id}/export`

**Description**: Export database

**Headers**:

```
Authorization: Bearer YOUR_JWT_TOKEN
```

**Request Body**:

```json
{
  "format": "json",
  "include_metadata": true,
  "encryption_key": "your-encryption-key"
}
```

### Import Database

**Endpoint**: `POST /databases/{id}/import`

**Description**: Import data to database

**Headers**:

```
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: multipart/form-data
```

**Form Data**:

- `file`: Database file to import
- `format`: Import format (json, binary, sql)
- `validate`: Validate import (true/false)

## 📊 Collection API

### List Collections

**Endpoint**: `GET /databases/{database_id}/collections`

**Description**: Get list of collections in database

**Headers**:

```
Authorization: Bearer YOUR_JWT_TOKEN
```

**Query Parameters**:

- `page` (int): Page number
- `limit` (int): Items per page
- `search` (string): Search term

**Response**:

```json
{
  "success": true,
  "data": {
    "collections": [
      {
        "id": "col_123",
        "name": "users",
        "database_id": "db_123",
        "document_count": 500,
        "size": "1.2MB",
        "schema": {
          "name": { "type": "string" },
          "email": { "type": "email" }
        },
        "indexes": ["email"],
        "encryption": true,
        "created_at": "2024-01-01T10:00:00Z"
      }
    ]
  }
}
```

### Create Collection

**Endpoint**: `POST /databases/{database_id}/collections`

**Description**: Create new collection

**Headers**:

```
Authorization: Bearer YOUR_JWT_TOKEN
```

**Request Body**:

```json
{
  "name": "users",
  "schema": {
    "name": {
      "required": true,
      "type": "string",
      "min": 2,
      "max": 100
    },
    "email": {
      "required": true,
      "type": "email",
      "unique": true
    }
  },
  "encryption": true,
  "indexes": ["email"]
}
```

### Get Collection

**Endpoint**: `GET /databases/{database_id}/collections/{id}`

**Description**: Get collection details

**Headers**:

```
Authorization: Bearer YOUR_JWT_TOKEN
```

### Update Collection

**Endpoint**: `PUT /databases/{database_id}/collections/{id}`

**Description**: Update collection configuration

**Headers**:

```
Authorization: Bearer YOUR_JWT_TOKEN
```

**Request Body**:

```json
{
  "name": "user_profiles",
  "schema": {
    "name": { "required": true, "type": "string" },
    "email": { "required": true, "type": "email" }
  }
}
```

### Delete Collection

**Endpoint**: `DELETE /databases/{database_id}/collections/{id}`

**Description**: Delete collection

**Headers**:

```
Authorization: Bearer YOUR_JWT_TOKEN
```

### Create Index

**Endpoint**: `POST /databases/{database_id}/collections/{id}/indexes`

**Description**: Create index on collection

**Headers**:

```
Authorization: Bearer YOUR_JWT_TOKEN
```

**Request Body**:

```json
{
  "fields": ["email"],
  "type": "unique",
  "name": "email_index"
}
```

### Delete Index

**Endpoint**: `DELETE /databases/{database_id}/collections/{id}/indexes/{index_name}`

**Description**: Delete index from collection

**Headers**:

```
Authorization: Bearer YOUR_JWT_TOKEN
```

## 📝 Document API

### List Documents

**Endpoint**: `GET /databases/{database_id}/collections/{collection_id}/documents`

**Description**: Get documents from collection

**Headers**:

```
Authorization: Bearer YOUR_JWT_TOKEN
```

**Query Parameters**:

- `filter` (string): MongoDB filter query
- `sort` (string): Sort specification
- `limit` (int): Limit results
- `skip` (int): Skip results
- `projection` (string): Fields to include/exclude

**Example Request**:

```bash
curl -X GET "http://localhost:8080/api/v1/databases/db_123/collections/col_123/documents?filter={\"age\": {\"$gt\": 25}}&sort={\"name\": 1}&limit=10" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

**Response**:

```json
{
  "success": true,
  "data": {
    "documents": [
      {
        "_id": "doc_123",
        "name": "John Doe",
        "email": "john@example.com",
        "age": 30,
        "created_at": "2024-01-01T10:00:00Z"
      }
    ],
    "pagination": {
      "total": 150,
      "limit": 10,
      "skip": 0
    }
  }
}
```

### Get Document

**Endpoint**: `GET /databases/{database_id}/collections/{collection_id}/documents/{id}`

**Description**: Get specific document

**Headers**:

```
Authorization: Bearer YOUR_JWT_TOKEN
```

### Create Document

**Endpoint**: `POST /databases/{database_id}/collections/{collection_id}/documents`

**Description**: Create new document

**Headers**:

```
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
```

**Request Body**:

```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "age": 30,
  "status": "active"
}
```

### Update Document

**Endpoint**: `PUT /databases/{database_id}/collections/{collection_id}/documents/{id}`

**Description**: Update document

**Headers**:

```
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
```

**Request Body**:

```json
{
  "name": "John Updated",
  "age": 31
}
```

### Delete Document

**Endpoint**: `DELETE /databases/{database_id}/collections/{collection_id}/documents/{id}`

**Description**: Delete document

**Headers**:

```
Authorization: Bearer YOUR_JWT_TOKEN
```

### Bulk Operations

**Endpoint**: `POST /databases/{database_id}/collections/{collection_id}/documents/bulk`

**Description**: Perform bulk operations

**Headers**:

```
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
```

**Request Body**:

```json
{
  "operations": [
    {
      "type": "insert",
      "data": { "name": "User 1", "email": "user1@example.com" }
    },
    {
      "type": "update",
      "filter": { "status": "active" },
      "data": { "$set": { "last_login": "2024-01-15T10:30:00Z" } }
    },
    {
      "type": "delete",
      "filter": { "status": "inactive" }
    }
  ]
}
```

## 📈 Monitoring API

### System Health

**Endpoint**: `GET /system/health`

**Description**: Get system health status

**Headers**:

```
Authorization: Bearer YOUR_JWT_TOKEN
```

**Response**:

```json
{
  "success": true,
  "data": {
    "status": "healthy",
    "checks": {
      "database": "healthy",
      "storage": "healthy",
      "memory": "warning",
      "cpu": "healthy"
    },
    "metrics": {
      "uptime": "15d 2h 30m",
      "memory_usage": "75%",
      "cpu_usage": "45%",
      "disk_usage": "60%"
    }
  }
}
```

### Performance Metrics

**Endpoint**: `GET /system/metrics`

**Description**: Get system performance metrics

**Headers**:

```
Authorization: Bearer YOUR_JWT_TOKEN
```

**Response**:

```json
{
  "success": true,
  "data": {
    "queries": {
      "per_second": 150,
      "slow_queries": 5,
      "avg_response_time": 0.05
    },
    "connections": {
      "active": 25,
      "max": 100,
      "waiting": 0
    },
    "cache": {
      "hit_rate": 85,
      "memory_usage": "512MB"
    }
  }
}
```

### Activity Logs

**Endpoint**: `GET /system/audit`

**Description**: Get system activity logs

**Headers**:

```
Authorization: Bearer YOUR_JWT_TOKEN
```

**Query Parameters**:

- `start_date` (string): Start date (ISO format)
- `end_date` (string): End date (ISO format)
- `user` (string): Filter by user
- `action` (string): Filter by action type

### Real-time Updates

**Endpoint**: `GET /system/realtime`

**Description**: Server-sent events for real-time updates

**Headers**:

```
Authorization: Bearer YOUR_JWT_TOKEN
Accept: text/event-stream
```

**Event Stream**:

```text
event: system_update
data: {"type": "database_created", "database": "myapp", "timestamp": "2024-01-15T10:30:00Z"}

event: user_activity
data: {"user": "admin@example.com", "action": "login", "timestamp": "2024-01-15T10:30:00Z"}

event: performance_alert
data: {"metric": "cpu_usage", "value": 95, "threshold": 90}
```

## 👥 User Management API

### List Users

**Endpoint**: `GET /users`

**Description**: Get list of all users

**Headers**:

```
Authorization: Bearer YOUR_JWT_TOKEN
```

### Create User

**Endpoint**: `POST /users`

**Description**: Create new user

**Headers**:

```
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
```

**Request Body**:

```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "secure-password",
  "role": "user",
  "status": "active"
}
```

### Get User

**Endpoint**: `GET /users/{id}`

**Description**: Get user details

**Headers**:

```
Authorization: Bearer YOUR_JWT_TOKEN
```

### Update User

**Endpoint**: `PUT /users/{id}`

**Description**: Update user information

**Headers**:

```
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
```

### Delete User

**Endpoint**: `DELETE /users/{id}`

**Description**: Delete user

**Headers**:

```
Authorization: Bearer YOUR_JWT_TOKEN
```

### Roles Management

**Endpoint**: `GET /roles`

**Description**: Get list of available roles

**Headers**:

```
Authorization: Bearer YOUR_JWT_TOKEN
```

## 🔒 Security API

### Security Settings

**Endpoint**: `GET /security/settings`

**Description**: Get security settings

**Headers**:

```
Authorization: Bearer YOUR_JWT_TOKEN
```

### Update Security Settings

**Endpoint**: `PUT /security/settings`

**Description**: Update security settings

**Headers**:

```
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
```

**Request Body**:

```json
{
  "password_policy": {
    "min_length": 12,
    "require_numbers": true,
    "require_special_chars": true
  },
  "session_timeout": 3600,
  "max_login_attempts": 5,
  "ip_whitelist": ["192.168.1.0/24"]
}
```

### Security Audit

**Endpoint**: `GET /security/audit`

**Description**: Get security audit logs

**Headers**:

```
Authorization: Bearer YOUR_JWT_TOKEN
```

## 📊 Analytics API

### Usage Analytics

**Endpoint**: `GET /analytics/usage`

**Description**: Get usage analytics

**Headers**:

```
Authorization: Bearer YOUR_JWT_TOKEN
```

**Query Parameters**:

- `period` (string): Period (daily, weekly, monthly, yearly)
- `start_date` (string): Start date
- `end_date` (string): End date

### Custom Reports

**Endpoint**: `GET /analytics/reports`

**Description**: Get custom reports

**Headers**:

```
Authorization: Bearer YOUR_JWT_TOKEN
```

## 🚀 Rate Limiting

API menerapkan rate limiting untuk mencegah abuse:

- **Requests per minute**: 60 requests per minute
- **Burst limit**: 10 requests per second
- **Window size**: 60 seconds

Header rate limiting:

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
X-RateLimit-Reset: 1642255200
```

## 📝 SDK Examples

### PHP SDK

```php
<?php
require 'vendor/autoload.php';

use BangronDB\Client;

$client = new Client('http://localhost:8080/api/v1', 'YOUR_API_KEY');

// Authenticate
$auth = $client->auth->login('admin@example.com', 'password');

// Get databases
$databases = $client->databases->list();

// Create database
$db = $client->databases->create('myapp', [
    'path' => '/data/myapp',
    'encryption' => true
]);

// Get collections
$collections = $client->collections->list($db->id);
```

### JavaScript SDK

```javascript
import BangronDB from "bangrondb-js";

const client = new BangronDB({
  baseUrl: "http://localhost:8080/api/v1",
  apiKey: "YOUR_API_KEY",
});

// Authenticate
const auth = await client.auth.login("admin@example.com", "password");

// Get databases
const databases = await client.databases.list();

// Create database
const db = await client.databases.create("myapp", {
  path: "/data/myapp",
  encryption: true,
});
```

### Python SDK

```python
from bangrondb import Client

client = Client('http://localhost:8080/api/v1', 'YOUR_API_KEY')

# Authenticate
auth = client.auth.login('admin@example.com', 'password')

# Get databases
databases = client.databases.list()

# Create database
db = client.databases.create('myapp', {
    'path': '/data/myapp',
    'encryption': True
})
```

## 📋 Postman Collection

[Download Postman Collection](https://github.com/bangrondb/bangrondb/raw/main/docs/api/BangronDB_Admin_Panel.postman_collection.json)

## 🔧 Webhooks

### Create Webhook

**Endpoint**: `POST /webhooks`

**Description**: Create webhook for events

**Headers**:

```
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
```

**Request Body**:

```json
{
  "name": "Document Created",
  "event": "document.created",
  "url": "https://your-webhook-url.com/hook",
  "secret": "your-webhook-secret",
  "events": ["document.created", "document.updated"]
}
```

### List Webhooks

**Endpoint**: `GET /webhooks`

**Description**: Get list of webhooks

**Headers**:

```
Authorization: Bearer YOUR_JWT_TOKEN
```

### Test Webhook

**Endpoint**: `POST /webhooks/{id}/test`

**Description**: Test webhook endpoint

**Headers**:

```
Authorization: Bearer YOUR_JWT_TOKEN
```

## 📊 API Documentation

Untuk dokumentasi API yang lebih detail dan interaktif, kunjungi:

- **Swagger UI**: `http://localhost:8080/api/docs`
- **ReDoc**: `http://localhost:8080/api/redoc`
- **OpenAPI Spec**: `http://localhost:8080/api/openapi.json`

---

**Tips**:

- Gunakan environment variables untuk menyimpan API keys
- Implementasi retry logic untuk failed requests
- Monitor API usage dengan analytics
- Gunakan rate limiting untuk endpoints publik
- Dokumentasikan API changes dengan changelog

For support, contact: api-support@bangrondb.io
