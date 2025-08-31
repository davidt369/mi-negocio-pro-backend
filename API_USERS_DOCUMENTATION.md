# API Documentation - Users Management

## Overview
This API provides full CRUD operations for user management with role-based access control.

## Authentication
All endpoints (except login) require authentication using Sanctum tokens.

### Headers Required:
```
Accept: application/json
Content-Type: application/json
Authorization: Bearer {token}
```

## Endpoints

### 1. Authentication

#### POST /api/login
Login and get authentication token.

**Request:**
```json
{
  "email": "admin@minegocio.com",
  "password": "admin123"
}
```

**Response:**
```json
{
  "message": "Inicio de sesión exitoso",
  "user": {
    "id": 1,
    "email": "admin@minegocio.com",
    "full_name": "Administrador Principal",
    "phone": "+57 300 123 4567",
    "role": "owner",
    "is_active": true,
    "created_at": "2025-08-24T...",
    "updated_at": "2025-08-24T..."
  },
  "token": "1|abc123..."
}
```

#### POST /api/logout
Logout current session (requires auth).

#### POST /api/logout-all
Logout from all devices (requires auth).

#### POST /api/refresh
Refresh authentication token (requires auth).

#### GET /api/me
Get current user information (requires auth).

---

### 2. User Management

#### GET /api/users
List all users (owner only).

**Query Parameters:**
- `role`: Filter by role (owner/employee)
- `is_active`: Filter by active status (true/false)
- `search`: Search by name or email
- `per_page`: Items per page (default: 15)

**Response:**
```json
{
  "data": [...],
  "links": {...},
  "meta": {...}
}
```

#### POST /api/users
Create new user (owner only).

**Request:**
```json
{
  "email": "employee@minegocio.com",
  "full_name": "Nuevo Empleado",
  "phone": "+57 300 555 0123",
  "password": "password123",
  "role": "employee",
  "is_active": true
}
```

#### GET /api/users/{id}
Get specific user details (owner or own profile).

#### PUT /api/users/{id}
Update user (owner or own profile).

**Request:** (same as create, all fields optional)

#### DELETE /api/users/{id}
Delete user (owner only, cannot delete owner).

#### PATCH /api/users/{id}/toggle-status
Toggle user active status (owner only).

---

### 3. Profile Management

#### GET /api/users/profile
Get current user profile.

#### PUT /api/users/profile
Update current user profile.

**Request:**
```json
{
  "full_name": "Updated Name",
  "phone": "+57 300 999 0000",
  "email": "newemail@minegocio.com",
  "current_password": "current_password",
  "password": "new_password",
  "password_confirmation": "new_password"
}
```

## User Roles & Permissions

### Owner
- Can view, create, update, delete all users
- Can toggle user active status
- Cannot delete themselves
- Cannot deactivate themselves

### Employee
- Can view and update their own profile
- Cannot manage other users

## Database Schema

```sql
CREATE TYPE user_role AS ENUM ('owner','employee');

CREATE TABLE users (
  id                    serial PRIMARY KEY,
  email                 varchar(100) UNIQUE,
  full_name             varchar(100) NOT NULL,
  phone                 varchar(20),
  password_hash         varchar(255),
  role                  user_role NOT NULL DEFAULT 'employee',
  is_active             boolean NOT NULL DEFAULT true,
  created_at            timestamptz NOT NULL DEFAULT now(),
  updated_at            timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX idx_users_active ON users(is_active, role);
```

## Default Credentials

**Owner Account:**
- Email: admin@minegocio.com
- Password: admin123

## Error Responses

```json
{
  "message": "Error message",
  "errors": {
    "field": ["Field specific error"]
  }
}
```

Common HTTP Status Codes:
- 200: Success
- 201: Created
- 400: Bad Request
- 401: Unauthorized
- 403: Forbidden
- 404: Not Found
- 422: Validation Error
- 500: Server Error