# JobNexus - Premium Job Portal Application

A comprehensive job portal application built with core PHP, allowing job seekers to find opportunities and companies to hire top talent.

## 🚀 Tech Stack

*   **Backend**: PHP (Core/Vanilla - No Framework)
*   **Database**: MySQL
*   **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
*   **Architecture**: Custom MVC-like structure with Singleton Database pattern

## 📂 Project Structure

```
/
├── admin/          # Admin dashboard and management
├── api/            # API endpoints for AJAX requests
├── assets/         # Static assets (CSS, JS, Images)
├── auth/           # Authentication pages (Login, Register, Forgot Password)
├── classes/        # Core business logic and database models
│   ├── Database.php      # Singleton PDO wrapper
│   ├── User.php          # User management
│   ├── Job.php           # Job listing logic
│   ├── Company.php       # Company profile logic
│   └── ...
├── companies/      # Public company profiles
├── config/         # Configuration (DB credentials, constants)
├── database/       # SQL schemas and setup scripts
├── hr/             # HR/Recruiter dashboard
├── jobs/           # Job listing and search pages
├── seeker/         # Job seeker dashboard and profile
├── uploads/        # User uploaded content (Resumes, Logos)
├── index.php       # Landing page
└── notifications.php # User notifications
```

## 🌟 Key Features

*   **Role-Based Access Control**:
    *   **Seekers**: Search jobs, apply, manage profile/resume, track applications.
    *   **HR/Employers**: Post jobs, manage applications, schedule interviews, company profiles.
    *   **Admins**: Site management, verification, user oversight.
*   **Job Management**: Detailed listings with tagging, salary ranges, and categorizations.
*   **Application Tracking**: Full workflow from "Applied" to "Hired".
*   **Interview Scheduling**: Integrated calendar/event system for interviews.
*   **Notifications**: Real-time updates for application status changes.

## 🛠️ Setup & Installation

1.  **Configure Database**:
    *   Create a database named `jobnexus`.
    *   Import `database/db_schema.sql`.
2.  **Configure Application**:
    *   Edit `config/config.php`.
    *   Update `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME` if necessary.
    *   Update `BASE_URL` to match your local server path.
3.  **Run**:
    *   Serve via Apache/XAMPP or PHP built-in server: `php -S localhost:8000`
