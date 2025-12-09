# Installation Instructions

## Quick Start

1. **Copy the plugin folder** to your WordPress installation:
   ```
   wp-content/plugins/simple-page-builder/
   ```

2. **Activate the plugin** in WordPress Admin → Plugins

3. **Access the admin interface** at **Tools → Page Builder**

## File Structure

```
simple-page-builder/
├── simple-page-builder.php  (Main plugin file)
├── uninstall.php            (Cleanup on uninstall)
├── README.md                (Documentation)
├── includes/
│   ├── database.php         (Database setup)
│   ├── api-handler.php      (REST API endpoint)
│   └── admin.php            (Admin interface & AJAX)
└── admin/
    ├── css/
    │   └── admin.css        (Admin styles)
    └── js/
        └── admin.js         (Admin JavaScript)
```

## Database Tables

The plugin creates the following database tables on activation:

- `wp_spb_api_keys` - Stores API keys (hashed)
- `wp_spb_api_logs` - Stores API request logs
- `wp_spb_created_pages` - Tracks pages created via API

## Initial Setup

1. **Generate an API Key**:
   - Go to Tools → Page Builder → API Keys tab
   - Click "Generate New API Key"
   - Enter a name (e.g., "Production Server")
   - Optionally set an expiration date
   - **Important**: Copy the key immediately - it won't be shown again!

2. **Configure Settings**:
   - Go to Settings tab
   - Set webhook URL (optional)
   - Set webhook secret (required if using webhooks)
   - Configure rate limit (default: 100 requests/hour)
   - Set default API key expiration

3. **Test the API**:
   - Use the API Documentation tab for examples
   - Test with cURL or Postman
   - Check Activity Log tab for request history

## Testing Checklist

After installation, test:

- [ ] Plugin activates without errors
- [ ] Database tables created (`wp_spb_api_keys`, `wp_spb_api_logs`, `wp_spb_created_pages`)
- [ ] Admin page loads (Tools → Page Builder)
- [ ] All 5 tabs are accessible
- [ ] Generate API key works
- [ ] Generated key is shown only once
- [ ] Revoke API key works
- [ ] Settings save works
- [ ] REST API endpoint works: `POST /wp-json/pagebuilder/v1/create-pages`
- [ ] API authentication works (valid key)
- [ ] API authentication fails (invalid key)
- [ ] Rate limiting works
- [ ] Webhook delivery works (if configured)
- [ ] Activity log shows requests
- [ ] Created pages tab shows pages
- [ ] CSV export works

## Troubleshooting

### Admin interface not loading?
- Check browser console for JavaScript errors
- Verify `admin/css/admin.css` and `admin/js/admin.js` exist and are accessible
- Check WordPress debug log: `define('WP_DEBUG', true);` in wp-config.php
- Ensure jQuery is loaded (WordPress admin includes it by default)

### API endpoint not working?
- Verify API key is active (not revoked)
- Check expiration date hasn't passed
- Verify rate limit hasn't been exceeded
- Check API is enabled in Settings tab
- Review Activity Log for error details

### Webhooks not sending?
- Verify webhook URL is configured in Settings
- Check webhook secret is set
- Verify webhook URL is accessible (not behind firewall)
- Check WordPress debug log for errors
- Review Activity Log for webhook status

### Database tables not created?
- Deactivate and reactivate the plugin
- Check WordPress database user has CREATE TABLE permissions
- Check WordPress debug log for errors

### API key authentication failing?
- Ensure you're using `Authorization: Bearer YOUR_KEY` header
- Verify key hasn't been revoked
- Check key hasn't expired
- Ensure key has write permissions

## Uninstallation

To completely remove the plugin:

1. Deactivate the plugin
2. Delete the plugin folder
3. The `uninstall.php` file will automatically:
   - Drop all database tables
   - Delete all plugin options

**Note**: Pages created via the API will remain in WordPress (only tracking data is removed).

## Support

For issues or questions, check:
- Activity Log tab for API request details
- WordPress debug log for PHP errors
- Browser console for JavaScript errors
