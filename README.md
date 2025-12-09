# Simple Page Builder

A WordPress plugin that creates bulk pages via a secure REST API endpoint with advanced authentication and webhook notifications.

## Features

- **Secure REST API**: `POST /wp-json/pagebuilder/v1/create-pages`
- **API Key Authentication**: Secure, hashed keys with expiration dates and permission scopes
- **Admin Interface**: Full-featured dashboard to manage keys, logs, and settings
- **Webhooks**: Notify external services when pages are created (with HMAC-SHA256 signature)
- **Rate Limiting**: Protect your server from abuse (configurable per key)
- **Activity Logging**: Track all API requests with detailed logs
- **CSV Export**: Export activity logs for analysis

## Installation

1. Download the plugin files.
2. Create a folder named `simple-page-builder` in `wp-content/plugins/`.
3. Upload the files:
   - `simple-page-builder.php`
   - `uninstall.php`
   - `includes/` (database.php, api-handler.php, admin.php)
   - `admin/` (css/admin.css, js/admin.js)
4. Activate the plugin in WordPress Admin.
5. Go to **Tools > Page Builder** to manage settings and generate API keys.

## Admin Interface

The plugin provides a comprehensive admin interface with 5 tabs:

1. **API Keys Management**: Generate, view, and revoke API keys
2. **Activity Log**: View API request logs with filtering and CSV export
3. **Created Pages**: See all pages created via the API
4. **Settings**: Configure webhook URL, rate limits, and API access
5. **API Documentation**: Complete API reference with examples

## API Documentation

### Authentication

All requests must include the `Authorization` header:

```bash
Authorization: Bearer <your_api_key>
```

### Endpoint: Create Pages

**URL**: `/wp-json/pagebuilder/v1/create-pages`  
**Method**: `POST`

**Request Body**:

```json
{
  "pages": [
    {
      "title": "About Us",
      "content": "<h1>Welcome</h1><p>This is the about page.</p>",
      "status": "publish"
    },
    {
      "title": "Contact",
      "content": "<p>Contact us here.</p>",
      "status": "draft"
    }
  ]
}
```

**Response**:

```json
{
  "success": true,
  "pages": [
    {
      "id": 123,
      "title": "About Us",
      "url": "https://yoursite.com/about-us"
    }
  ],
  "errors": []
}
```

### Webhooks

Configure a webhook URL in the settings to receive notifications when pages are created. The payload includes an `X-Webhook-Signature` header (HMAC-SHA256) for verification.

**Webhook Payload**:

```json
{
  "event": "pages_created",
  "timestamp": "2025-10-07T14:30:00Z",
  "request_id": "req_abc123xyz",
  "api_key_name": "Production Server",
  "total_pages": 2,
  "pages": [
    {
      "id": 123,
      "title": "About Us",
      "url": "https://yoursite.com/about-us"
    }
  ]
}
```

**Verification Example (PHP)**:

```php
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'];
$secret = 'your_webhook_secret';

$computed = hash_hmac('sha256', $payload, $secret);

if (hash_equals($signature, $computed)) {
    // Verified!
    $data = json_decode($payload, true);
    // Process webhook data
}
```

### Error Codes

- **401** - Missing or invalid Authorization header
- **403** - Invalid API key, revoked key, expired key, or insufficient permissions
- **400** - Invalid request parameters
- **429** - Rate limit exceeded
- **503** - API is disabled

### Rate Limiting

Each API key has a configurable rate limit (default: 100 requests per hour). If exceeded, you'll receive a `429 Too Many Requests` response.

## Security Features

- API keys are hashed using WordPress password hashing (cannot be retrieved)
- HMAC-SHA256 signatures for webhook verification
- Rate limiting per API key
- Optional expiration dates for API keys
- Complete request logging with IP addresses
- Global API enable/disable switch

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher

## License

This plugin is provided as-is for the task submission.
