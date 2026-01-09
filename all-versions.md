# v1.0 – School Onboarding & Admin Login
Description: Covers initial school registration, admin authentication, and viewing/updating the single-school profile and admin account.

## v1.0 User Stories
- Admin self-registers a school by providing school name, email, address, subdomain, and admin password; sees success banner and optional email verification prompt (`/register`).
- Admin logs in with email and password; invalid input shows friendly errors, next-redirect respected, dashboard/profile loaded on success, and session persists until logout (`/login`).
- Admin can trigger “Forgot Password?” from the login form after entering a valid email to request a reset link (`/login`).
- Admin views the school profile with logo/signature, name, address, email, phone, and current session/term; can refresh data (`/v10/profile`).
- Admin updates school profile (name, email, phone, address, session/term, logo, signature add/remove) and sees immediate reflection on profile page (`/v10/edit-school-profile`).
- Admin updates their own account details (name, email, password) with validation and friendly errors (`/v10/edit-admin-profile`).

## v1.0 API Documentation (Endpoints Only)
- POST /api/v1/register-school
- POST /api/v1/login
- POST /api/v1/password/forgot
- GET /api/v1/school
- PUT /api/v1/school
- PUT /api/v1/user
- POST /api/v1/logout

# v1.1 – Academic Sessions & Terms
Description: Focused on creating, listing, editing, and deleting academic sessions and their linked terms with validation and feedback.

## v1.1 User Stories
- Admin creates academic sessions with name, start date, end date, sees validation errors (e.g., overlap/required), and can list, edit, or delete sessions with success/failure alerts; delete respects backend constraints (`/v11/add-session`, `/v11/all-sessions`, `/v11/edit-session`).
- Admin creates academic terms linked to a session with name (1st/2nd/3rd), start date, end date, validates required fields and allowed names, lists terms per session, and edits/deletes with clear feedback; delete respects backend constraints (`/v11/add-term`, `/v11/all-terms`, `/v11/edit-term`).

## v1.1 API Documentation (Endpoints Only)
- GET /api/v1/sessions
- POST /api/v1/sessions
- PUT /api/v1/sessions/{id}
- DELETE /api/v1/sessions/{id}
- GET /api/v1/terms
- POST /api/v1/terms
- GET /api/v1/terms/{id}
- PUT /api/v1/terms/{id}
- DELETE /api/v1/terms/{id}
# v1.2 – Class Structure (Classes, Arms, Sections)
Description: Documents managing classes, class arms, and class sections—creating, editing, listing, and deleting with class/arm filters.

## v1.2 User Stories
- Admin manages classes: list all classes, create new classes, edit class names, and delete classes with feedback (`/v12/all-classes`, `/v12/add-class`, `/v12/edit-class`).
- Admin manages class arms: list/filter by class, create arms for a class, edit arm names, and delete arms with confirmations (`/v12/all-class-arms`, `/v12/add-class-arm`, `/v12/edit-class-arm`).
- Admin manages class sections: list sections filtered by class and arm, create sections for a class arm, edit section names, and delete sections with confirmations (`/v12/all-class-arm-sections`, `/v12/add-class-arm-section`, `/v12/edit-class-arm-section`).

## v1.2 API Documentation (Endpoints Only)
- GET /api/v1/classes
- POST /api/v1/classes
- GET /api/v1/classes/{id}
- PUT /api/v1/classes/{id}
- DELETE /api/v1/classes/{id}
- GET /api/v1/classes/{classId}/arms
- POST /api/v1/classes/{classId}/arms
- GET /api/v1/classes/{classId}/arms/{armId}
- PUT /api/v1/classes/{classId}/arms/{armId}
- DELETE /api/v1/classes/{classId}/arms/{armId}
- GET /api/v1/classes/{classId}/arms/{armId}/sections
- POST /api/v1/classes/{classId}/arms/{armId}/sections
- PUT /api/v1/classes/{classId}/arms/{armId}/sections/{sectionId}
- DELETE /api/v1/classes/{classId}/arms/{armId}/sections/{sectionId}
# v1.3 – Parent Management
Description: Details creating, searching, updating, and deleting parent records with validation and feedback.

## v1.3 User Stories
- Admin manages parents: list parents with search, view linked students count, and refresh results (`/v13/all-parents`).
- Admin creates parent records with required first/last name and phone, plus optional email, occupation, and address (`/v13/add-parent`).
- Admin edits existing parents and updates contact and profile details with validation and friendly errors (`/v13/edit-parent`).
- Admin deletes parent records with confirmation prompts and sees success/failure alerts (`/v13/all-parents`).

## v1.3 API Documentation (Endpoints Only)
- GET /api/v1/parents
- POST /api/v1/parents
- GET /api/v1/parents/{id}
- PUT /api/v1/parents/{id}
- DELETE /api/v1/parents/{id}
# v1.4 – Student Management & Skills
Description: Covers student CRUD, listings, skills/term comments, result PINs, result printing, and class skill ratings.

## v1.4 User Stories
- Admin manages students: add/edit detailed profiles (demographics, class/arm/section, session/term, parent, blood group, optional photo), with validation and error feedback (`/v14/add-student`, `/v14/edit-student`).
- Admin lists and filters students by session/class/arm/section, searches, sorts, paginates, and can view or edit from the table (`/v14/all-students`).
- Admin views a student profile with full details, deletes a student, and prints the student result for a chosen session/term (`/v14/student-details`).
- Admin records per-student skill ratings (1–5) per session/term, edits/deletes ratings, and uses available skill types; saves teacher/principal term comments for the selected context (`/v14/student-details`).
- Admin manages student result PINs for a selected session/term: generate/regenerate, invalidate, show, and view PIN status/history (`/v14/student-details`).
- Admin checks a student result by selecting session/term/class/arm/student and opens the printable slip (`/v14/check-result`).
- Admin bulk-prints class results for a selected session/term/class with optional autoprint flag (`/v14/bulk-results`).
- Admin bulk rates class skills: load students by session/term/class, pick a skill, enter ratings grid, and save all (`/v14/class-skill-ratings`).
- Admin uses dependent dropdowns (class → arm → section) for troubleshooting (`/v14/test-dropdown-debug`).

## v1.4 API Documentation (Endpoints Only)
- GET /api/v1/sessions
- GET /api/v1/terms
- GET /api/v1/terms?session_id={id}
- GET /api/v1/classes
- GET /api/v1/classes/{classId}/arms
- GET /api/v1/classes/{classId}/arms/{armId}/sections
- GET /api/v1/parents
- GET /api/v1/locations/countries
- GET /api/v1/locations/states?country_id={id}
- GET /api/v1/locations/states/{stateId}/lgas
- GET /api/v1/locations/blood-groups
- GET /api/v1/students
- POST /api/v1/students
- GET /api/v1/students/{id}
- PUT /api/v1/students/{id}
- DELETE /api/v1/students/{id}
- GET /api/v1/students/{id}/results/print
- GET /api/v1/results/bulk/print
- GET /api/v1/students/{id}/skill-types
- GET /api/v1/students/{id}/skill-ratings
- POST /api/v1/students/{id}/skill-ratings
- PUT /api/v1/students/{id}/skill-ratings/{ratingId}
- DELETE /api/v1/students/{id}/skill-ratings/{ratingId}
- GET /api/v1/students/{id}/term-summary
- PUT /api/v1/students/{id}/term-summary
- GET /api/v1/result-pins
- POST /api/v1/result-pins
- PUT /api/v1/result-pins/{id}
- DELETE /api/v1/result-pins/{id}
- GET /api/v1/settings/skill-types
# v1.5 – Staff Management & Staff Self-Service
Description: Captures staff CRUD for admins plus staff-facing dashboard and profile/password updates.

## v1.5 User Stories
- Admin manages staff: list/search/filter/sort staff records, with pagination and summaries (`/v15/all-staff`).
- Admin creates staff profiles with role, gender, contact info, optional employment start date, address, qualifications, and photo upload; sees success/error feedback (`/v15/add-staff`).
- Admin edits staff profiles, updates details and photo, and navigates back to the staff list (`/v15/edit-staff`).
- Admin deletes staff profiles with confirmation and respects permission checks (`/v15/all-staff`).
- Staff user views a dedicated dashboard showing current session/term, assigned classes/subjects, teacher profile summary, and quick links to manage profile (`/v25/staff-dashboard`).
- Staff user updates their own profile and password with validation and feedback (`/v25/profile`).

## v1.5 API Documentation (Endpoints Only)
- GET /api/v1/staff
- POST /api/v1/staff
- GET /api/v1/staff/{id}
- PUT /api/v1/staff/{id}
- DELETE /api/v1/staff/{id}
- GET /api/v1/staff/dashboard
- GET /api/v1/staff/me
# v1.6 – Subject Management
Description: Documents subject CRUD with listing, search, sorting, pagination, and feedback.

## v1.6 User Stories
- Admin manages subjects: list/search/sort/paginate subjects with code/description, and refresh results (`/v16/all-subjects`).
- Admin creates subjects with name, optional code, and description, with validation and feedback (`/v16/add-subject`).
- Admin edits existing subjects, updating name/code/description with validation and feedback (`/v16/edit-subject`).
- Admin deletes subjects with confirmation and clear success/failure alerts (`/v16/all-subjects`).

## v1.6 API Documentation (Endpoints Only)
- GET /api/v1/settings/subjects
- POST /api/v1/settings/subjects
- PUT /api/v1/settings/subjects/{id}
- DELETE /api/v1/settings/subjects/{id}
# v1.7 – Subject & Teacher Assignments
Description: Covers assigning subjects to classes and teachers to subjects/classes with filters, pagination, and CRUD.

## v1.7 User Stories
- Admin assigns subjects to classes (and optional arms/sections): select one or more subjects, pick class/arm, save assignments, see success/warning/error feedback, and reset the form (`/v17/assign-subjects`).
- Admin lists subject assignments with pagination and filters (search, class, arm), then edits or deletes assignments with confirmation and feedback (`/v17/assign-subjects`).
- Admin assigns teachers to subjects/classes/sessions/terms (optional arms), with validation, feedback, and reset; supports filtering by subject/teacher/class/session and editing/deleting assignments (`/v17/assign-teachers`).

## v1.7 API Documentation (Endpoints Only)
- GET /api/v1/settings/subjects
- GET /api/v1/classes
- GET /api/v1/classes/{classId}/arms
- GET /api/v1/classes/{classId}/arms/{armId}/sections
- GET /api/v1/settings/subject-assignments
- POST /api/v1/settings/subject-assignments
- PUT /api/v1/settings/subject-assignments/{id}
- DELETE /api/v1/settings/subject-assignments/{id}
- GET /api/v1/settings/subject-teacher-assignments
- POST /api/v1/settings/subject-teacher-assignments
- PUT /api/v1/settings/subject-teacher-assignments/{id}
- DELETE /api/v1/settings/subject-teacher-assignments/{id}
- GET /api/v1/staff
- GET /api/v1/sessions
- GET /api/v1/terms
# v1.8 – Class Teacher Assignments
Description: Documents assigning class teachers per class/arm/session/term with filtering, editing, deleting, and feedback.

## v1.8 User Stories
- Admin assigns class teachers to classes/arms (optional sections) for a selected session/term with validation and feedback; form reset available (`/v18/assign-class-teachers`).
- Admin lists class-teacher assignments with filters (search, teacher, class, arm, session), pagination, and can edit or delete assignments with confirmation and feedback (`/v18/assign-class-teachers`).

## v1.8 API Documentation (Endpoints Only)
- GET /api/v1/classes
- GET /api/v1/classes/{classId}/arms
- GET /api/v1/classes/{classId}/arms/{armId}/sections
- GET /api/v1/staff
- GET /api/v1/sessions
- GET /api/v1/terms
- GET /api/v1/settings/class-teachers
- POST /api/v1/settings/class-teachers
- PUT /api/v1/settings/class-teachers/{id}
- DELETE /api/v1/settings/class-teachers/{id}
# v1.9 – Results, PINs, Components, Grading & Skills
Description: Focuses on result PIN management, bulk result entry, assessment components, grading scales, and skill category/type management.

## v1.9 User Stories
- Admin manages result PINs: load PINs by session/term/class/arm/student, generate or regenerate single PINs, bulk-generate PINs for a class, set expiry/max usage, show PIN, invalidate PINs, and print scratch cards (`/v19/pins`).
- Admin performs result entry in bulk: load students by session/term/class/arm/section/subject/component, view existing scores, enter/update scores with validation against component weight, and save batch results with status feedback (`/v19/results-entry`).
- Admin manages assessment components: list/filter/paginate components, create/edit with name/score/order/label and subject associations, select/deselect subjects (including select all), and delete components (`/v19/assessment-components`).
- Admin manages grading scales: select an active scale, add/edit/delete grade ranges (label, min/max score, optional grade point, description) with row validation and save changes (`/v19/grade-scales`).
- Admin manages skills & behaviour: list skill categories and skills, create/edit/delete categories and skills (with optional weight/description), and refresh lists (`/v19/skills`).

## v1.9 API Documentation (Endpoints Only)
- GET /api/v1/sessions
- GET /api/v1/terms
- GET /api/v1/classes
- GET /api/v1/classes/{classId}/arms
- GET /api/v1/classes/{classId}/arms/{armId}/sections
- GET /api/v1/students
- GET /api/v1/students/{id}
- GET /api/v1/result-pins
- POST /api/v1/result-pins
- PUT /api/v1/result-pins/{id}
- DELETE /api/v1/result-pins/{id}
- GET /api/v1/result-pins/cards/print
- GET /api/v1/results
- POST /api/v1/results/batch
- GET /api/v1/settings/assessment-components
- POST /api/v1/settings/assessment-components
- GET /api/v1/settings/assessment-components/{id}
- PUT /api/v1/settings/assessment-components/{id}
- DELETE /api/v1/settings/assessment-components/{id}
- GET /api/v1/grades/scales
- PUT /api/v1/grades/scales/{id}
- GET /api/v1/settings/skill-categories
- POST /api/v1/settings/skill-categories
- PUT /api/v1/settings/skill-categories/{id}
- DELETE /api/v1/settings/skill-categories/{id}
- GET /api/v1/settings/skill-types
- POST /api/v1/settings/skill-types
- PUT /api/v1/settings/skill-types/{id}
- DELETE /api/v1/settings/skill-types/{id}
# v2.0 – Rollover, Promotions, Attendance, Bulk Upload, Fees, Roles
Description: Aggregates academic rollover, student promotion, attendance (dashboard and capture), bulk uploads, banking/fees, and role/permission management.

## v2.0 User Stories
- Admin runs academic rollover: select source session, define new session name/start/end, preview term dates, and create the new session with confirmation and feedback (`/v20/academic-rollover`).
- Admin promotes students in bulk: filter source session/term/class/arm, select students, choose target session/class/arm (optional retain subjects), execute promotion, and view success/skip summary (`/v20/student-promotion`).
- Admin views promotion history with filters (session/term/class), loads reports, and exports CSV/PDF (`/v20/promotion-reports`).
- Admin monitors attendance dashboard: runs reports over date range with class/department filters, sees student/staff status breakdowns and at-risk lists, and exports CSV/PDF (`/v21/attendance-dashboard`).
- Admin records student attendance by date/session/term/class/arm/section: loads students, sets status/comments, saves, deletes entries, and exports history (`/v21/student-attendance`).
- Admin records staff attendance by date/department/search: loads staff, sets status, saves, deletes entries, and exports history (`/v21/staff-attendance`).
- Admin performs bulk student upload: download CSV template, drag/drop CSV, validate preview with summary/errors, download error log, and commit batch creation (`/v22/bulk-student-upload`).
- Admin manages bank accounts: list/search, add/edit (bank, account, branch, default/active flags), delete accounts, and set default (`/v23/bank-details`).
- Admin manages fees: create/edit/delete fee items; define fee structures per session/term/class/item/amount/mandatory flag; copy structures across sessions/terms/classes; filter structures (`/v23/fee-structure`).
- Admin manages roles & permissions: list/create/edit/delete roles with grouped permissions and metadata (`/v24/roles`).
- Admin assigns roles to users: search users, filter by role, paginate, select user, choose roles in modal, and save updates with feedback (`/v24/user-roles`).

## v2.0 API Documentation (Endpoints Only)
- GET /api/v1/sessions
- GET /api/v1/terms
- GET /api/v1/classes
- GET /api/v1/classes/{classId}/arms
- GET /api/v1/classes/{classId}/arms/{armId}/sections
- GET /api/v1/students
- GET /api/v1/staff
- POST /api/v1/sessions/rollover
- POST /api/v1/promotions/bulk
- GET /api/v1/promotions/history
- GET /api/v1/attendance/students
- POST /api/v1/attendance/students
- DELETE /api/v1/attendance/students/{id}
- GET /api/v1/attendance/staff
- POST /api/v1/attendance/staff
- DELETE /api/v1/attendance/staff/{id}
- GET /api/v1/students/bulk/template
- POST /api/v1/students/bulk/preview
- POST /api/v1/students/bulk
- GET /api/v1/fees/bank-details
- POST /api/v1/fees/bank-details
- PUT /api/v1/fees/bank-details/{id}
- DELETE /api/v1/fees/bank-details/{id}
- POST /api/v1/fees/bank-details/{id}/default
- GET /api/v1/fees/items
- POST /api/v1/fees/items
- PUT /api/v1/fees/items/{id}
- DELETE /api/v1/fees/items/{id}
- GET /api/v1/fees/structures
- POST /api/v1/fees/structures
- PUT /api/v1/fees/structures/{id}
- DELETE /api/v1/fees/structures/{id}
- POST /api/v1/fees/structures/copy
- GET /api/v1/fees/structures/by-session-term
- GET /api/v1/roles
- POST /api/v1/roles
- PUT /api/v1/roles/{id}
- DELETE /api/v1/roles/{id}
- GET /api/v1/permissions
- GET /api/v1/permissions/hierarchy
- GET /api/v1/users
- PUT /api/v1/users/{id}/roles
# v2.1 – No New Scope
Description: No additional features or endpoints beyond v2.0 in the provided code scope.

## v2.1 User Stories
- No additional features beyond v2.0 are present in the provided code scope.

## v2.1 API Documentation (Endpoints Only)
- No new endpoints beyond v2.0 are used in the provided code scope.
# v2.2 – No New Scope
Description: No additional features or endpoints beyond v2.0–v2.1 in the provided code scope.

## v2.2 User Stories
- No additional features beyond v2.0–v2.1 are present in the provided code scope.

## v2.2 API Documentation (Endpoints Only)
- No new endpoints beyond v2.0–v2.1 are used in the provided code scope.
# v2.3 – No New Scope
Description: No additional features or endpoints beyond v2.0–v2.2 in the provided code scope.

## v2.3 User Stories
- No additional features beyond v2.0–v2.2 are present in the provided code scope.

## v2.3 API Documentation (Endpoints Only)
- No new endpoints beyond v2.0–v2.2 are used in the provided code scope.
# v2.4 – No New Scope
Description: No additional features or endpoints beyond v2.0–v2.3 in the provided code scope.

## v2.4 User Stories
- No additional features beyond v2.0–v2.3 are present in the provided code scope.

## v2.4 API Documentation (Endpoints Only)
- No new endpoints beyond v2.0–v2.3 are used in the provided code scope.
# v2.5 – No New Scope
Description: No additional features or endpoints beyond v2.0–v2.4 in the provided code scope.

## v2.5 User Stories
- No additional features beyond v2.0–v2.4 are present in the provided code scope.

## v2.5 API Documentation (Endpoints Only)
- No new endpoints beyond v2.0–v2.4 are used in the provided code scope.
# v2.6 – Student Portal Login & Result Download
Description: Covers student login, session handling, profile fetch, and printable result download with user-friendly errors.

## v2.6 User Stories
- Student signs in to the result portal with admission number and password, sees validation/errors, and can use demo credentials when enabled (`/student-login`).
- Student session token is stored for subsequent requests; logout clears the student session (`/student-login`, student auth utilities).
- Student downloads printable result by session/term via the student download route, with clear error pages for missing filters, permission issues, or unavailable results (`/student/print-result/route.ts`).
- Student profile can be fetched after login for portal context (school, class, parent, subjects) via student auth utilities.

## v2.6 API Documentation (Endpoints Only)
- POST /api/v1/student/login
- POST /api/v1/student/logout
- GET /api/v1/student/profile
- GET /api/v1/student/results/download
