# Comprehensive School Database Seeder

This seeder creates a complete demo school environment with all necessary data for testing and demonstration purposes.

## What Gets Created

### School Account
- **Name:** Demo International School
- **Email:** demo@gmail.com
- **Password:** 12345678
- **Role:** Administrator

### Class Structure

**Note:** Classes are created with an `order` column (1-14) and are automatically sorted everywhere in the application via a global scope. No manual sorting needed!

#### Nursery Section (Main Arm)
- Nursery 1 (Main)
- Nursery 2 (Main)
- Nursery 3 (Main)

#### Primary Section (Main Arm)
- Primary 1 (Main)
- Primary 2 (Main)
- Primary 3 (Main)
- Primary 4 (Main)
- Primary 5 (Main)

**Note:** Nursery and Primary classes have a default "Main" arm because the database requires all students to be assigned to a class arm.

#### Junior Secondary Section (JSS) - With Arms A, B, C
- JSS 1 (A, B, C)
- JSS 2 (A, B, C)
- JSS 3 (A, B, C)

#### Senior Secondary Section (SS) - With Departments
- SS 1 (Science, Arts, Commercial)
- SS 2 (Science, Arts, Commercial)
- SS 3 (Science, Arts, Commercial)

### Academic Sessions
- **2024/2025** (3 terms)
  - 1st Term
  - 2nd Term
  - 3rd Term
- **2025/2026** (3 terms)
  - 1st Term
  - 2nd Term
  - 3rd Term

### Subjects (28 subjects total)

#### Core Subjects
- Mathematics
- English Language
- Basic Science
- Social Studies
- Civic Education

#### Science Subjects
- Biology
- Physics
- Chemistry
- Agricultural Science
- Geography

#### Arts Subjects
- Literature in English
- Government
- History
- Christian Religious Studies
- Islamic Religious Studies

#### Commercial Subjects
- Economics
- Commerce
- Accounting
- Business Studies

#### Other Subjects
- Computer Studies
- Physical Education
- Home Economics
- Fine Arts
- Music
- French
- Yoruba
- Igbo
- Hausa

### Assessment Components
For each subject in the first session/term:
- CA1 (10 marks, 10% weight)
- CA2 (10 marks, 10% weight)
- CA3 (10 marks, 10% weight)
- EXAM (70 marks, 70% weight)

### Skills and Behaviour Assessment
- Attentiveness
- Communication Skills
- Handwriting
- Honesty
- Neatness
- Perseverance
- Politeness
- Promptness in completing work
- Punctuality
- Self Control

### People
- **Teachers:** 10 teachers with Nigerian names
- **Parents:** 20 parents with Nigerian names and occupations
- **Students:** 200 students randomly distributed across all classes (approximately 60% linked to parents)

## How to Run

### Recommended: Fresh Migration with All Seeders

```bash
cd school-be-php
php artisan migrate:fresh --seed
```

This single command will:
1. Drop all tables and re-run migrations
2. Seed foundation data (blood groups, grade scales, countries/states/LGAs)
3. Create the comprehensive demo school with all data

### Alternative: Seed Only (if database is already migrated)

```bash
cd school-be-php
php artisan db:seed
```

## After Seeding

### Login Credentials
- **URL:** Your application URL
- **Email:** demo@gmail.com
- **Password:** 12345678

### What to Check
1. ✅ School profile is set up
2. ✅ All 14 classes are created (with appropriate arms)
3. ✅ 28 subjects are created
4. ✅ Subject assignments match class levels and departments
5. ✅ 200 students are distributed across classes
6. ✅ 10 teachers are created
7. ✅ 20 parents are created with occupations and contact details
8. ✅ Teachers are assigned to subjects and classes
9. ✅ Assessment components are set up
10. ✅ Skill types are configured
11. ✅ 2 sessions with 3 terms each are created
12. ✅ Students are linked to parents (approximately 120 students have parents)

## Features

### Intelligent Subject Assignment
- Subjects are assigned based on class level (Nursery, Primary, JSS, SS)
- Department-specific subjects for SS classes:
  - **Science:** Physics, Chemistry, Biology, etc.
  - **Arts:** Literature, Government, History, etc.
  - **Commercial:** Economics, Commerce, Accounting, etc.

### Realistic Nigerian Context
- Nigerian names for students and teachers
- Nigerian states for student origin
- Common Nigerian subjects (Yoruba, Igbo, Hausa)
- Local education structure

### Teacher Assignments
- Each teacher is assigned to multiple subjects
- Each class has a class teacher
- Teachers have proper roles and permissions

### Student Distribution
- Students are evenly distributed across all classes
- Proper admission number generation
- Realistic age ranges based on class level
- Approximately 60% of students are linked to parents

### Parent Management
- 20 parents with realistic Nigerian names
- Each parent has occupation, address, and contact details
- Parents can have multiple children (siblings) in the school
- Parent accounts with login credentials (email/password)

## Troubleshooting

### Error: "Class not found"
Make sure you're in the correct directory:
```bash
cd /home/cloud/Videos/SCHOOL/school-be-php
```

### Error: "SQLSTATE[23000]: Integrity constraint violation"
The database might have existing data. Try:
```bash
php artisan migrate:fresh --seed
```

### Error: "Permission denied"
Run with appropriate permissions:
```bash
php artisan cache:clear
php artisan config:clear
php artisan migrate:fresh --seed
```

## Notes

- **Idempotent:** The seeder is fully idempotent - it can be run multiple times without creating duplicates
- **Multi-tenancy:** All data is properly scoped to the school with subdomain 'demo'
- **Transactions:** Uses transactions, so if any error occurs, all changes are rolled back
- **Admission numbers:** Automatically generated with proper sequencing
- **UUIDs:** All UUIDs are properly generated for foreign keys
- **Current session/term:** The school's current session and term are automatically set
- **Passwords:** Teacher, parent, and student passwords are set to 'password' for easy testing
- **Class arms:** All classes have at least one arm (Nursery/Primary use "Main" arm) to satisfy database constraints

## Customization

To modify the seeder:

1. Edit `/database/seeders/ComprehensiveSchoolSeeder.php`
2. Adjust arrays like `$nigerianFirstNames`, `$nigerianLastNames`, or subject lists
3. Change the number of students/teachers in the respective methods
4. Re-run: `php artisan migrate:fresh --seed`

## Support

If you encounter any issues:
1. Check the Laravel logs: `storage/logs/laravel.log`
2. Verify database connection in `.env`
3. Ensure all migrations are up to date: `php artisan migrate:status`
