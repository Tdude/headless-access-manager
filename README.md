# Headless Access Manager – README 2.0

**IMPORTANT: Assessments and their API are PRODUCTION READY. DO NOT MODIFY.**

## Project Status
- **Assessments, their handling, and API:** Finished and in production. Do not alter.
- **Custom Post Types (CPTs) for Schools and Classes:** Experimental, in-progress.
- **Relations and Admin Interface:** In progress, see below for requirements.

## What To Do
- Work ONLY on Schools, Classes, their relations, and the admin interface as described below.
- Follow the planned relationships and permissions model (see Admin Interface & Relations).
- Keep code modular and document all changes.

## What NOT To Do
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
