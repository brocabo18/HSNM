# IP Inventory Management System

A comprehensive, fully-functional IP inventory and network management system with modern dark-themed UI.

## Features

### ✅ Authentication & Security
- **Login/Logout System**: Secure session management with password hashing
- **Role-Based Access Control**: Admin, User, and Viewer roles
- **Session Timeout**: Automatic logout after inactivity
- **Audit Logging**: All actions tracked with timestamps and user information

### ✅ Dashboard
- **Real-Time Statistics**: Total IPs, Active IPs, Available IPs, Conflict Alerts
- **Interactive Inventory Table**: Search, filter, and pagination
- **Status Indicators**: Active, Reserved, Static, Offline, Conflict states
- **Responsive Design**: Modern dark theme with Tailwind CSS

### ✅ Device Management
- **Full CRUD Operations**: Create, Read, Update, Delete devices
- **IP Validation**: Duplicate detection and format validation
- **MAC Address Formatting**: Automatic formatting and validation
- **Device Details**: Hostname, Type, Location, VLAN, Subnet, Notes

### ✅ IP Scanner
- **CIDR Support**: Scan entire subnets using CIDR notation
- **IP Range Scanning**: Scan from start IP to end IP
- **Online Detection**: Ping-based device discovery
- **Hostname Resolution**: Automatic DNS lookup
- **Quick Add**: Add discovered devices to inventory

### ✅ Subnet Management
- **Subnet Overview**: View all configured subnets
- **Utilization Tracking**: Real-time IP allocation percentages
- **Visual Indicators**: Color-coded utilization bars
- **Quick Actions**: View devices, run scans

### ✅ Audit Logs
- **Complete Activity Tracking**: All user actions logged
- **Advanced Filtering**: Filter by user, action, date range
- **Export to CSV**: Download audit trail
- **Action Icons**: Visual indicators for different event types

### ✅ Reports
- **IP Utilization Chart**: Interactive doughnut chart
- **Device Type Distribution**: Bar charts with percentages
- **Subnet Allocation Table**: Detailed utilization metrics
- **Status Summary**: Device count by status
- **Top Active Users**: User activity rankings
- **Export Options**: CSV export for all reports

### ✅ Settings (Admin Only)
- **User Management**: Create, edit, delete users
- **Role Assignment**: Admin, User, Viewer permissions
- **User Status**: Enable/disable user accounts
- **System Information**: Version, database, user count

## Installation

### Prerequisites
- XAMPP (Apache + MySQL + PHP 7.4+)
- Web browser (Chrome, Firefox, Edge, Safari)

### Setup Steps

1. **Extract Files**
   ```
   Extract the IP-Address-Inventory folder to:
   C:\xampp\htdocs\
   ```

2. **Start XAMPP**
   - Open XAMPP Control Panel
   - Start Apache
   - Start MySQL

3. **Create Database**
   - Open phpMyAdmin: http://localhost/phpmyadmin
   - Click "SQL" tab
   - Copy and paste contents of `database/init_database.sql`
   - Click "Go" to execute

4. **Access Application**
   ```
   Open browser and navigate to:
   http://localhost/IP-Address-Inventory/
   ```

5. **Login**
   ```
   Username: admin
   Password: admin123
   ```

   **⚠️ IMPORTANT**: Change the default password after first login!

## Database Configuration

If your MySQL has custom credentials, edit `config/config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');          // Change if needed
define('DB_PASS', '');              // Add password if needed
define('DB_NAME', 'ip_inventory');
```

## Default Data

The system comes with sample data:
### Subnets
1. Main Network (192.163.10.0/21) - 2,048 IP addresses

### Sample Devices
1. 192.163.10.1 - gateway-primary.local (Active)
2. 192.163.10.10 - file-server-01 (Reserved)
3. 192.163.10.25 - DESKTOP-8X912 (Conflict)
4. 192.163.10.55 - printer-marketing (Static)
5. 192.163.10.89 - guest-wifi-ap-02 (Offline)

## Usage Guide

### Adding a Device
1. Click "Add Device" button on dashboard
2. Fill in IP address (required) and other details
3. Select status and subnet
4. Click "Add Device"

### Scanning Network
1. Go to Scanner page
2. Enter IP range or CIDR notation (e.g., 192.168.1.0/24)
3. Select scan type
4. Click "Start Scan"
5. Add discovered devices to inventory

### Viewing Reports
1. Navigate to Reports page
2. Select date range
3. View charts and statistics
4. Export to CSV if needed

### Managing Users (Admin Only)
1. Go to Settings page
2. Click "Add User"
3. Fill in user details and assign role
4. Click "Create User"

### Viewing Audit Logs
1. Navigate to Audit Logs page
2. Use filters to search (user, action, date)
3. Export logs if needed

## Security Features

- ✅ Password hashing with bcrypt
- ✅ SQL injection prevention (prepared statements)
- ✅ XSS protection (input sanitization)
- ✅ Session management with timeout
- ✅ Role-based access control
- ✅ Comprehensive audit trail

## File Structure

```
IP-Address-Inventory/
├── api/
│   └── devices.php          # REST API for device operations
├── config/
│   └── config.php           # Database configuration
├── database/
│   └── init_database.sql    # Database schema
├── export/
│   └── csv.php              # CSV export functionality
├── includes/
│   └── auth_check.php       # Authentication middleware
├── index.php                # Main dashboard
├── login.php                # Login page
├── logout.php               # Logout handler
├── devices.php              # Device add/edit page
├── scanner.php              # IP scanner
├── subnets.php              # Subnet management
├── reports.php              # Reports and analytics
├── logs.php                 # Audit logs viewer
├── settings.php             # User management (admin)
└── README.md                # This file
```

## Browser Compatibility

- ✅ Chrome (Recommended)
- ✅ Firefox
- ✅ Edge
- ✅ Safari

## Troubleshooting

### Database Connection Error
- Verify XAMPP MySQL is running
- Check database credentials in `config/config.php`
- Ensure database was created successfully

### Login Issues
- Clear browser cookies
- Verify user exists in database
- Check user is_active = 1

### Permission Denied
- Ensure logged in user has appropriate role
- Admin features require admin role

## Version

**IP Manager v1.0.0**

## Author

Built with modern web technologies:
- PHP 7.4+
- MySQL
- Tailwind CSS
- Chart.js
- Material Symbols Icons

## License

Proprietary - Internal Use Only

---

**© 2026 IP Manager - Professional Network Inventory System**
