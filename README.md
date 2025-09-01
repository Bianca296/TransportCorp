# DAW Transport Company

A web application for managing shipping orders across land, air, and ocean transport.

## Setup

### Requirements
- PHP 7.4+
- MySQL/MariaDB  
- Apache (XAMPP/WAMP recommended for Windows)

### Installation

1. **Copy project to web directory**
   - XAMPP: `C:\xampp\htdocs\DAW`
   - WAMP: `C:\wamp64\www\DAW`

2. **Create database**
   - Go to `http://localhost/phpmyadmin`
   - Create database: `transport_company`

3. **Run migrations**
   - Visit: `http://localhost/DAW/database/install.php`
   - Or run migrations manually from `database/migrations/`

4. **Configure settings**
   - Update `config/database.php` with your database credentials
   - Update `config/config.php` with your site URL

### Default Users

After installation, you can login with:

- **Admin**: admin@transportcorp.com / password
- **Employee**: employee@transportcorp.com / password  
- **Customer**: customer@example.com / password

## Features

**Authentication & Users**
- Login/registration system
- Role-based access (admin, employee, customer)
- User management (admin panel)

**Order Management**
- Create shipping orders
- Support for land, air, ocean transport
- Order tracking system
- Cost calculation

**Dashboards**
- Admin: user management, system overview
- Employee: order processing, customer management
- Customer: create orders, track shipments

**Contact System**
- Contact form with PHPMailer integration
- Admin notifications and auto-replies
- Inquiry management

## Structure

```
DAW/
├── admin/          # Admin panel
├── employee/       # Employee interface  
├── customer/       # Customer interface
├── auth/           # Login/registration
├── config/         # Configuration files
├── classes/        # PHP classes (User, Order, etc)
├── includes/       # Shared includes and middleware
├── database/       # Migrations and database tools
└── public/         # CSS, assets
```

## Database

The application uses a migration system. All database changes are tracked in `database/migrations/`. 

To add new tables or modify existing ones, create a new migration file:
```bash
php database/create_migration.php "description_of_change"
```

## Configuration

**Database** (`config/database.php`):
- DB_HOST: usually 'localhost'
- DB_NAME: 'transport_company'  
- DB_USER: 'root' (XAMPP default)
- DB_PASS: '' (empty for XAMPP)

**Application** (`config/config.php`):
- SITE_URL: your local URL (e.g., 'http://localhost/DAW')
- Error reporting settings

**Email** (`config/email.php`):
- SMTP settings for contact form
- Email templates

## Contact Form Setup

To enable email functionality:

1. Install PHPMailer in `includes/PHPMailer/src/`
2. Configure SMTP settings in `config/email.php`
3. For Gmail: use app passwords, not your regular password

## Development

**Common tasks:**
- User management: `admin/users.php`
- Order management: `employee/orders.php`
- Database admin: `http://localhost/phpmyadmin`

**Security:**
- CSRF protection on all forms
- Role-based access control
- Session management
- Input validation and sanitization

## Troubleshooting

**Can't access the site:**
- Check Apache is running in XAMPP/WAMP
- Verify URL: `http://localhost/DAW`

**Database errors:**
- Ensure MySQL is running
- Check database exists in phpMyAdmin
- Verify credentials in `config/database.php`

**Permission issues:**
- Run XAMPP as administrator
- Check file permissions

## Production Notes

Before deploying:
- Change default passwords
- Update database credentials
- Set error reporting to 0 in `config/config.php`
- Use HTTPS
- Set up proper SMTP for email