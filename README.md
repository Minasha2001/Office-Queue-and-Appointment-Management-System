# Divisional Secretariat Queue & Appointment Management System

A secure, high-performance, and responsive Web Application built using **PHP, HTML5, CSS3, JavaScript (ES6), and MySQL**. The system is designed to modernize divisional secretariat operations by merging walk-in and online appointments into an automated, sequential queue, thereby reducing client waiting times and improving office productivity.

---

## 🌟 Key Features

### 1. Citizen (Client) Module
* **Registration & Login**: Secure, session-based authentication with password hashing.
* **Services Grid**: Elegant visual board displaying available office services with descriptive icons.
* **Appointment Booking**: Dynamic interactive form with real-time time-slot availability check (automatically locks fully booked slots).
* **Sequential Token Generation**: Automatically generates a unique service token (e.g., `BC-001` for Birth Certificate) upon confirmation.
* **My Bookings Dashboard**: View, cancel, or reschedule active bookings. Rescheduling to a new date automatically recalculates and updates the token sequence number.
* **Token Slip Printer**: Clean, print-friendly receipt layout showing ticket number, registered time, document checklist, and estimated waiting time.

### 2. Staff (Officer) Module
* **Queue Board**: Monitor today's real-time queue line for the officer's assigned service.
* **Queue Controller**: Servicing dashboard to call the next customer in sequence, mark tickets as completed, skipped, or absent, and recall tokens.

### 3. Admin Module
* **Analytical Dashboard**: Monitor daily indicators including total bookings, completed services, waiting queues, and absent/no-show clients.
* **Queue Control Center**: Oversee all service queues, call/skip tokens, and register walk-in customers on the fly.
* **User Accounts Manager**: View registered citizen and staff accounts, filter by role, and delete records.
* **Staff Registry**: Add officer accounts, assign them to services, and set their active status.
* **Service CRUD Manager**: Add, edit, or delete services, configure FontAwesome icons, and set custom token prefixes.
* **Schedule & Holiday Manager**: Define operational working hours and time-slot intervals per day of the week, and schedule office holidays.

### 4. Reporting Module
* **Daily & Monthly Reports**: Overview of queue traffic and service outcomes.
* **Service-wise & No-Show Logs**: Analytical reports on specific lines and absent clients.
* **CSV Export Utility**: Download any filtered report output as a standard spreadsheet file for offline storage.

### 5. Security & Queue Logic
* **SQL Injection Prevention**: All queries run via prepared statements (`mysqli_prepare`).
* **Role-Based Access Control (RBAC)**: Secure pages check user sessions before loading components.
* **Est. Waiting Time Calculation**: Calculates estimated waiting times dynamically:
  $$\text{Waiting Time} = \text{Avg Serving Time} \times \text{Number of waiting customers ahead in queue}$$

---

## 📂 Codebase File Structure

* `/` (Root directory)
  * [index.html](file:///c:/xampp/htdocs/Office-Queue-and-Appointment-Management-System/index.html) - Entry point redirecting users to the login screen.
  * [login.php](file:///c:/xampp/htdocs/Office-Queue-and-Appointment-Management-System/login.php) - Login form page with role selection.
  * [login_process.php](file:///c:/xampp/htdocs/Office-Queue-and-Appointment-Management-System/login_process.php) - Handles credential verification and session startup.
  * [register.php](file:///c:/xampp/htdocs/Office-Queue-and-Appointment-Management-System/register.php) - Citizen self-registration.
  * [logout.php](file:///c:/xampp/htdocs/Office-Queue-and-Appointment-Management-System/logout.php) - Securely terminates session.
  * [style.css](file:///c:/xampp/htdocs/Office-Queue-and-Appointment-Management-System/style.css) - Global premium design system and stylesheet.
  * [db.php](file:///c:/xampp/htdocs/Office-Queue-and-Appointment-Management-System/db.php) - Database connection configuration.
  * [schema.sql](file:///c:/xampp/htdocs/Office-Queue-and-Appointment-Management-System/schema.sql) - Database table structures and constraints.
  * [db_init.php](file:///c:/xampp/htdocs/Office-Queue-and-Appointment-Management-System/db_init.php) - Database installation script and table seeder.
  * [get_slots.php](file:///c:/xampp/htdocs/Office-Queue-and-Appointment-Management-System/get_slots.php) - API to check available time slots dynamically.
  * [print_token.php](file:///c:/xampp/htdocs/Office-Queue-and-Appointment-Management-System/print_token.php) - Print-friendly token receipt page.
  * [reports.php](file:///c:/xampp/htdocs/Office-Queue-and-Appointment-Management-System/reports.php) - Querying grid and CSV report generator.
  * `includes/` - Reusable dashboard components:
    * `citizen_sidebar.php` - Citizen menu navigation.
    * `staff_sidebar.php` - Officer menu navigation.
    * `admin_sidebar.php` - Admin menu navigation.
    * `header.php` - Dynamic top bar showing user name, initials avatar, and role.
  * `image/` - Graphic assets folder:
    * `gov.png` - State emblem logo.
    * `background.jpg` - Background photo of office facade.

---

## 🛠️ Installation & Setup

### Prerequisites
* **Local Web Server**: XAMPP / WampServer / MAMP with PHP (7.4 or higher) and MySQL Server.

### Steps
1. **Clone/Copy Project**: Place the project folder into your web server's root directory (e.g. `C:\xampp\htdocs\Office-Queue-and-Appointment-Management-System`).
2. **Start Servers**: Start Apache and MySQL modules from your XAMPP Control Panel.
3. **Initialize Database**:
   * Open your browser and navigate to: `http://localhost/Office-Queue-and-Appointment-Management-System/db_init.php`
   * This runs the database initialization script. It creates the database `office_queue_system`, sets up all 8 tables, and seeds initial data (services, schedules, staff, and admin accounts).
4. **Access Portal**: Open `http://localhost/Office-Queue-and-Appointment-Management-System/` to load the login page.

---

## 🔑 Demo Accounts for Verification

Use the following pre-seeded credentials to explore the roles:

| User Role | Username | Password | Assigned Queue / Notes |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin` | `admin123` | Control center, CRUD panels, stats, and reports |
| **Officer (Staff)** | `officer1` | `officer123` | Assigned to **Birth Certificate** (`BC`) queue |
| **Officer (Staff)** | `officer2` | `officer123` | Assigned to **NIC Services** (`NIC`) queue |
| **Citizen (Client)** | `minu` | `minu123` | Pre-registered citizen account for testing booking flows |

---

## 📊 Database Table Relations

* **`users`**: Root accounts table.
* **`services`**: Registry of office services, prefixes, and details.
* **`staff`**: Maps a staff `user_id` to an assigned `service_id` (foreign keys).
* **`schedules`**: Operating hours and time-slot configs per day of the week.
* **`holidays`**: Closed dates.
* **`appointments`**: Booked slots mapping citizen IDs, service IDs, dates, and times.
* **`queue_tokens`**: Active daily queue rows. Integrates both online check-ins and walk-in registrations chronologically by sequence token.
* **`activity_logs`**: Logs actions (bookings, cancellations, queue serving, configuration changes) for audit trails.
