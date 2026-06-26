# Network Switch Inventory - Setup Guide

## Prerequisites

- **PHP** 7.4 or higher
- **MySQL** 5.7 or higher (or MariaDB)
- **Web Server** (Apache, Nginx, or PHP built-in server)

## Installation Steps

### 1. Database Setup

**Option A: Using MySQL Command Line**
```bash
mysql -u root -p < schema.sql
```

**Option B: Using phpMyAdmin**
1. Open phpMyAdmin in your browser
2. Click "Import" tab
3. Choose `schema.sql` file
4. Click "Go"

**Option C: Manual Creation**
1. Open MySQL client or phpMyAdmin
2. Copy the contents of `schema.sql`
3. Execute the SQL statements

### 2. Database Configuration

Edit `config.php` and update the database credentials:

```php
define('DB_HOST', 'localhost');      // Your database host
define('DB_NAME', 'network_inventory'); // Database name
define('DB_USER', 'root');           // Your MySQL username
define('DB_PASS', '');               // Your MySQL password
```

### 3. File Permissions

Ensure proper permissions for PHP files:
```bash
chmod 644 *.php
chmod 644 schema.sql
```

### 4. Run the Application

**Option A: Using PHP Built-in Server (Development)**
```bash
cd c:/Users/MICHAEL/OneDrive/Desktop/Network-Switch-Inventory
php -S localhost:8000
```
Then open: http://localhost:8000/dashboard.php

**Option B: Using XAMPP/WAMP**
1. Copy files to `htdocs` or `www` folder
2. Access: http://localhost/Network-Switch-Inventory/dashboard.php

**Option C: Using Production Server**
1. Upload all files to your web server
2. Ensure PHP and MySQL are configured
3. Access via your domain

## Features

### ✅ Dashboard
- View all network switches in a beautiful dark-themed table
- Real-time device count
- Sticky table header for better navigation

### ✅ Add New Switch
- Click "Add New Switch" button
- Fill in all required fields (marked with *)
- Optional fields: Port Details, Personnel, Maintenance dates, Remarks
- Instant validation and feedback

### ✅ Edit Switch
- Hover over any switch row
- Click the edit icon
- Modify any field
- Save changes

### ✅ Delete Switch
- Hover over any switch row
- Click the delete icon
- Confirm deletion in popup
- Switch removed from database

### ✅ Filtering
- Filter by Manufacturer (dropdown auto-populated from database)
- Filter by Location (dropdown auto-populated from database)
- Filter by Status (Active, Maintenance, Inactive)
- Combine multiple filters
- Clear all filters with one click

### ✅ Global Search
- Search across Switch ID, Model, Serial Number, and IP Address
- Real-time search results
- Works with filters

### ✅ CSV Export
- Click "Export CSV" button
- Exports currently filtered data
- Downloads with timestamp in filename
- UTF-8 encoded for Excel compatibility

## File Structure

```
Network-Switch-Inventory/
├── config.php          # Database configuration
├── schema.sql          # Database schema and sample data
├── api.php             # RESTful API endpoints
├── export.php          # CSV export functionality
└── dashboard.php       # Main dashboard interface
```

## API Endpoints

**List Switches**
```
GET api.php?action=list&manufacturer=Cisco&status=Active
```

**Get Filter Options**
```
GET api.php?action=filters
```

**Add Switch**
```
POST api.php?action=add
Content-Type: application/json
Body: { switch_id, model, manufacturer, ... }
```

**Update Switch**
```
POST api.php?action=update
Content-Type: application/json
Body: { id, switch_id, model, ... }
```

**Delete Switch**
```
POST api.php?action=delete
Content-Type: application/json
Body: { id }
```

## Troubleshooting

### "Database connection failed"
- Check your MySQL server is running
- Verify credentials in `config.php`
- Ensure `network_inventory` database exists

### "Table not found"
- Run the `schema.sql` file to create tables
- Check database name matches config

### Filters not working
- Check browser console for JavaScript errors
- Ensure `api.php` is accessible
- Verify PHP error logs

### CSV export not downloading
- Check PHP file permissions
- Verify `export.php` is accessible
- Check browser download settings

## Security Notes

- Change default database credentials in production
- Use HTTPS in production environments
- Implement proper authentication before deployment
- Sanitize all user inputs (already implemented via PDO prepared statements)
- Set appropriate file permissions

## Browser Compatibility

- Chrome 90+ ✅
- Firefox 88+ ✅
- Safari 14+ ✅
- Edge 90+ ✅

## Next Steps

1. **Add Authentication**: Implement user login/logout
2. **Add Pagination**: For better performance with large datasets
3. **Add Charts**: Visualize switch distribution by manufacturer, location, status
4. **Add Audit Logs**: Track who made changes and when
5. **Add Bulk Operations**: Import CSV, bulk delete, bulk update

## Support

For issues or questions, check:
- PHP error log (usually in `/var/log/apache2/error.log` or XAMPP logs)
- Browser developer console (F12)
- Database logs

Enjoy managing your network switches! 🚀
