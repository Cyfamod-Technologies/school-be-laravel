# 🏫 School Management System - Backend API

[![MIT License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![Laravel](https://img.shields.io/badge/Laravel-11.x-red?logo=laravel)](https://laravel.com/)
[![PHP](https://img.shields.io/badge/PHP-8.2+-blue?logo=php)](https://www.php.net/)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](CONTRIBUTING.md)
[![Code of Conduct](https://img.shields.io/badge/Contributor%20Covenant-2.0-4baaaa.svg)](CODE_OF_CONDUCT.md)

A comprehensive Laravel-based school management system designed to streamline administrative tasks, student management, and academic operations.

---

## 🏛️ Architecture & Tenancy Overview
This application uses a multi-tenant architectural design:
- **Global Agents & Affiliates:** Managed centrally across the platform.
- **School-Level Tenancy:** Students, enrollments, grading, and settings are tightly isolated to specific `school_id` foreign keys to ensure data segregation.
- **RBAC (Role-Based Access Control):** Powered by `spatie/laravel-permission`, leveraging team scopes so users have different roles depending on which school context they are acting in.

## 📦 Prerequisites
- **PHP** >= 8.2
- **Composer** >= 2.0
- **MariaDB** >= 10.11
- **Docker** (optional, but highly recommended for local databases).

## 🚀 One-Command Local Setup

Follow these exact steps to spin up the application on a clean machine:

```bash
# 1. Clone the repository
git clone https://github.com/Cyfamod-Technologies/school-be-laravel.git
cd school-be-laravel

# 2. Install dependencies
composer install

# 3. Setup your environment
cp .env.example .env

# 4. Generate the application key
# (Required: Mathematically encrypts sessions and passwords to prevent 500 crashes)
php artisan key:generate

# 5. Run database migrations and seed default credentials
php artisan migrate --seed

# 6. Boot the local server
php artisan serve
```
The API is now running locally at `http://localhost:8000`.

### 🔑 Seeded Login Credentials
> 💡 **Tip:** Running `php artisan db:seed` executes the `ComprehensiveSchoolSeeder`. The seeder will automatically print all generated admin, teacher, student, and parent test login credentials directly to your terminal at the end of the script execution.

## 🧪 Running Tests
This project relies heavily on Pest and PHPUnit for testing. To ensure you don't accidentally wipe your local database during testing, tests use the isolated `.env.testing` configuration.

```bash
php artisan test
```

## 🚢 Deployment Architecture
The repository uses a dual-deployment pipeline defined in GitHub Actions (`.github/workflows/deploy.yml`):
1. **Production Environment:** Automatically deployed via legacy SSH workflows upon pushing to the `main` branch.
2. **Development Environment:** Deployed natively via GitHub Webhooks upon pushing to `dev` or `staging`. The CI pipeline runs tests, and the deployment server automatically pulls the code and rebuilds the containers.

## 🤝 Contributing

We welcome contributions! Please read our [CONTRIBUTING.md](CONTRIBUTING.md) guide and Code of Conduct before submitting a PR.

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🔗 Related Projects

Frontend Application: [school-fe-nextjs](https://github.com/Cyfamod-Technologies/school-fe-nextjs)

## 💬 Support

- 📫 [Open an Issue](https://github.com/Cyfamod-Technologies/school-be-laravel/issues)
- 💡 [Discussions](https://github.com/Cyfamod-Technologies/school-be-laravel/discussions)
