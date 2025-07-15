# Contributing to School Management System

Thank you for considering contributing to our school management system! This document provides guidelines and instructions for contributing to the project.

## Code of Conduct

This project adheres to a code of conduct. By participating, you are expected to uphold this code. Please report unacceptable behavior to the project maintainers.

## Development Setup

1. **Fork the repository** on GitHub
2. **Clone your fork** locally:
   ```bash
   git clone https://github.com/yourusername/school-management-system.git
   cd school-management-system
   ```
3. **Add the upstream repository**:
   ```bash
   git remote add upstream https://github.com/originalowner/school-management-system.git
   ```
4. **Follow the installation instructions** in the README.md

## Branch Strategy

We use **Git Flow** branching model:

- `main` - Production-ready code
- `develop` - Integration branch for features
- `feature/*` - New features
- `hotfix/*` - Critical bug fixes
- `release/*` - Release preparation

## Development Workflow

### 1. Before Starting Work

```bash
# Switch to develop branch
git checkout develop

# Pull latest changes
git pull upstream develop

# Create a new feature branch
git checkout -b feature/your-feature-name
```

### 2. Making Changes

- Write clean, readable code
- Follow PSR-12 coding standards
- Add appropriate comments
- Write tests for new functionality
- Update documentation if needed

### 3. Commit Guidelines

#### Commit Message Format
```
<type>(<scope>): <description>

<body>

<footer>
```

#### Types:
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting, etc.)
- `refactor`: Code refactoring
- `test`: Adding or updating tests
- `chore`: Maintenance tasks

#### Examples:
```bash
feat(students): add student registration endpoint
fix(auth): resolve token expiration issue
docs(readme): update installation instructions
test(students): add unit tests for student model
```

### 4. Code Quality Checks

Before committing, run these checks:

```bash
# Code style
./vendor/bin/php-cs-fixer fix

# Static analysis
./vendor/bin/phpstan analyse

# Run tests
php artisan test

# Check test coverage
php artisan test --coverage
```

### 5. Submitting Changes

```bash
# Stage your changes
git add .

# Commit with descriptive message
git commit -m "feat(students): add student registration endpoint"

# Push to your fork
git push origin feature/your-feature-name
```

## Pull Request Process

### 1. Create Pull Request

1. Go to your fork on GitHub
2. Click "New Pull Request"
3. Select base branch: `develop`
4. Select compare branch: `feature/your-feature-name`
5. Fill out the PR template

### 2. Pull Request Template

```markdown
## Description
Brief description of what this PR does.

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Testing
- [ ] Unit tests pass
- [ ] Integration tests pass
- [ ] Manual testing completed

## Checklist
- [ ] Code follows style guidelines
- [ ] Self-review completed
- [ ] Tests added/updated
- [ ] Documentation updated
- [ ] No breaking changes (or marked as such)

## Screenshots (if applicable)
Add screenshots here

## Related Issues
Closes #123
```

### 3. Review Process

- At least one maintainer must review
- All CI checks must pass
- Code coverage must not decrease
- Documentation must be updated if needed

### 4. After Review

- Address feedback in new commits
- Don't squash commits until approved
- Maintainers will merge using "Squash and merge"

## Coding Standards

### PHP Standards

Follow **PSR-12** coding standard:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class StudentController extends Controller
{
    public function index(): JsonResponse
    {
        $students = Student::all();
        
        return response()->json([
            'data' => $students,
            'message' => 'Students retrieved successfully'
        ]);
    }
}
```

### Laravel Best Practices

1. **Use Eloquent over Raw SQL**
2. **Validate in Form Requests**
3. **Use Resource Classes for API responses**
4. **Follow Repository Pattern for complex queries**
5. **Use Events and Listeners for decoupling**

### API Development

1. **RESTful conventions**
2. **Proper HTTP status codes**
3. **Consistent response format**
4. **OpenAPI documentation**
5. **Version your APIs**

## Testing Guidelines

### Test Structure

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StudentTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_student(): void
    {
        $studentData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890'
        ];

        $response = $this->postJson('/api/students', $studentData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'name',
                        'email',
                        'created_at'
                    ]
                ]);
    }
}
```

### Test Coverage

- Maintain at least 80% code coverage
- Test both happy path and edge cases
- Use factories for test data
- Mock external services

## Documentation

### API Documentation

Use OpenAPI annotations in controllers:

```php
/**
 * @OA\Get(
 *     path="/api/students",
 *     summary="Get all students",
 *     tags={"Students"},
 *     @OA\Response(response=200, description="Success")
 * )
 */
public function index(): JsonResponse
{
    // Implementation
}
```

### Code Documentation

- Document complex business logic
- Add PHPDoc blocks for methods
- Explain "why" not "what"
- Keep documentation up to date

## Issue Reporting

### Bug Reports

Include:
- Steps to reproduce
- Expected behavior
- Actual behavior
- Environment details
- Error messages/logs

### Feature Requests

Include:
- Use case description
- Proposed solution
- Alternative solutions
- Additional context

## Security

- Never commit sensitive data
- Use environment variables for configuration
- Follow OWASP guidelines
- Report security issues privately

## Getting Help

- Check existing issues and documentation
- Ask questions in discussions
- Contact maintainers for guidance
- Join our developer community

## Recognition

Contributors will be:
- Listed in the README
- Mentioned in release notes
- Invited to maintainer team (for regular contributors)

Thank you for contributing to our school management system! 🎓