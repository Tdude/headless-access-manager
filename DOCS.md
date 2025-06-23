# Headless Access Manager – Documentation

## Overview
Headless Access Manager (HAM) is a WordPress plugin designed to serve as a backend for a Next.js frontend. It manages custom user roles, role-based permissions, custom data structures, JWT authentication, and provides rich REST API endpoints for data access.

## Features
- **Custom User Roles**:
  - Student
  - Teacher
  - Principal
  - School Head
- **Custom Data Types**:
 - Assessments
  - Schools
  - Classes
  - Students
  - Teachers
  - Principals
  - School Heads
- **JWT Authentication**:
  - Secure token-based authentication
  - Configurable token expiration
- **Rich REST API**:
  - User management endpoints
  - Data access endpoints
  - Statistics and reporting endpoints
- **Role-Based Permissions**:
  - Granular access control
  - Hierarchical permission inheritance
- **Admin Interface**:
  - Dashboard with system overview
  - Intuitive user management
  - School and class administration

## Installation
1. **Upload Plugin Files**: Upload the plugin files to the `/wp-content/plugins/headless-access-manager` directory.
2. **Install via WordPress**: Alternatively, install the plugin through the WordPress plugins screen.
3. **Activate Plugin**: Activate the plugin through the 'Plugins' screen in WordPress.
4. **Configure Settings**: Configure the plugin settings under the 'HAM' menu in the WordPress admin.
5. **Setup Data Structure**: Set up your schools, classes, and users as needed.

## Usage
### Setting Up Data Structure
1. **Create Schools**: Create schools via HAM > Schools.
2. **Create Classes**: Create classes and assign them to schools via HAM > Classes.
3. **Create Users**: Create users with appropriate roles (Student, Teacher, Principal, School Head).
4. **Assign Users**: Assign users to schools and classes via user profile settings.

### API Endpoints
The plugin creates multiple REST API endpoints:
- **Authentication**: `/wp-json/ham/v1/auth/token`
- **Users**: `/wp-json/ham/v1/users`
- **Schools**: `/wp-json/ham/v1/schools`
- **Classes**: `/wp-json/ham/v1/classes`
- **Assessments**: `/wp-json/ham/v1/assessments`
- **Statistics**: `/wp-json/ham/v1/stats/*`

### Frontend Integration
1. **Obtain JWT Token**: Use the authentication endpoint to obtain a JWT token.
2. **Include Token in Requests**: Include the token in the Authorization header for subsequent requests.
3. **Access Data**: Access data using appropriate endpoints based on user permissions.

## API Documentation
### Authentication
- `POST /wp-json/ham/v1/auth/token` - Get JWT token with username/password
- `GET /wp-json/ham/v1/auth/validate` - Validate JWT token

### Users
- `GET /wp-json/ham/v1/users/me` - Get current user info
- `GET /wp-json/ham/v1/users` - List users (with filtering options)
- `GET /wp-json/ham/v1/users/{id}` - Get single user
- `POST /wp-json/ham/v1/users` - Create user
- `PUT /wp-json/ham/v1/users/{id}` - Update user

### Data
- `GET /wp-json/ham/v1/schools` - List schools
- `GET /wp-json/ham/v1/schools/{id}` - Get single school
- `GET /wp-json/ham/v1/classes` - List classes
- `GET /wp-json/ham/v1/classes/{id}` - Get single class

### Assessments
- `GET /wp-json/ham/v1/assessments` - List assessments (with filtering)
- `GET /wp-json/ham/v1/assessments/{id}` - Get single assessment
- `POST /wp-json/ham/v1/assessments` - Create assessment
- `PUT /wp-json/ham/v1/assessments/{id}` - Update assessment

### Statistics
- `GET /wp-json/ham/v1/stats/student/{id}/progress` - Student progress stats
- `GET /wp-json/ham/v1/stats/class/{id}` - Class statistics
- `GET /wp-json/ham/v1/stats/teacher/{id}` - Teacher statistics
- `GET /wp-json/ham/v1/stats/school/{id}` - School statistics
- `GET /wp-json/ham/v1/stats/schools` - Multi-school statistics

## FAQ
### Does this plugin handle frontend rendering?
No, this plugin is designed as a backend for a headless WordPress site. It provides API endpoints that can be consumed by a frontend application (like Next.js).

### How is authentication handled?
Authentication uses JWT (JSON Web Tokens). Users authenticate with username/password to get a token, which is then included in subsequent API requests.

### Can I extend the plugin with custom roles or data types?
Yaa, the plugin is built to be extensible. You can add custom roles or modify data structures by extending the plugin's classes or using WordPress hooks.

### Is the plugin GDPR compliant?
The plugin stores only the data necessary for its functionality. Personal data handling should comply with your organization's GDPR policies.

## Changelog
### 1.0.0
- **Initial Release**

## Upgrade Notice
### 1.0.0
- No upgrade notes yet.





## Class CPT Meta Boxes

### Files
- `inc/admin/meta-boxes/class-ham-class-meta-boxes.php`: Handles all meta boxes for the Class CPT.

### Meta Boxes
1. **School**: Assigns the class to a school.
2. **Students**: Searchable, multi-select field for assigning students.

### Data Storage
- `_ham_school_id`: (int) School post ID.
- `_ham_student_ids`: (int[]) Array of student post IDs.

### AJAX Endpoints
- `wp_ajax_ham_search_students`: Returns matching students for Select2.
