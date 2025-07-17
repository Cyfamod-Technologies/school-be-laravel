# Model Documentation

This document provides a detailed overview of the database schema and the corresponding Eloquent models used in this application.

## Database Schema

The following tables are present in the database:

*   **analytics_data**: Stores aggregated analytics data for classes and subjects.
*   **api_keys**: Stores API keys for external integrations.
*   **assessment_components**: Stores assessment components for a school.
*   **attendances**: Stores attendance records for students.
*   **audit_logs**: Stores audit logs for user activity.
*   **class_arms**: Stores the arms of a class.
*   **class_sections**: Stores the sections of a class arm.
*   **class_teachers**: Stores the teachers assigned to a class section.
*   **classes**: Stores the classes in a school.
*   **fee_payments**: Stores fee payment records for students.
*   **grade_ranges**: Stores the grade ranges for a grading scale.
*   **grading_scales**: Stores the grading scales for a school.
*   **message_threads**: Stores message threads between users.
*   **messages**: Stores messages in a thread.
*   **parents**: Stores parent information.
*   **performance_reports**: Stores performance reports for students.
*   **permissions**: Stores permissions for roles.
*   **result_pins**: Stores result pins for students.
*   **results**: Stores the results of students in subjects.
*   **role_has_permissions**: Stores the permissions assigned to a role.
*   **roles**: Stores the roles in the system.
*   **school_skill_types**: Stores the skill types for a school.
*   **school_user_assignments**: Stores the users assigned to a school.
*   **schools**: Stores the schools in the system.
*   **sessions**: Stores the academic sessions in a school.
*   **skill_categories**: Stores the skill categories for a school.
*   **skill_ratings**: Stores the skill ratings for a student.
*   **skill_types**: Stores the skill types for a school.
*   **staff**: Stores staff information.
*   **student_enrollments**: Stores the enrollment of students in a class section.
*   **students**: Stores student information.
*   **subject_class_assignments**: Stores the subjects assigned to a class.
*   **subject_teacher_assignments**: Stores the teachers assigned to a subject in a class section.
*   **subjects**: Stores the subjects in a school.
*   **term_summaries**: Stores the term summaries for students.
*   **terms**: Stores the academic terms in a session.
*   **users**: Stores the users of the system.

## Eloquent Models

The following Eloquent models are available in the `app/Models` directory:

*   **AnalyticsData**: Corresponds to the `analytics_data` table.
*   **ApiKey**: Corresponds to the `api_keys` table.
*   **AssessmentComponent**: Corresponds to the `assessment_components` table.
*   **Attendance**: Corresponds to the `attendances` table.
*   **AuditLog**: Corresponds to the `audit_logs` table.
*   **ClassArm**: Corresponds to the `class_arms` table.
*   **ClassSection**: Corresponds to the `class_sections` table.
*   **ClassTeacher**: Corresponds to the `class_teachers` table.
*   **Classes**: Corresponds to the `classes` table.
*   **FeePayment**: Corresponds to the `fee_payments` table.
*   **GradeRange**: Corresponds to the `grade_ranges` table.
*   **GradingScale**: Corresponds to the `grading_scales` table.
*   **MessageThread**: Corresponds to the `message_threads` table.
*   **Message**: Corresponds to the `messages` table.
*   **Parents**: Corresponds to the `parents` table.
*   **PerformanceReport**: Corresponds to the `performance_reports` table.
*   **Permission**: Corresponds to the `permissions` table.
*   **ResultPin**: Corresponds to the `result_pins` table.
*   **Result**: Corresponds to the `results` table.
*   **Role**: Corresponds to the `roles` table.
*   **SchoolSkillType**: Corresponds to the `school_skill_types` table.
*   **SchoolUserAssignment**: Corresponds to the `school_user_assignments` table.
*   **School**: Corresponds to the `schools` table.
*   **Session**: Corresponds to the `sessions` table.
*   **SkillCategory**: Corresponds to the `skill_categories` table.
*   **SkillRating**: Corresponds to the `skill_ratings` table.
*   **SkillType**: Corresponds to the `skill_types` table.
*   **Staff**: Corresponds to the `staff` table.
*   **StudentEnrollment**: Corresponds to the `student_enrollments` table.
*   **Student**: Corresponds to the `students` table.
*   **SubjectClassAssignment**: Corresponds to the `subject_class_assignments` table.
*   **SubjectTeacherAssignment**: Corresponds to the `subject_teacher_assignments` table.
*   **Subject**: Corresponds to the `subjects` table.
*   **TermSummary**: Corresponds to the `term_summaries` table.
*   **Term**: Corresponds to the `terms` table.
*   **User**: Corresponds to the `users` table.


