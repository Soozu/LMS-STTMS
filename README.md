# Learning Management System (STTMS)

## Overview
A comprehensive Learning Management System built with PHP, designed to facilitate educational institution management, student-teacher interactions, and course administration.

## Features
- **User Management**
  - Student Portal
  - Teacher Portal
  - Admin Dashboard
- **Course Management**
  - Course Creation and Management
  - Assignment Handling
  - Resource Sharing
- **Authentication System**
  - Secure Login/Logout
  - Password Reset Functionality
  - Role-based Access Control

## Directory Structure
```
├── admin/          # Administrative dashboard files
├── cron/           # Scheduled tasks
├── css/            # Stylesheet files
├── database/       # Database configuration and migrations
├── images/         # Image assets
├── includes/       # PHP include files
├── js/            # JavaScript files
├── logs/          # System logs
├── student/       # Student portal files
├── teacher/       # Teacher portal files
├── uploads/       # File upload directory
└── vendor/        # Third-party dependencies
```

## Requirements
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- Composer for dependency management

## Installation
1. Clone the repository
2. Configure your web server to point to the project directory
3. Create a MySQL database
4. Import the database schema from `database/`
5. Configure database connection in configuration files
6. Run `composer install` to install dependencies

## Configuration
1. Set up database credentials in configuration file
2. Configure email settings for password reset functionality
3. Set appropriate file permissions for uploads and logs directories

## Security Features
- Password Hashing
- SQL Injection Prevention
- XSS Protection
- CSRF Protection
- Secure Session Management

## Contributing
Please read the contributing guidelines before submitting pull requests.

## License
This project is licensed under the MIT License - see the LICENSE file for details.

## Support
For support and queries, please contact the system administrator. 