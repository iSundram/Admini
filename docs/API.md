# Admini API Documentation

The Admini Control Panel provides a RESTful API for managing hosting accounts, domains, email accounts, databases, and other resources programmatically.

## Authentication

All API requests require authentication using an API key sent in the `X-API-Key` header.

```
X-API-Key: your_api_key_here
```

For demo purposes, use the following API key:
```
X-API-Key: e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855
```

## Base URL

```
https://your-domain.com/api/
```

## Response Format

All API responses are returned in JSON format:

**Success Response:**
```json
{
    "success": true,
    "data": {...},
    "timestamp": "2024-01-01T12:00:00+00:00"
}
```

**Error Response:**
```json
{
    "success": false,
    "error": "Error message",
    "timestamp": "2024-01-01T12:00:00+00:00"
}
```

## Endpoints

### Users

#### Get All Users
```
GET /api/users
```

**Response:**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "username": "user1",
            "email": "user1@example.com",
            "role": "user",
            "status": "active",
            "created_at": "2024-01-01 12:00:00"
        }
    ]
}
```

#### Get User by ID
```
GET /api/users/{id}
```

#### Create User
```
POST /api/users
```

**Request Body:**
```json
{
    "username": "newuser",
    "email": "newuser@example.com",
    "password": "securepassword",
    "role": "user"
}
```

#### Update User
```
PUT /api/users/{id}
```

**Request Body:**
```json
{
    "email": "newemail@example.com",
    "status": "suspended",
    "disk_quota": 2048,
    "bandwidth_quota": 20480
}
```

#### Delete User
```
DELETE /api/users/{id}
```

### Domains

#### Get All Domains
```
GET /api/domains
```

#### Get Domain by ID
```
GET /api/domains/{id}
```

#### Create Domain
```
POST /api/domains
```

**Request Body:**
```json
{
    "user_id": 1,
    "domain_name": "example.com",
    "document_root": "/public_html"
}
```

#### Delete Domain
```
DELETE /api/domains/{id}
```

### Email Accounts

#### Get All Email Accounts
```
GET /api/email
```

#### Get Email Account by ID
```
GET /api/email/{id}
```

#### Create Email Account
```
POST /api/email
```

**Request Body:**
```json
{
    "user_id": 1,
    "domain_id": 1,
    "email": "test@example.com",
    "password": "emailpassword",
    "quota": 1024
}
```

#### Delete Email Account
```
DELETE /api/email/{id}
```

### Databases

#### Get All Databases
```
GET /api/databases
```

#### Get Database by ID
```
GET /api/databases/{id}
```

#### Create Database
```
POST /api/databases
```

**Request Body:**
```json
{
    "user_id": 1,
    "database_name": "myapp_db",
    "database_type": "mysql"
}
```

#### Delete Database
```
DELETE /api/databases/{id}
```

### Statistics

#### Get System Statistics
```
GET /api/statistics
```

**Response:**
```json
{
    "success": true,
    "data": {
        "users": {
            "total": 150,
            "active": 140,
            "suspended": 10
        },
        "domains": {
            "total": 200,
            "active": 195
        },
        "email_accounts": {
            "total": 500,
            "active": 480
        },
        "databases": {
            "total": 75,
            "mysql": 70,
            "postgresql": 5
        },
        "disk_usage": {
            "total_allocated": 153600,
            "total_used": 89432
        },
        "bandwidth_usage": {
            "total_allocated": 1536000,
            "total_used": 234567
        }
    }
}
```

## Status Codes

- `200` - OK
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `404` - Not Found
- `405` - Method Not Allowed
- `409` - Conflict
- `500` - Internal Server Error

## Rate Limiting

API requests are limited to 1000 requests per hour per API key. Rate limit headers are included in responses:

```
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 999
X-RateLimit-Reset: 1577836800
```

## Examples

### cURL Examples

**Get all users:**
```bash
curl -X GET \
  https://your-domain.com/api/users \
  -H 'X-API-Key: your_api_key_here'
```

**Create a new user:**
```bash
curl -X POST \
  https://your-domain.com/api/users \
  -H 'Content-Type: application/json' \
  -H 'X-API-Key: your_api_key_here' \
  -d '{
    "username": "newuser",
    "email": "newuser@example.com",
    "password": "securepassword",
    "role": "user"
  }'
```

**Create a domain:**
```bash
curl -X POST \
  https://your-domain.com/api/domains \
  -H 'Content-Type: application/json' \
  -H 'X-API-Key: your_api_key_here' \
  -d '{
    "user_id": 1,
    "domain_name": "example.com",
    "document_root": "/public_html"
  }'
```

### PHP Examples

```php
<?php
$apiKey = 'your_api_key_here';
$baseUrl = 'https://your-domain.com/api';

// Get all users
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/users');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-API-Key: ' . $apiKey
]);

$response = curl_exec($ch);
$data = json_decode($response, true);

if ($data['success']) {
    foreach ($data['data'] as $user) {
        echo "User: " . $user['username'] . " (" . $user['email'] . ")\n";
    }
}

curl_close($ch);
?>
```

### JavaScript Examples

```javascript
const apiKey = 'your_api_key_here';
const baseUrl = 'https://your-domain.com/api';

// Get all users
fetch(`${baseUrl}/users`, {
    headers: {
        'X-API-Key': apiKey
    }
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        data.data.forEach(user => {
            console.log(`User: ${user.username} (${user.email})`);
        });
    }
});

// Create a new user
fetch(`${baseUrl}/users`, {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-API-Key': apiKey
    },
    body: JSON.stringify({
        username: 'newuser',
        email: 'newuser@example.com',
        password: 'securepassword',
        role: 'user'
    })
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        console.log('User created:', data.data);
    }
});
```

## Error Handling

The API uses standard HTTP status codes and provides detailed error messages:

```json
{
    "success": false,
    "error": "Username or email already exists",
    "timestamp": "2024-01-01T12:00:00+00:00"
}
```

Common error scenarios:
- **400 Bad Request**: Missing required fields or invalid data
- **401 Unauthorized**: Invalid or missing API key
- **404 Not Found**: Resource does not exist
- **409 Conflict**: Resource already exists (e.g., duplicate username)
- **500 Internal Server Error**: Server-side error

## Support

For API support and questions, please contact the development team or refer to the main documentation.