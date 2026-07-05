# Cyfamod School Management System - Backend API

![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=flat-square&logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-11.x-FF2D20?style=flat-square&logo=laravel&logoColor=white)
![MariaDB](https://img.shields.io/badge/MariaDB-10.11%2B-003545?style=flat-square&logo=mariadb&logoColor=white)
![Testing](https://img.shields.io/badge/Tests-Passing-brightgreen?style=flat-square)

The core API backend for the Cyfamod School Management System. It powers the school administration web portals, agent affiliate dashboards, and public school websites.

---

## 🏛️ Architecture & Tenancy Overview
This application uses a multi-tenant architectural design:
- **Global Agents & Affiliates:** Managed centrally across the platform.
- **School-Level Tenancy:** Students, enrollments, grading, and settings are tightly isolated to specific `school_id` foreign keys to ensure data segregation.
- **RBAC (Role-Based Access Control):** Powered by `spatie/laravel-permission`, leveraging team scopes so users have different roles depending on which school context they are acting in.

> **CRITICAL:** Before writing any code, you **must** read the [IMPLEMENTATION_GUIDE.md](IMPLEMENTATION_GUIDE.md). It serves as the live, master blueprint for the Subscription and Agent Affiliate System architecture.

## 📦 Prerequisites
- **PHP** >= 8.2
- **Composer** >= 2.0
- **MariaDB** >= 10.11 (MySQL is fully compatible but MariaDB 10.11 is the production target).
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

# 5. Create a local database named `school-mgt-test-db` (or whatever you put in .env)
# Then run the migrations and seed the initial roles
php artisan migrate --seed

# 6. Boot the local server
php artisan serve
```
The API is now running locally at `http://localhost:8000`.

### 🔑 Default Login Credentials
Running `--seed` executes the `ComprehensiveSchoolSeeder`, which generates a complete ecosystem for the "Demo International School". 

**Admin Portal Access:**
- **Email:** `demo@gmail.com`
- **Password:** `12345678`

## 🧪 Running Tests
This project relies heavily on Pest and PHPUnit for testing. To ensure you don't accidentally wipe your local database during testing, tests use the isolated `.env.testing` configuration.

```bash
php artisan test
```

## 🚢 Deployment Architecture
The repository uses a dual-deployment pipeline defined in GitHub Actions (`.github/workflows/deploy.yml`):
1. **Production Environment:** Automatically deployed via legacy SSH workflows upon pushing to the `main` branch.
2. **Development Environment:** Deployed natively via GitHub Webhooks upon pushing to `dev` or `staging`. The CI pipeline runs tests, and the deployment server automatically pulls the code and rebuilds the containers.

## 🤝 How to Contribute
Please read our [CONTRIBUTING.md](CONTRIBUTING.md) guide before submitting any pull requests. It contains our formatting rules, branch naming conventions, and testing requirements.

## 🛠️ Common Troubleshooting

**Issue:** `Incorrect uuid value` when running tests or creating records.
**Fix:** MariaDB 10.11 enforces Strict Mode for UUIDs. Ensure you are using `Str::uuid()` instead of dummy strings in your factories.

**Issue:** CI `Deploy via SSH` step is failing on `dev`.
**Fix:** This is normal! Since `dev` is hosted on a webhook-based CD provider, SSH is skipped on purpose. The provider handles it natively.
