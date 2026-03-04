# API Security - API Key Authentication

## Overview

To secure the backend API endpoints, we have implemented an API key authentication mechanism. Every request directed to the standard application API (e.g., creating bookings, fetching venues) must include a valid `x-api-key` header to proceed. Without this header or if the key is incorrect, the server will block the request and return an HTTP `401 Unauthorized` status.

## Environment Configuration

The API key needs to be set in your environment file (`.env`). Without it, the middleware will result in a `500 Server Error` on secured endpoints to prevent insecure fallbacks. 

Add the following variable to your `.env` file:

```env
API_KEY=your_super_secret_api_key_here
```

*Note: Make sure to keep this value secret and do not push `.env` to source control. Ensure your production environment mirrors this variable appropriately.*

## Usage for Clients (Frontend / Mobile / Third-Party)

All HTTP requests sent to protected API endpoints must include the `x-api-key` header with the configured key. 

### Example (cURL)

```bash
curl -X GET http://127.0.0.1:8000/api/rooms \
  -H "Accept: application/json" \
  -H "x-api-key: your_super_secret_api_key_here"
```

### Example (Axios/JavaScript)

If you are using Axios to interact with the backend API, you can set the header globally or on a per-request basis.

**Global Setup:**
```javascript
import axios from 'axios';

axios.defaults.baseURL = 'http://127.0.0.1:8000';
axios.defaults.headers.common['x-api-key'] = process.env.VITE_API_KEY; // Replace with however you reference it
```

**Per-Request Setup:**
```javascript
const fetchRooms = async () => {
  const response = await axios.get('/api/rooms', {
    headers: {
      'x-api-key': 'your_super_secret_api_key_here'
    }
  });
  return response.data;
}
```

## Implementation Details

- **Middleware Location**: `app/Http/Middleware/EnsureApiKeyIsValid.php`
- **Registration**: Wrapped around the relevant routes inside `routes/api.php`
- **Config Handling**: The key is pulled from the environment and resolved in `config/services.php` under `services.api.key`. 

### Public Route Exceptions

The following endpoints purposely bypass the `x-api-key` check for integration or external monitoring reasons:

- **`GET /api/health`**: Can be used by server orchestrators (e.g., Kubernetes, PM2, UptimeRobot) without needing an API key. 
