# App Info (User Guide)

This file describes how to use the School Management System from the UI: where to go and how to complete common tasks. It intentionally excludes architecture, code details, backend logic, or database design.

## Base URLs (replace with your environment)
- Frontend dashboard: {FRONTEND_BASE_URL} (example: http://localhost:3000)
- Student portal: {FRONTEND_BASE_URL}/student-login

## Roles
- Admin: full access to setup, management, and reports.
- Staff/Teacher: access to teaching, attendance, results entry (as permitted).
- Student: access to student dashboard, results, CBT (as permitted).

## Sign in and onboarding
Steps:
1. Register a school (if enabled): go to `/register`, fill school and admin details, submit.
2. Admin login: go to `/login`, enter email and password.
3. Forgot password: on `/login`, click “Forgot Password?” and follow the prompt.
4. Student login: go to `/student-login`, enter admission number and password.
5. Demo mode: when enabled, demo login buttons appear on login screens.

## Core navigation by task

### Dashboard and profile
- Dashboard overview: `/v10/dashboard`
- School profile: `/v10/profile`
- Edit school profile: `/v10/edit-school-profile`
- Edit admin profile: `/v10/edit-admin-profile`

### Academic setup
- Sessions: `/v11/all-sessions`, `/v11/add-session`, `/v11/edit-session`
- Terms: `/v11/all-terms`, `/v11/add-term`, `/v11/edit-term`
- Classes: `/v12/all-classes`, `/v12/add-class`, `/v12/edit-class`
- Class arms: `/v12/all-class-arms`, `/v12/add-class-arm`, `/v12/edit-class-arm`
- Class sections: `/v12/all-class-arm-sections`, `/v12/add-class-arm-section`, `/v12/edit-class-arm-section`

### Parents and students
- Parents list/manage: `/v13/all-parents`, `/v13/add-parent`, `/v13/edit-parent`
- Students list/manage: `/v14/all-students`, `/v14/add-student`, `/v14/edit-student`
- Student details and actions: `/v14/student-details`
- Check a student result: `/v14/check-result`
- Bulk print results: `/v14/bulk-results`
- Class skill ratings: `/v14/class-skill-ratings`

### Staff
- Staff list/manage: `/v15/all-staff`, `/v15/add-staff`, `/v15/edit-staff`
- Staff dashboard: `/v25/staff-dashboard`
- Staff profile: `/v25/profile`

### Subjects and assignments
- Subjects: `/v16/all-subjects`, `/v16/add-subject`, `/v16/edit-subject`
- Assign subjects to classes: `/v17/assign-subjects`
- Assign teachers to subjects/classes: `/v17/assign-teachers`
- Assign class teachers: `/v18/assign-class-teachers`

### Results, grading, and skills
- Result PINs: `/v19/pins`
- Results entry (bulk): `/v19/results-entry`
- Assessment components: `/v19/assessment-components`
- Grade scales: `/v19/grade-scales`
- Skills and behaviour: `/v19/skills`

### Academic rollover and promotions
- Academic rollover: `/v20/academic-rollover`
- Student promotion: `/v20/student-promotion`
- Promotion reports: `/v20/promotion-reports`

### Attendance
- Attendance dashboard: `/v21/attendance-dashboard`
- Student attendance capture: `/v21/student-attendance`
- Staff attendance capture: `/v21/staff-attendance`

### Bulk upload
- Bulk student upload: `/v22/bulk-student-upload`

### Finance (if enabled)
- Bank details: `/v23/bank-details`
- Fee structure: `/v23/fee-structure`

### Roles and permissions
- Roles: `/v24/roles`
- User role assignments: `/v24/user-roles`

### Student dashboard and results
- Student dashboard: `/v26/student-dashboard`
- Student bio data: `/v26/student-dashboard/bio-data`
- Student results: `/v26/student-dashboard/my-result`
- Student result portal login: `/student-login`

### CBT (Computer Based Test)
Student side:
- Available quizzes: `/v27/cbt`
- Take quiz: `/v27/cbt/[quizId]/take`
- Quiz history: `/v27/cbt/history`
- Quiz result: `/v27/cbt/results/[attemptId]`

Admin side:
- Manage quizzes: `/v27/cbt/admin`
- Create quiz: `/v27/cbt/admin/create`
- Edit quiz: `/v27/cbt/admin/[quizId]/edit`
- Questions: `/v27/cbt/admin/[quizId]/questions`
- Results list: `/v27/cbt/admin/[quizId]/results`
- Attempt detail: `/v27/cbt/admin/[quizId]/results/[attemptId]`
- Link CBT to components: `/v27/cbt/admin/cbt-link`

## How to do common tasks


### Update school profile
1. Go to `/v10/profile` to view current details.
2. Go to `/v10/edit-school-profile`.
3. Update fields (including logo/signature if needed) and save.

### Update admin profile
1. Go to `/v10/edit-admin-profile`.
2. Update name, email, or password and save.

### Create a new session
1. Go to `/v11/add-session`.
2. Enter session name, start date, and end date.
3. Save and confirm in `/v11/all-sessions`.

### Edit or delete a session
1. Go to `/v11/all-sessions`.
2. Use the list to edit or delete a session.

### Create a new term
1. Go to `/v11/add-term`.
2. Select the session, enter term name and dates.
3. Save and confirm in `/v11/all-terms`.

### Edit or delete a term
1. Go to `/v11/all-terms`.
2. Use the list to edit or delete a term.

### Create a class
1. Go to `/v12/add-class`.
2. Enter the class name and save.
3. Confirm in `/v12/all-classes`.

### Create a class arm
1. Go to `/v12/add-class-arm`.
2. Select the class and enter arm name.
3. Save and confirm in `/v12/all-class-arms`.

### Add a parent
1. Go to `/v13/add-parent`.
2. Enter required fields (name, phone) and optional contact details.
3. Save.

### Edit or delete a parent
1. Go to `/v13/all-parents`.
2. Search and open the parent.
3. Edit or delete from the list/details.

### Add a new student
1. Go to `/v14/add-student`.
2. Fill bio data, class/arm/section, session/term, and parent info.
3. (Optional) upload a photo.
4. Save. Use `/v14/all-students` to confirm.

### Edit a student
1. Go to `/v14/all-students`.
2. Search or filter, then open the student.
3. Choose Edit and update fields.
4. Save changes.

### View student details and print a result
1. Go to `/v14/student-details`.
2. Select a student to view full details.
3. Choose session/term and print result if available.

### Record skill ratings and comments
1. Go to `/v14/student-details`.
2. Select session/term, enter skill ratings and comments.
3. Save changes.

### Check a student result (admin)
1. Go to `/v14/check-result`.
2. Select session, term, class/arm, and student.
3. Open the printable result.

### Bulk print results
1. Go to `/v14/bulk-results`.
2. Select session/term/class and print.

### Class skill ratings (bulk)
1. Go to `/v14/class-skill-ratings`.
2. Select session/term/class and skill.
3. Enter ratings and save.

### Create a class structure (class -> arm )
1. Create a class at `/v12/add-class`.
2. Create an arm at `/v12/add-class-arm`.

### Add a staff member
1. Go to `/v15/add-staff`.
2. Fill staff profile and role details.
3. Save and confirm in `/v15/all-staff`.

### Edit or delete staff
1. Go to `/v15/all-staff`.
2. Open a staff record and edit or delete.

### Staff self-service profile
1. Go to `/v25/profile`.
2. Update profile or password and save.

### Add a subject
1. Go to `/v16/add-subject`.
2. Enter name (and optional code/description).
3. Save and confirm in `/v16/all-subjects`.

### Edit or delete a subject
1. Go to `/v16/all-subjects`.
2. Use the list to edit or delete.

### Assign subjects and teachers
1. Assign subjects to classes at `/v17/assign-subjects`.
2. Assign teachers to subjects/classes at `/v17/assign-teachers`.
3. Assign class teachers at `/v18/assign-class-teachers`.

### Manage assessment components
1. Go to `/v19/assessment-components`.
2. Create or edit components and link them to subjects.
3. Save changes.

### Manage grade scales
1. Go to `/v19/grade-scales`.
2. Add or edit grade ranges and save.

### Manage skills and behaviour
1. Go to `/v19/skills`.
2. Create/edit skill categories and skills.
3. Save changes.

### Enter results (bulk)
1. Go to `/v19/results-entry`.
2. Select session, term, class, arm/section, subject, and component.
3. Enter scores and save.

### Manage result PINs
1. Go to `/v19/pins`.
2. Filter by session/term/class/student.
3. Generate/regenerate PINs, set limits/expiry, print scratch cards if needed.

### Run academic rollover
1. Go to `/v20/academic-rollover`.
2. Select source session and set the new session details.
3. Preview term dates, confirm, and create.

### Record attendance
1. Go to `/v21/student-attendance` or `/v21/staff-attendance`.
2. Choose date, session/term, and class/department.
3. Mark attendance and save.
4. Use `/v21/attendance-dashboard` for reports and exports.

### Promote students
1. Go to `/v20/student-promotion`.
2. Pick source session/term/class/arm and select students.
3. Choose target session/class/arm.
4. Execute promotion and review summary.

### View promotion reports
1. Go to `/v20/promotion-reports`.
2. Filter by session/term/class and export if needed.

### Bulk upload students
1. Go to `/v22/bulk-student-upload`.
2. Download the CSV template.
3. Upload your file and review the preview/errors.
4. Confirm to import.

### Fees setup
1. Add bank accounts at `/v23/bank-details`.
2. Define fee structure at `/v23/fee-structure`.

### Manage roles and permissions
1. Create or edit roles at `/v24/roles`.
2. Assign roles to users at `/v24/user-roles`.

### Student dashboard and results
1. Log in at `/student-login`.
2. Open `/v26/student-dashboard`.
3. View bio data at `/v26/student-dashboard/bio-data`.
4. Download results at `/v26/student-dashboard/my-result`.

### CBT admin flow (create and publish)
1. Go to `/v27/cbt/admin` and select Create.
2. Add quiz details and save.
3. Add questions at `/v27/cbt/admin/[quizId]/questions`.
4. Publish the quiz from the quiz list.

### CBT student flow (take a quiz)
1. Go to `/v27/cbt` to see available quizzes.
2. Select a quiz and start.
3. Submit and view results at `/v27/cbt/results/[attemptId]`.

## Notes
- If you cannot see a page or action, your role might not have permission.
- Deletions are typically restricted to admins.
- Routes with `[id]` mean a specific record ID will be in the URL.

## Step-by-step: Initial school setup (recommended order)
1. Create a new session: `/v11/add-session`. This defines the academic year range (start/end dates) used across all records.
2. Create a term for the session: `/v11/add-term`. Terms split the session (1st/2nd/3rd) and are required for results, attendance, and reports.
3. Update school settings/profile: `/v10/edit-school-profile`. Add logo/signature, verify school details, and select the active session/term if available.
4. Add subjects: `/v16/add-subject`. These are the subjects that will be assigned to classes and used for results entry.
5. Create classes, arms, and sections: `/v12/add-class`, `/v12/add-class-arm`, `/v12/add-class-arm-section`. This builds your class structure used for student placement and filtering.
6. Add staff/teachers: `/v15/add-staff`. Create staff profiles and roles so they can be assigned to classes and subjects.
7. Add parents: `/v13/add-parent`. Parent records link to students for contact and portal access.
8. Create assessment components: `/v19/assessment-components`. Set up components like CA1, CA2, Exams, define scores/weights, and link them to subjects.
9. Set up grade scales: `/v19/grade-scales`. Define grade ranges (A, B, etc.) and set the active scale for reports.
10. Add students: `/v14/add-student`. Capture bio data and assign class/arm/section, parent, and session/term.
11. Assign subjects to classes: `/v17/assign-subjects`. Choose which subjects are taught in each class (and arm/section if needed).
12. Assign teachers to subjects/classes: `/v17/assign-teachers`. Link teachers to the subjects they teach for each class/session/term.
13. Assign class teachers: `/v18/assign-class-teachers`. Set the main class teacher per class/arm for the selected session/term.
14. (Optional) Set up result PINs: `/v19/pins`. Generate student PINs for result access, set usage limits/expiry, and print cards if needed.
15. (Optional) Enter results: `/v19/results-entry`. Select class/subject/component and enter scores in bulk with validation.
16. (Optional) Start attendance tracking: `/v21/student-attendance` and `/v21/staff-attendance`. Mark daily attendance and use `/v21/attendance-dashboard` for reports.
17. (Optional) Configure fees: `/v23/bank-details`, `/v23/fee-structure`. Add bank accounts and define fee items/amounts per class/session/term.
18. (Optional) Create roles and permissions: `/v24/roles`, `/v24/user-roles`. Restrict access by role and assign users to roles.
19. (Optional) Verify student portal access: `/student-login`, `/v26/student-dashboard`. Confirm students can sign in and view their data/results.
20. (Optional) CBT setup: `/v27/cbt/admin`, `/v27/cbt/admin/[quizId]/questions`, `/v27/cbt/admin/cbt-link`. Create quizzes, add questions, and link CBT to assessment components.
21. Later workflows: promotions `/v20/student-promotion`, rollover `/v20/academic-rollover`, reports `/v20/promotion-reports`, attendance reports `/v21/attendance-dashboard`.
