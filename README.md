Database Schema & Initialization
schema.sql
 defines the 8 required tables.
db_init.php
 creates the database, table schema, and seeds default services, schedules, staff accounts, and a citizen profile.
Session Authentication & Security
Prepared statements for SQL injection prevention.
Password hashing (password_hash) and verification.
Role-based redirection from 
login_process.php
 and role checks at the top of each module.
register.php
 for citizen sign-up with client-side and server-side validation.
Core API
get_slots.php
 dynamically checks holiday dates, operating schedules per day of week, and calculates active staff capacity to return available 30-minute booking slots.
Citizen Module
citizen_dashboard.php
: Displays services grid with custom icons.
citizen_book.php
: Calendar & time-slot selector, required document checklist.
citizen_appointments.php
: Live booking log, reschedule, cancel operations. Recalculates token sequence if rescheduled to a new date.
citizen_queue.php
: Live public status dashboard, auto-refreshes every 15s. Calculates waiting customers ahead and estimated waiting time.
Staff (Officer) Module
staff_dashboard.php
: Displays today's queue for their assigned service. Prominent Active Serving card to Call Next, Complete, Recall, Skip, or mark Absent.
Admin Module
admin_dashboard.php
: Analytical indicators of daily queue counters.
admin_users.php
: Citizen/Staff registry with search, filter, and delete.
admin_staff.php
: CRUD registry to add officers, assign services, and set active status.
admin_services.php
: CRUD registry for services, token prefixes, icons.
admin_schedules.php
: Set operating hours per day and manage holidays.
admin_queue.php
: Admin control over all queue lines and walk-in customer addition.
Reports & Utilities
reports.php
: Filters daily, monthly, service-wise, and no-show metrics. Includes statistics grid and a CSV export utility.
print_token.php
: Clean printable receipt page for tokens.
Seeded Accounts for Testing
Role	Username	Password	Notes / Assigned Service
Administrator	admin	admin123	Full access to all controls, CRUD registries, and reports
Staff (Officer)	officer1	officer123	Assigned to Birth Certificate (BC) queue
Staff (Officer)	officer2	officer123	Assigned to NIC Services (NIC) queue
Citizen (Client)	minu	minu123	Pre-registered citizen account for testing booking flows
How to Verify the Flow
Verify Database Setup

You can run http://localhost/Office-Queue-and-Appointment-Management-System/db_init.php in your browser. This will confirm tables are in place and report seeded records. (We have already successfully executed this from the CLI).
Test Citizen Flow

Open http://localhost/Office-Queue-and-Appointment-Management-System/ in your browser. It will redirect you to login.php.
Log in as Citizen: Username minu, Password minu123, select "Citizen" in the User Type dropdown.
You will see the Welcome Page. Click Book an Appointment or select Birth Certificate from the services grid.
Pick a date, choose an available time slot, add notes, and click Confirm Appointment.
You will be redirected to My Appointments showing your booking and token (e.g. BC-001). Click Print to view the printable slip layout.
Test Staff (Officer) Flow

Log out and log back in as Officer: Username officer1, Password officer123, select "Officer".
You will see Today's Queue for the "Birth Certificate" service. The appointment you booked as minu will appear in the queue list.
Click Call Next Customer. The system updates status to "Called" and details will load into the active panel.
Click Complete or Absent to close the service ticket.
Test Admin Flow

Log in as Admin: Username admin, Password admin123, select "Admin".
View the Analytics cards.
Go to Queue Control. Add a walk-in customer for the "Birth Certificate" service. You will see a success message with a link to print their ticket.
Go to Reports. Select "Daily Report" and click Query. Review the results, then click Export to download the CSV sheet.
