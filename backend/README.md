# BeanPrepared Backend (PHP/MySQL)

## Setup
1. Create a MySQL database named `beanprepared`.
2. Apply the schema: `mysql -u root -p beanprepared < schema.sql`
3. Set environment variables for DB + OneSignal:
   - `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`
   - `ONESIGNAL_APP_ID_IOS`, `ONESIGNAL_APP_ID_ANDROID`, `ONESIGNAL_REST_API_KEY`
4. Run the API with PHP's built-in server:
   - `php -S localhost:8080 index.php`

## API Endpoints
- `GET /api/notification-types`
- `GET /api/events`
- `GET /api/event-submissions`
- `POST /api/users/register`
- `POST /api/preferences`
- `POST /api/events`
- `POST /api/event-submissions`

## Cron
Run the notification sender every minute for short lead times:
- `php cron/send_notifications.php`

## Admin
View submitted events in your browser:
- `http://localhost:8080/admin_submissions.php`
Manage events:
- `http://localhost:8080/admin_events.php`
Manage event types:
- `http://localhost:8080/admin_types.php`

## Web Frontend
Public site mirrors the app for upcoming events and submissions:
- `http://localhost:8080/public/index.php`
- `http://localhost:8080/public/submit.php`

## Notes
- Store all timestamps in UTC.
- OneSignal requires separate app IDs for iOS and Android. This starter uses the Android app ID in the cron payload; you can split per-platform when you add device metadata.
