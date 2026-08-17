# Employee Attendance & Leave Management System

A Laravel + Filament internal HR system for employee attendance tracking, working-hour calculations, leave management, and workforce statistics.

## Technology

- Laravel 13
- PHP 8.5
- Filament 5
- Livewire
- MySQL 8
- Laravel Sail
- Tailwind CSS / Vite

## Core Features

### Admin Panel

- Employee management
- Employee activation/deactivation
- Daily attendance monitoring
- Manual attendance entry with audit information
- Multiple attendance sessions per employee/day
- Employee attendance history
- Leave request review
- Approve / Reject leave requests
- Employee leave balances
- Attendance statistics
- Weekly/monthly analytics
- Workforce state dashboard
- Pending leave requests
- Employee profile views

### Employee Panel

- Independent employee authentication
- Check In
- Check Out
- Multiple daily attendance sessions
- Live worked-time calculation
- Remaining daily hours
- Attendance history
- Weekly/monthly working-hour summaries
- Leave balance
- Leave request submission
- Leave request history

## Attendance Rules

Required working time is 7 hours per day, stored internally as 25,200 seconds.

The attendance counting window is 05:00 AM to 09:00 PM in the application business timezone, Asia/Muscat. Only time inside this window contributes to worked time.

Example:

```text
Session: 03:00 -> 08:00
Counted: 05:00 -> 08:00
Total: 3 hours
```

Multiple sessions are summed.

```text
08:00 -> 12:00 = 4h
13:00 -> 16:00 = 3h
Total = 7h
```

Attendance statuses for counted working time:

- Below Required: less than 7 hours
- Completed: exactly 7 hours
- Above Required: more than 7 hours

Open sessions contribute dynamically until checkout or the 21:00 counting limit, whichever comes first.

## Work States

Work State is separate from Attendance Status.

- Working: employee has an open attendance session.
- Checked Out: employee has counted attendance today and no open session.
- Not Checked In Yet: today is still before 21:00 and employee has no counted attendance.
- Absent: work day has ended, or date is historical, with no counted attendance and no approved leave.
- On Leave: approved leave covers the date.

Attendance presence requires counted working time greater than 0. A session entirely outside 05:00-21:00 does not count as attendance presence.

## Manual Attendance

Admin/HR users can add manual attendance for correction purposes.

Manual attendance requires:

- Employee
- Date
- Check-in time
- Check-out time
- Reason

Manual attendance uses the same `AttendanceCalculator` as employee check-in/check-out. Admin users cannot manually choose worked hours, counted seconds, or attendance status.

Manual attendance records keep audit data:

- source
- created_by
- reason/note

Overlapping attendance sessions are rejected. Adjacent sessions are allowed when one session ends exactly when another begins.

## Leave Logic

The annual leave allowance is 35 days.

Leave types include:

- Annual
- Sick
- Emergency

Pending leave requests do not deduct balance. Rejected leave requests do not deduct balance. Approved leave requests deduct annual balance only when the selected `LeaveType` is configured to deduct annual balance.

Remaining leave is derived as:

```text
remaining_days = annual_allowance - used_days
```

Leave duration uses inclusive calendar days.

```text
20 Aug -> 24 Aug = 5 days
```

The leave workflow includes overlap protection and transaction/locking protection against double approval or duplicate balance deduction.

## Architecture

Core models:

- `User`
- `Employee`
- `AttendanceSession`
- `LeaveType`
- `LeaveBalance`
- `LeaveRequest`

Important services:

- `AttendanceCalculator`
- `AttendanceService`
- `LeaveService`
- `AdminDashboardService`

High-level flow:

```text
Filament UI
    -> Application Services
    -> Attendance / Leave Business Logic
    -> Eloquent Models
    -> MySQL
```

Daily attendance totals and statuses are derived from attendance sessions rather than stored as separate daily attendance records.

## Authentication and Security

The application has two Filament panels:

- Admin: `/admin`
- Employee: `/employee`

The panels use independent session guards, so an Admin and Employee can remain logged in simultaneously in the same browser.

Security controls include:

- `admin` guard
- `employee` guard
- `User::canAccessPanel()` role and employee-state checks
- Laravel policies
- Employee query scoping
- Employees can only access their own attendance and leave records
- Inactive employees cannot access the employee panel
- Historical records are retained when employees are deactivated

## Installation

Requirements:

- Docker Desktop, or Docker Engine on Linux
- WSL2 is recommended for Windows users

Clone and set up the project:

```bash
git clone <repository-url>
cd attendance-system
cp .env.example .env
```

If `vendor/` does not exist yet, install Composer dependencies using Docker:

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php85-composer:latest \
    composer install --ignore-platform-reqs
```

Start Sail and initialize the application:

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate:fresh --seed
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
```

Application URLs:

- Application: http://localhost:8080
- Admin panel: http://localhost:8080/admin
- Employee panel: http://localhost:8080/employee

## Demo Accounts

These credentials are for demo/testing purposes only.

### Admin

- URL: http://localhost:8080/admin
- Email: `admin@example.com`
- Password: `password`

### Employee

- URL: http://localhost:8080/employee
- Email: `employee@example.com`
- Password: `password`

## Demo Data

The command below creates realistic demo data:

```bash
./vendor/bin/sail artisan migrate:fresh --seed
```

Seeded data includes:

- Employees from multiple departments
- Attendance history
- Multiple attendance sessions
- Completed days
- Below-required days
- Above-required days
- Currently working employee
- Absences
- Leave requests
- Leave balances
- Approved / pending / rejected leave

Dashboards and charts are populated immediately after seeding.

## Project Structure

```text
app/
├── Enums/       Domain enum values for roles, attendance, leave, source, and work state
├── Filament/    Admin and employee panel resources, pages, and widgets
├── Models/      Eloquent models and relationships
├── Policies/    Authorization policies
└── Services/    Attendance, leave, and dashboard business logic

config/
└── attendance.php  Required seconds and counting window configuration

database/
├── migrations/  Database schema
└── seeders/     Demo data and leave types

tests/
├── Feature/     HTTP, Filament, service, auth, and workflow tests
└── Unit/        Attendance calculation tests
```

## Testing and Build

Run tests:

```bash
./vendor/bin/sail artisan test
```

Run code formatting:

```bash
./vendor/bin/sail pint
```

Build frontend assets:

```bash
./vendor/bin/sail npm run build
```

Final verification at delivery time passed with 115 tests and 284 assertions.

## Important Technical Decisions

- Integer seconds are used internally instead of floating-point hours.
- Attendance window calculations are centralized in `AttendanceCalculator`.
- Multiple attendance sessions per employee/day are supported.
- An open session is represented by `check_out_at = NULL`.
- Daily totals and statuses are derived from sessions.
- Leave remaining balance is derived from allowance and used days.
- Employees are deactivated with `is_active` rather than destructively deleted.
- Database transactions and `lockForUpdate()` protect critical workflows.
- Manual attendance entries are auditable.
- Asia/Muscat is the business timezone.
