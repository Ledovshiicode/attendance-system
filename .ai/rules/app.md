---
paths:
  - 'app/**'
---

# App

## Use Oman-local time for attendance
Attendance business logic, dashboard classification, demo attendance data, and attendance UI displays must use config('app.timezone'), which is Asia/Muscat for Oman. Do not hard-code UTC for attendance calculations; normalize explicit Carbon inputs to the application timezone before deriving work_date or applying the 05:00-21:00 window.
