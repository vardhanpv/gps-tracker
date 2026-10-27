# GPS Tracker – Laravel REST API

A Laravel-based GPS tracking system that processes location logs for field representatives and generates reports for stationary stops and visits to geographical zones.

## 1. Project Overview

This project was developed as part of a PHP Developer programming assignment.

The application stores GPS coordinates recorded for representatives and provides REST API endpoints to identify:

- **Stop Reports:** Detect when a representative remains within a 50-metre radius for at least 15 minutes.
- **Zone Visit Reports:** Identify visits to a specified geographical zone and calculate the observed duration of each visit.
- **GPS Gap Handling:** Separate stops and zone visits when GPS records have gaps greater than five minutes.
- **Input Validation:** Validate dates, geographical coordinates, radius values, and representative IDs.
- **Automated Testing:** Verify API behaviour using Laravel's testing framework.

## 2. Technology Stack

- PHP 8.5
- Laravel
- MySQL 8.4 / MariaDB
- SQLite for automated tests
- Laravel Eloquent ORM
- REST APIs returning JSON
- PHPUnit / Laravel testing framework

## 3. Requirements

Before installing the application, ensure you have:

- PHP with the extensions required by Laravel
- Composer
- MySQL or MariaDB
- A local development environment such as WAMP
- Git, for version control and submission

## 4. Installation and Setup

### Step 1: Clone or obtain the project

Clone the repository or place the project inside your local web development directory.

For WAMP, the project directory may be:

`C:\wamp64\www\gps-tracker`

Open a terminal in the project directory.

### Step 2: Install dependencies

```bash
composer install
```

### Step 3: Configure environment variables

Copy `.env.example` to `.env` if `.env` does not already exist.

Configure your application and database settings:

```env
APP_NAME="GPS Tracker"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gps_tracker
DB_USERNAME=root
DB_PASSWORD=
```

Update the database username and password to match your local environment.

**Important:** Never commit `.env` or production credentials to GitHub.

### Step 4: Generate the application key

```bash
php artisan key:generate
```

### Step 5: Create the database

Create a MySQL database named `gps_tracker`, or configure the application to use an existing development database.

Do not use your production database for development or automated tests.

### Step 6: Run migrations

```bash
php artisan migrate
```

This creates the required application tables.

### Step 7: Start the application

```bash
php artisan serve
```

The default local address is:

`http://127.0.0.1:8000`

## 5. Database Design

### Representatives

The `representatives` table stores information about each field representative.

| Column | Description |
|---|---|
| `id` | Primary key |
| `name` | Representative's name |
| `employee_code` | Unique employee identifier |
| `created_at` | Record creation timestamp |
| `updated_at` | Last update timestamp |

### Location Logs

The `location_logs` table stores GPS tracking records.

| Column | Description |
|---|---|
| `id` | Primary key |
| `representative_id` | Foreign key referencing representatives |
| `latitude` | GPS latitude |
| `longitude` | GPS longitude |
| `recorded_at` | Date and time of the GPS reading |
| `created_at` | Record creation timestamp |
| `updated_at` | Last update timestamp |

### Relationships

- One representative can have multiple location logs.
- Each location log belongs to one representative.
- Deleting a representative cascades to their associated location logs.

### Indexing

The location logs table includes:

- An index on `recorded_at`.
- A compound index on `(representative_id, recorded_at)`.

These indexes help support date filtering and chronological retrieval of GPS records.

## 6. API Endpoints

All endpoints return JSON responses.

### A. Stop Report

**Endpoint**

```http
GET /api/reports/stops
```

**Required parameter**

| Parameter | Description |
|---|---|
| `date` | Report date in `YYYY-MM-DD` format |

**Optional parameter**

| Parameter | Description |
|---|---|
| `representative_id` | Restricts the report to one representative |

**Example request**

```http
GET /api/reports/stops?date=2026-10-27
```

For a single representative:

```http
GET /api/reports/stops?date=2026-10-27&representative_id=1
```

**Stop detection rules**

- Maximum distance from the stop's initial GPS point: 50 metres.
- Minimum observed duration: 15 minutes.
- Maximum gap between consecutive GPS readings: 5 minutes.
- At least two GPS readings are required.

A new stop group begins when the representative moves beyond the permitted radius or the GPS gap exceeds five minutes.

**Example response**

```json
{
  "date": "2026-10-27",
  "stop_radius_meters": 50,
  "minimum_duration_minutes": 15,
  "maximum_gps_gap_minutes": 5,
  "count": 1,
  "data": [
    {
      "representative_id": 1,
      "representative_name": "Test Representative",
      "start_time": "2026-10-27 09:00:00",
      "end_time": "2026-10-27 09:20:00",
      "duration_minutes": 20,
      "latitude": 11.2588,
      "longitude": 75.7804,
      "location_points": 5
    }
  ]
}
```

The example is illustrative; actual results depend on the GPS records in the database.

### B. Zone Visit Report

**Endpoint**

```http
GET /api/reports/zone-visits
```

**Required parameters**

| Parameter | Description |
|---|---|
| `date` | Report date in `YYYY-MM-DD` format |
| `latitude` | Latitude of the zone centre |
| `longitude` | Longitude of the zone centre |
| `radius` | Zone radius in metres |

**Example request**

```http
GET /api/reports/zone-visits?date=2026-10-27&latitude=11.2588&longitude=75.7804&radius=100
```

**Zone visit rules**

- The zone is defined by a centre coordinate and a radius.
- GPS readings within the radius are considered inside the zone.
- A visit is split when a reading falls outside the zone.
- A visit is also split when consecutive GPS readings have a gap greater than five minutes.
- At least two GPS readings are required to report a visit.
- Separate visits are returned as separate records.

**Example response**

```json
{
  "date": "2026-10-27",
  "zone": {
    "latitude": 11.2588,
    "longitude": 75.7804,
    "radius_meters": 100
  },
  "maximum_gps_gap_minutes": 5,
  "count": 1,
  "data": [
    {
      "representative_id": 1,
      "representative_name": "Test Representative",
      "entry_time": "2026-10-27 09:00:00",
      "exit_time": "2026-10-27 09:20:00",
      "duration_minutes": 20,
      "location_points": 5
    }
  ]
}
```

The entry and exit timestamps represent the first and last observed GPS readings inside the zone, not necessarily the exact physical entry and exit times.

The API returns individual visits. A combined total for a representative's multiple visits must be calculated by aggregating the returned visit durations.

## 7. GPS Distance Calculation

The application uses the Haversine formula to calculate the approximate distance between two geographical coordinates on Earth.

This distance calculation is used for:

- Detecting whether a representative remains within a stop radius.
- Determining whether GPS readings fall inside a specified zone.

Latitude and longitude are stored as decimal values with seven decimal places.

## 8. Error Handling and Validation

The APIs validate incoming parameters before processing GPS records.

Examples of validation rules include:

- Dates must match `YYYY-MM-DD`.
- Latitude must be between `-90` and `90`.
- Longitude must be between `-180` and `180`.
- Zone radius must be greater than zero and no more than 50,000 metres.
- A supplied representative ID must exist in the representatives table.

Laravel returns JSON validation errors for invalid API requests.

## 9. Running Automated Tests

The project includes automated tests for both report APIs.

Run the complete test suite:

```bash
php artisan test
```

The tests cover:

- Qualified stop detection.
- Invalid date handling.
- Separation of stops across long GPS gaps.
- Zone visit detection.
- Invalid coordinate validation.
- Separation of visits across long GPS gaps.
- Separation of visits when a representative leaves and returns to a zone.

The current development test suite has passed **9 tests with 32 assertions**.

Automated tests use SQLite in-memory storage through `phpunit.xml`, keeping the tests isolated from the MySQL development database.

## 10. Performance and Scalability Considerations

The current implementation uses database indexes to improve GPS record retrieval.

For larger datasets, further improvements may include:

- Processing GPS logs in chunks rather than loading an entire day's records into application memory.
- Applying database-level filtering and aggregation where appropriate.
- Partitioning large location-log tables by date.
- Removing duplicate GPS events using an appropriate event identifier or deduplication rule.
- Handling missing, delayed, or out-of-order GPS events.
- Measuring query performance with representative production-sized datasets.

The current implementation retrieves the requested day's logs into application memory, so large-scale performance should be benchmarked before production deployment.

## 11. Known Limitations

- Stop and zone durations are calculated from observed GPS timestamps.
- Visits with fewer than two GPS points are not reported.
- GPS accuracy, device clock drift, and delayed location uploads can affect results.
- The current APIs process one requested date at a time.
- Authentication and authorization should be configured before exposing the APIs publicly.
- The optional daily movement map using Leaflet and OpenStreetMap has not yet been implemented.

## 12. Future Enhancements

Potential improvements include:

- An interactive daily GPS movement map.
- Representative-wise and date-range reports.
- A combined total duration for multiple zone visits.
- Authentication and API rate limiting.
- Efficient chunked processing for high-volume GPS data.
- Additional tests for duplicate events, missing readings, and boundary conditions.

## 13. Project Status

The Stop Report and Zone Visit Report endpoints are implemented, and the current automated test suite passes.

The optional movement map and further high-volume performance optimisations remain future work.
