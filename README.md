# Headless Access Manager – README 2.0

**IMPORTANT: Assessments and their API are PRODUCTION READY but need a form input for student and need to show logged in teacher (Associated to WP user).**

## Project Status
Updated Work Order: WP Admin UI First
Priority: Admin UI Consistency Before Frontend Changes
I completely agree with your prioritization. Here's the revised work order focusing on getting the WordPress Admin UI working correctly first:
Phase 1: Fix Admin UI (MUST COMPLETE FIRST)
1. Debug & Fix Assessment List View
    * Identify correct meta keys being used for student ID storage
    * Ensure student names display correctly by resolving to Student CPTs
    * Fix teacher name resolution using class-based associations
    * Verify display is consistent across all assessment entries
2. Debug & Fix Assessment Modal View
    * Ensure modal uses identical data retrieval logic as the list view
    * Verify student and teacher names display correctly and consistently
    * Confirm modal and list show identical information for the same assessment
3. Testing & Validation
    * Create test cases covering various assessment scenarios
    * Document expected vs. actual results in both list and modal views
    * Only proceed when both views consistently show correct information
Phase 2: Frontend/API Changes (ONLY AFTER PHASE 1)
1. Evaluate API Needs
    * With working Admin UI, analyze if API changes are actually needed
    * Document any API endpoints that require modification
2. Frontend Form Changes
    * Add student selection to frontend evaluation form
    * Ensure teacher name displays correctly
Key Success Criteria
* Admin list and modal must show identical, correct information
* Student names must come from Student CPTs, not WP users or form names
* Teacher names must use consistent resolution logic in both views
* All changes must be tracked in git with clear commit messages
This phased approach ensures we have a solid foundation in the Admin UI before tackling any frontend changes, which will save time and prevent cascading issues across the system.



## Before the current job, Project Status
- **Assessments, their handling, and API:** Finished and in production. Do not alter.
- **Custom Post Types (CPTs) for Schools and Classes:** Experimental, in-progress.
- **Relations and Admin Interface:** In progress, see below for requirements.

## What To Do
- Work ONLY on Schools, Classes, their relations, and the admin interface as described below.
- Follow the planned relationships and permissions model (see Admin Interface & Relations).
- Keep code modular and document all changes.

## What NOT To Do earlier
- **DO NOT** touch, refactor, or alter any code or documentation related to assessments or their API endpoints/logic.
- **DO NOT** change authentication or core user/role logic.
- **DO NOT** break REST API compatibility.

## Directory Structure (Key Parts)
- `README.md` – This file (contributor guidelines, project status, requirements)
- `DOCS.md` – Technical documentation and API docs (see below)
- `inc/` – Main PHP source code
  - `inc/api/` – API controllers
  - `inc/admin/` – Admin interface logic
  - `inc/constants.php` – Constants
  - `inc/assessment-constants.php` – Assessment constants (DO NOT MODIFY)
  - ...
- `assets/`, `languages/`, `logs/`, `templates/`, `tools/` – Supporting files and directories

## Admin Interface & CPT Relations (Requirements)
- **Dashboard:** System overview for admins
- **User Management:**
  - **Schools** (School Heads, managed by main WP admin):
    - School heads can see any school, principals, teachers, classes, students
  - **School** (Principals, managed by main WP admin or School Head):
    - Principals can see their own school, teachers, classes, students
  - **Teacher** (Principals, managed by main WP admin or Principal):
    - Teachers can see their own students, classes
  - **Class** (Principals, managed by main WP admin, Principal or Teacher):
    - Teachers can see their students. A class can have many teachers and teachers can have many classes (with students)
  - **Student** (Teachers, managed by main WP admin, Principal or Teacher):
    - Students can only see their own assessments

## Documentation
All technical and API documentation has been moved to `DOCS.md`.

---

For detailed API, installation, and usage documentation, see [DOCS.md](./DOCS.md).

- **Initial Release**
