# School Management System

A comprehensive Laravel-based school management system designed to streamline administrative tasks, student management, and academic operations.

## Features

- **Student Management**: Registration, profiles, attendance tracking
- **Teacher Management**: Staff profiles, class assignments, schedules
- **Academic Management**: Courses, subjects, grading system
- **Administrative Tools**: Reports, notifications, user management
- **Authentication & Authorization**: Role-based access control
- **API Documentation**: Swagger/OpenAPI integration

## Technology Stack

- **Backend**: Laravel 10.x (PHP 8.1+)
- **Database**: MySQL 8.0+
- **Authentication**: Laravel Sanctum
- **API Documentation**: L5-Swagger
- **Testing**: PHPUnit, Pest
- **Code Quality**: PHP-CS-Fixer, PHPStan

## Prerequisites

- PHP 8.1 or higher
- Composer
- Node.js & NPM
- MySQL 8.0 or higher
- Git

## Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/school-management-system.git
   cd school-management-system
   ```

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Install Node.js dependencies**
   ```bash
   npm install
   ```

4. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Configure your `.env` file**
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=school_management
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   
   # Swagger Documentation
   L5_SWAGGER_CONST_HOST=http://localhost:8000
   L5_SWAGGER_GENERATE_ALWAYS=true
   ```

6. **Database setup**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

7. **Generate API documentation**
   ```bash
   php artisan l5-swagger:generate
   ```

8. **Build frontend assets**
   ```bash
   npm run dev
   ```

9. **Start the development server**
   ```bash
   php artisan serve
   ```

## Usage

### Accessing the Application

- **Web Interface**: `http://localhost:8000`
- **API Documentation**: `http://localhost:8000/api/documentation`
- **Admin Panel**: `http://localhost:8000/admin`

### Default Credentials

- **Admin**: admin@school.com / password
- **Teacher**: teacher@school.com / password
- **Student**: student@school.com / password

## API Endpoints

### Authentication
- `POST /api/login` - User login
- `POST /api/logout` - User logout
- `POST /api/register` - User registration

### Students
- `GET /api/students` - List all students
- `POST /api/students` - Create new student
- `GET /api/students/{id}` - Get student details
- `PUT /api/students/{id}` - Update student
- `DELETE /api/students/{id}` - Delete student

### Teachers
- `GET /api/teachers` - List all teachers
- `POST /api/teachers` - Create new teacher
- `GET /api/teachers/{id}` - Get teacher details

For complete API documentation, visit `/api/documentation` after starting the server.

## Testing

Run the test suite:

```bash
# Run all tests
php artisan test

# Run with coverage
php artisan test --coverage

# Run specific test file
php artisan test tests/Feature/StudentTest.php
```

## Code Quality

### PHP CS Fixer
```bash
# Fix code style
./vendor/bin/php-cs-fixer fix

# Check code style
./vendor/bin/php-cs-fixer fix --dry-run
```

### PHPStan
```bash
# Static analysis
./vendor/bin/phpstan analyse
```

## Development Workflow

1. Create a feature branch from `develop`
2. Make your changes
3. Write/update tests
4. Run code quality checks
5. Submit a pull request

## Database Schema

### Key Tables
- `users` - System users (students, teachers, admins)
- `students` - Student-specific information
- `teachers` - Teacher-specific information
- `courses` - Academic courses
- `subjects` - Course subjects
- `grades` - Student grades
- `attendance` - Attendance records

## Contributing

Please read [CONTRIBUTING.md](CONTRIBUTING.md) for details on our code of conduct and the process for submitting pull requests.

## Troubleshooting

### Common Issues

1. **Migration errors**: Ensure your database is running and credentials are correct
2. **Permission errors**: Check file permissions on `storage/` and `bootstrap/cache/`
3. **Swagger generation fails**: Ensure all controllers have proper OpenAPI annotations

### Getting Help

- Create an issue in the repository
- Check the [documentation](docs/)
- Contact the development team

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Authors

- **Your Name** - *Initial work* - [YourGitHub](https://github.com/yourusername)

## Acknowledgments

- Laravel Framework
- OpenAPI/Swagger
- Contributors and maintainers