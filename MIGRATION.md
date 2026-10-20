# v5 Migration Notes

## Required DB changes (run `/dev/build?flush=1` after deploying)

### New tables
- `RevirCheckIn` — QR scan location records
- `AssignmentPhoto` — Before/after photos linked to WorkerAssignments
- `QueuedJobDescriptor` (from symbiote/silverstripe-queuedjobs)

### Modified tables

#### `Revir`
Add the `QRToken` field to the existing Revir DataObject in `app/src/DataObjects/Revir.php`:

```php
private static array $db = [
    // ... existing fields ...
    'QRToken' => 'Varchar(64)',  // ADD THIS — 32-byte hex static QR token
];
```

After `/dev/build`, generate tokens for existing revirs:

```php
// sake dev/tasks/GenerateRevirTokensTask
// or run from tinker:
foreach (Revir::get() as $r) {
    if (!$r->QRToken) {
        CleaningSaaS\Controllers\RevirQRController::generateRevirToken($r);
    }
}
```

A `GenerateRevirTokensTask` BuildTask can be added for convenience.

### New routes (already in _config/routes.yml)
- `/revir-qr/$Action/$Token` — QR scan handling
- `/portal/photo/$Action/$ID` — Photo upload
- `/portal/calendar.ics` — iCal feed
- `/portal/calendar/$Action`
- `/manager/$Action/$ID` — Area manager portal

### New middleware
`RateLimitMiddleware` is injected into the HTTP pipeline via config.yml.
No additional setup required.

### Environment variables (add to .env)
```
# Twilio (optional — bulk SMS/WhatsApp)
TWILIO_ACCOUNT_SID=ACxxxxxxxxxxxxxxxxx
TWILIO_AUTH_TOKEN=your_auth_token_here
TWILIO_FROM_NUMBER=+49XXXXXXXXXX
TWILIO_WHATSAPP_FROM=whatsapp:+14155238886

# Note: MAILER_DSN is intentionally NOT configured here.
# Email is sent via SilverStripe Email::create() only.
```

### Composer
```bash
composer require symbiote/silverstripe-queuedjobs twilio/sdk
```

Twilio SDK is optional — all BulkNotificationService methods
gracefully return `['sent'=>0, 'failed'=>0, 'errors'=>['Twilio SDK not available']]`
when the SDK is not installed.

### QueuedJobs initial setup
Bootstrap the weekly job once after deployment:

```php
$nextSunday = new DateTime('next sunday 22:00');
QueuedJobService::singleton()->queueJob(
    new CleaningSaaS\Jobs\WeeklyAssignmentJob(),
    $nextSunday->format('Y-m-d H:i:s')
);
```

Or keep the cron task as the primary driver:
```cron
0 22 * * 0  php /var/www/html/vendor/bin/sake dev/tasks/CleaningSaaS-Tasks-GenerateWeeklyAssignmentsTask
```
