# Instalacija — youwilllikeit/silverstripe-cleaning

Ovaj dokument opisuje što trebate podesiti **nakon što je modul već instaliran** (`composer require youwilllikeit/silverstripe-cleaning`).

---

## Preduvjeti

- PHP 8.3+
- SilverStripe 6.x
- Composer

---

## Koraci

### 1. Composer require

```bash
composer require youwilllikeit/silverstripe-cleaning
```

### 2. Build baze podataka

```bash
vendor/bin/sake dev/build flush=1
```

Ovo kreira sve tablice modula:
`CleaningObject`, `Revir`, `WorkerAssignment`, `CleaningSchedule`,
`TimeTracking`, `ShiftSwapRequest`, `ClientRating`,
`AssignmentPhoto`, `RevirCheckIn`.

### 3. Konfiguriraj .env varijable

```dotenv
# Twilio — za SMS/WhatsApp obavijesti (opcionalno)
TWILIO_ACCOUNT_SID=ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_AUTH_TOKEN=your_auth_token_here
TWILIO_FROM_NUMBER=+49123456789
TWILIO_WHATSAPP_FROM=whatsapp:+14155238886
```

> ℹ️ Email obavijesti rade bez konfiguracije putem SilverStripe `Email::create()`.
> Twilio je opcionalan — modul se pokreće i bez njega.

### 4. Kreiraj grupe i permisije

```bash
vendor/bin/sake dev/tasks/YouWillLikeIT-Cleaning-Tasks-BuildDefaultGroupsTask
```

Kreira grupe: `super-admin`, `company-admin`, `area-manager`, `worker`, `client`
s odgovarajućim permisijama. **Idempotentno** — sigurno pokrenuti više puta.

### 5. Generiraj QR tokene za Revire

```bash
vendor/bin/sake dev/tasks/YouWillLikeIT-Cleaning-Tasks-GenerateRevirTokensTask
```

Popunjava `QRToken` (64-char hex) za sve Revire koji ga nemaju.
**Idempotentno** — preskače Revire koji već imaju token.

### 6. (Opcionalno) Seed demo podataka

> ⚠️ **Samo za razvoj/testiranje — briše sve postojeće podatke modula!**

```bash
vendor/bin/sake dev/tasks/YouWillLikeIT-Cleaning-Tasks-SeedDataTask --confirm
```

Opcije:
- `--confirm` — obavezno, zaštita od slučajnog pokretanja
- `--keep-users` — ne briše postojeće korisnike i grupe

Demo credentials nakon seed-a:

| Email | Lozinka | Uloga |
|---|---|---|
| `super@reinigung.de` | `Demo1234!` | Super Admin |
| `admin@reinigung.de` | `Demo1234!` | Company Admin |
| `manager@reinigung.de` | `Demo1234!` | Area Manager |
| `anna.mueller@reinigung.de` | `Demo1234!` | Worker |
| `thomas.weber@reinigung.de` | `Demo1234!` | Worker |
| `fatima.yilmaz@reinigung.de` | `Demo1234!` | Worker |
| `kunde@firma-mustermann.de` | `Demo1234!` | Client |

### 7. Postavi tjedni cron za generiranje smjena

```bash
# Svake nedjelje u 22:00 — generiraj smjene za sljedeći tjedan
0 22 * * 0 /var/www/html/vendor/bin/sake dev/tasks/YouWillLikeIT-Cleaning-Tasks-GenerateWeeklyAssignmentsTask
```

Ili putem `symbiote/silverstripe-queuedjobs` (preporučeno):

```bash
vendor/bin/sake dev/tasks/YouWillLikeIT-Cleaning-Tasks-SeedDataTask
```

Nakon seed-a, `WeeklyAssignmentJob` se automatski registrira za svake nedjelje.

### 8. Apache / Nginx — zaštita asset uploada

Fotografije radnika (`AssignmentPhoto`) se čuvaju u protected asset store-u.
SilverStripe to rješava automatski, ali provjeri da `.htaccess` u `public/assets/` nije onemogućen.

**Apache** — u `public/.htaccess` mora biti:
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [QSA,L]
```

**Nginx** — u server bloku:
```nginx
location /assets/ {
    try_files $uri /index.php?$query_string;
}
```

---

## Provjera instalacije

```bash
# Sve grupe postoje?
vendor/bin/sake dev/tasks/YouWillLikeIT-Cleaning-Tasks-BuildDefaultGroupsTask
# → trebalo bi pisati "already exists" za svaku grupu

# QR tokeni?
vendor/bin/sake dev/tasks/YouWillLikeIT-Cleaning-Tasks-GenerateRevirTokensTask
# → "No Revirs found" je OK ako još nema Revira

# Test generiranja smjena (bez pisanja):
vendor/bin/sake "dev/tasks/YouWillLikeIT-Cleaning-Tasks-GenerateWeeklyAssignmentsTask" --dry_run
```

---

## Proširivanje servisa

Dva servisa su dizajnirana za override putem SilverStripe Injector-a:

### SicknessService — vlastita logika bolovanja

```yaml
# app/_config/services.yml
SilverStripe\Core\Injector\Injector:
  YouWillLikeIT\Cleaning\Services\SicknessService:
    class: App\Services\CustomSicknessService
```

```php
class CustomSicknessService extends SicknessService
{
    public function isWorkerAbsent(int $workerID, string $date): bool
    {
        // vlastita logika...
    }
}
```

### NotificationService — SMS/WhatsApp umjesto emaila

```yaml
# app/_config/services.yml
SilverStripe\Core\Injector\Injector:
  YouWillLikeIT\Cleaning\Services\NotificationService:
    class: App\Services\TwilioNotificationService
```

Vidi `BulkNotificationService` za gotovu Twilio implementaciju.
