# SRMSS – Smart Route Management and Scheduling System

A web system for public transport depots in Sri Lanka. It replaces paper and spreadsheet route sheets with one dashboard for route planning, clash-free timetables, live trip tracking, fuel and maintenance logs, and management reports.

Built with **Laravel 12 (PHP 8.2+)**, **Blade**, **MySQL**, **Tailwind CSS 4**, **Alpine.js**, **Leaflet + OpenStreetMap** and **Chart.js**.

---

## Run it on another machine

### Requirements

- PHP 8.2 or newer with `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd`, `zip` (XAMPP or WAMP already include these)
- MySQL 5.7+ or MariaDB 10.4+
- [Composer](https://getcomposer.org/)
- Node.js is **not** required. The compiled CSS/JS is committed in `public/build`.

### Steps

```bash
# 1. Install PHP dependencies
composer install

# 2. Create the environment file and app key
copy .env.example .env          # macOS/Linux: cp .env.example .env
php artisan key:generate

# 3. Load the database (creates the `srmss` database with demo data)
#    Either import database/srmss.sql in phpMyAdmin (Import tab), or:
mysql -u root -p < database/srmss.sql

# 4. Start the app
php artisan serve
```

Open http://127.0.0.1:8000 and sign in.

If your MySQL user has a password or uses a different port, set `DB_USERNAME`, `DB_PASSWORD` and `DB_PORT` in `.env`.

**Alternative to step 3:** create an empty database named `srmss`, then run `php artisan migrate --seed`. This builds the same demo data with dates relative to *today*. Use this if the SQL file's trip history looks out of date.

### Demo accounts

| Role | Email | Password | Can do |
|---|---|---|---|
| Administrator | admin@srmss.lk | Admin@123 | Everything: users, depots, routes, timetables, buses and drivers (add, edit, remove), all reports. Corrects any trip, including completed ones, and edits or deletes trip activity entries. Can switch depot from the top bar. |
| Depot supervisor | supervisor@srmss.lk | Super@123 | Views buses, drivers, routes, timetables, fuel and maintenance records. Generates the day's trips, swaps buses and drivers, records delays. Operational reports only (trip completion, route performance, driver hours). Maharagama depot. |
| Operations staff | staff@srmss.lk | Staff@123 | Runs trips: departure, delay, arrival, cancellation. Views the bus, driver and route of a trip. Records and updates fuel fills and maintenance jobs (cannot delete them). |
| Depot supervisor | kandy.supervisor@srmss.lk | Super@123 | Same as supervisor, Kandy South depot |

Removing a bus, driver or route is a soft delete: past trips, fuel fills, maintenance jobs and reports keep showing it, and its page stays viewable with a "removed" notice. A bus, driver or route that still has active timetables or upcoming trips cannot be removed.

### Keeping trips generated

Trips are created from timetables. A week ahead is created whenever a timetable is saved. To keep the board filled day after day, either:

- press **Generate trips** on the Trips page, or
- run `php artisan srmss:generate-trips --days=7`, or
- leave `php artisan schedule:work` running (runs it every night at 00:05).

---

## Features by module

| Module | What it does |
|---|---|
| **1. Route planning** | Create routes by clicking stops on an OpenStreetMap map or searching place names. Drag to adjust, reorder, reverse. The road path, distance and driving time are fetched from OSRM. Stop distances and timings are estimated automatically. |
| **2. Schedule management** | Daily, weekly (chosen weekdays) and monthly (chosen dates) timetables. A **live clash check** runs as you fill in the form. Errors block saving; warnings must be acknowledged. Timetables can be suspended or resumed. Trips can be delayed, cancelled, or given a different bus or driver for emergencies, and every change is logged. |
| **3. Depot dashboard** | Active routes, available buses, drivers on duty, on-time rate and fleet utilisation. A trip board refreshes every minute, a 7-day trend chart is shown, and a "Needs attention" list flags trips without a bus, expiring licences and services due. |
| **4. Fuel and maintenance** | Fuel fills with automatic cost, and km/L measured tank-to-tank. Routine and corrective jobs: starting a job takes the bus off the road, and completing it returns the bus and resets its service counter. A due-for-service list is included. |
| **5. Driver and vehicle database** | Drivers (NIC, licence class and expiry, status, weekly hour limit, roster, hours worked) and buses (registration, capacity, service type, mileage, maintenance and fuel history). |
| **6. Reports and analytics** | Trip completion, route performance, fuel consumption (per bus, route and driver), maintenance summary and driver working hours, for a week, month or custom range. Each report has charts and written findings, and exports to **PDF** or **CSV**. |

### Scheduling rules (checked on every timetable)

| Rule | Severity |
|---|---|
| Bus already running at that time (+15 min turnaround) | Error |
| Driver already rostered at that time (+30 min rest) | Error |
| Another departure on the same route within 10 minutes | Error |
| Bus under maintenance or out of service | Error |
| Bus service type differs from the route's (e.g. normal bus on a luxury route) | Error |
| Driver not active or licence expired | Error |
| Timetable never runs, or arrival is before departure | Error |
| Bus has fewer seats than the route needs | Warning |
| Driver's licence expires before the timetable ends | Warning |
| Driver would exceed their weekly working-hour limit | Warning |
| Journey time much shorter than the route's usual running time | Warning |

The time limits can be changed in `config/srmss.php`.

---

## Architecture (3-tier)

```
Presentation tier   resources/views (Blade), resources/js (Alpine components: route editor,
                    schedule form, charts, live board), Tailwind CSS
        │  HTTP
Application tier    app/Http/Controllers  → thin controllers
                    app/Http/Requests     → validation (FormRequest classes)
                    app/Services          → business logic (scheduling, trips, fleet, reports)
                    app/Models            → Eloquent domain models
        │  Eloquent / PDO
Data tier           MySQL: depots, users, buses, drivers, bus_routes, route_stops,
                    schedules, trips, trip_adjustments, fuel_logs, maintenance_records
```

### Object-oriented design

| Concept | Where it is used |
|---|---|
| **Interfaces** | `ConflictRule`, `ReportExporter`, `HasBadge` (in `app/Contracts`) |
| **Strategy pattern** | Each scheduling rule is its own class implementing `ConflictRule`. `ScheduleConflictDetector` runs whatever rules are registered in `AppServiceProvider`. |
| **Template method** | `AbstractDoubleBookingRule` holds the shared overlap algorithm. `BusDoubleBookingRule`, `DriverDoubleBookingRule` and `RouteOverlapRule` supply only the differences. |
| **Abstract class + inheritance** | `Report` is the base for `TripCompletionReport`, `RoutePerformanceReport`, `FuelConsumptionReport`, `MaintenanceSummaryReport` and `DriverHoursReport` |
| **Factory** | `ReportFactory` creates a report object from its URL key |
| **Polymorphism** | `PdfReportExporter` and `CsvReportExporter` both export any `Report` |
| **Value objects** (immutable) | `RecurrenceRule`, `ReportPeriod`, `GeoPoint`, `ScheduleProposal`, `Conflict`, `ConflictReport` |
| **Encapsulation** | Domain behaviour lives on models, e.g. `Bus::isServiceDue()`, `Driver::licenseStatus()`, `Trip::wasOnTime()`, `Schedule::occursOn()` |
| **Enums with behaviour** | `UserRole::permissions()`, `TripStatus::isOpen()`, `LicenseStatus::fromExpiry()` |
| **Traits** | `BelongsToDepot` keeps each depot's data separate automatically |
| **Dependency injection** | Services are injected into controllers by Laravel's container |
| **Custom exception** | `SchedulingException` for business-rule violations, shown to the user as a message |

Key classes for the class diagram: `User`, `Depot`, `Bus`, `Driver`, `BusRoute`, `RouteStop`, `Schedule`, `Trip`, `TripAdjustment`, `FuelLog`, `MaintenanceRecord`, `ScheduleConflictDetector`, `ScheduleManager`, `AssignmentAdvisor`, `TripGenerator`, `TripOperations`, `TripClashChecker`, `RoutePlanner`, `FuelEfficiencyCalculator`, `MaintenanceService`, `DashboardMetrics`, `Report` (and its subclasses), `ReportFactory`, `DepotContext`.

---

## Testing

```bash
php artisan test
```

There are 109 automated tests. They use an in-memory SQLite database, so your MySQL data is not touched.

| Test file | Type | Covers |
|---|---|---|
| `tests/Unit/RecurrenceRuleTest.php` | White box | Daily, weekly and monthly recurrence; finding shared dates between timetables |
| `tests/Unit/DomainRulesTest.php` | White box | Licence status, role permissions, distance calculation, report periods |
| `tests/Feature/AuthenticationTest.php` | Black box | Sign in and out, wrong password, inactive accounts, lock-out after 5 failures |
| `tests/Feature/AccessControlTest.php` | Black box | What each role may open and do, depot data isolation, depot switching |
| `tests/Feature/TripCorrectionTest.php` | Black box | Administrator corrections to completed trips and to the activity log |
| `tests/Feature/RemovedRecordsTest.php` | Black box | Removed buses, drivers and routes keep their trips, fuel history and report figures |
| `tests/Feature/ScheduleConflictTest.php` | Black box | Every scheduling rule, warning acknowledgement, live check, suspension |
| `tests/Feature/TripOperationsTest.php` | Black box | Trip generation, departure, delay, arrival, cancellation, bus/driver swap |
| `tests/Feature/FleetManagementTest.php` | Black box | Routes with stops, bus and driver validation (Sri Lankan formats), fuel, maintenance |
| `tests/Feature/ReportsTest.php` | Black box | All reports render, export to PDF and CSV, and calculate correctly |

---

## Rebuilding the front end (only if you change CSS/JS)

```bash
npm install
npm run build     # or: npm run dev   while editing
```
