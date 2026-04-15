# API Specification

## Authentication & User Management
### `POST /register` (primary) / `POST /signup` (alias)
- **Description**: Register a new user.
- **Request Body**: `{ "username": "string", "password": "string", "role": "string" }`
- **Response**: `{ "id": "int", "username": "string", "role": "string" }`
