# Eastern Railway I-Card System

A web-based system for managing employee identification cards for Eastern Railway.

## Features

- Employee authentication using HRMS ID and Date of Birth
- Role-based access control (Admin, Controlling Officers, Dealers, AWOs)
- I-Card application and management
- Digital workflow for approvals
- PDF I-Card generation
- Employee database management

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Composer (for dependency management)

## Installation

1. **Clone the repository**
   ```bash
   git clone [repository-url] icard-system
   cd icard-system
   ```

2. **Create a MySQL database**
   ```sql
   CREATE DATABASE eastern_railway_icard CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. **Import the database schema**
   ```bash
   mysql -u username -p eastern_railway_icard < database/schema.sql
   ```

4. **Configure the application**
   Copy `.env.example` to `.env` and update the database credentials:
   ```
   DB_HOST=localhost
   DB_NAME=eastern_railway_icard
   DB_USER=your_username
   DB_PASS=your_password
   ```

5. **Set up file permissions**
   ```bash
   chmod -R 755 uploads/
   chmod -R 755 assets/
   chmod 755 logs/
   ```

6. **Access the application**
   - Employee login: `http://localhost/icard-system/employee/login.php`
   - Admin login: `http://localhost/icard-system/admin/login.php`

## Default Credentials

### Admin
- Username: `admin`
- Password: `admin@123`

### Employee
- HRMS ID: [As imported in the database]
- Password: Date of Birth (YYYY-MM-DD format)

## Directory Structure

```
icard-system/
├── admin/               # Admin panel files
├── api/                 # API endpoints
├── assets/              # Static files (CSS, JS, images)
│   ├── css/
│   ├── js/
│   └── images/
├── config/              # Configuration files
├── database/            # Database schema and migrations
├── includes/            # PHP includes and functions
├── templates/           # Reusable templates
├── uploads/             # Uploaded files
│   ├── photos/         # Employee photos
│   ├── signatures/     # Signature files
│   └── icards/         # Generated I-Card PDFs
├── .htaccess           # Apache configuration
├── index.php           # Main entry point
└── README.md           # This file
```

## Security

- All passwords are hashed using PHP's `password_hash()`
- SQL injection prevention using prepared statements
- XSS protection with output escaping
- CSRF protection for forms
- Secure session handling

## Contributing

1. Fork the repository
2. Create a new branch for your feature (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Support

For support, please contact the IT department at Eastern Railway.
