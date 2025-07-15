# School API Documentation

This document provides a comprehensive overview of the School API, which is designed to manage school-related data.

## Authentication

The School API uses Bearer Token authentication. To authenticate your requests, you need to include an `Authorization` header with your API key as a Bearer token.

`Authorization: Bearer {YOUR_API_KEY}`

## Endpoints

### School Registration

- **POST** `/api/register-school`

  This endpoint allows you to register a new school.

  **Parameters:**

  - `name` (string, required): The name of the school.
  - `address` (string, required): The address of the school.
  - `email` (string, required): The email address of the school.
  - `password` (string, required): The password for the school's admin account.
  - `password_confirmation` (string, required): The confirmation of the password.

  **Example Request:**

  ```bash
  curl -X POST \
    http://localhost:8000/api/register-school \
    -H 'Content-Type: application/json' \
    -d '{
      "name": "My School",
      "address": "123 Main St",
      "email": "school@example.com",
      "password": "password",
      "password_confirmation": "password"
    }'
  ```

  **Example Response:**

  ```json
  {
    "message": "School registered successfully",
    "school": {
      "id": "9a6b7c8d-9e0f-1a2b-3c4d-5e6f7a8b9c0d",
      "name": "My School",
      "slug": "my-school",
      "address": "123 Main St",
      "email": "school@example.com",
      "phone": null,
      "logo_url": null,
      "established_at": null,
      "owner_name": null,
      "status": "active",
      "created_at": "2025-07-15T19:22:03.000000Z",
      "updated_at": "2025-07-15T19:22:03.000000Z"
    },
    "user": {
      "id": "9a6b7c8d-9e0f-1a2b-3c4d-5e6f7a8b9c0e",
      "name": "My School Admin",
      "email": "school@example.com",
      "role": "super_admin",
      "status": "active",
      "last_login": null,
      "created_at": "2025-07-15T19:22:03.000000Z",
      "updated_at": "2025-07-15T19:22:03.000000Z"
    }
  }
  ```
