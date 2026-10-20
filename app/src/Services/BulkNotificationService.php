<?php

declare(strict_types=1);

namespace YouWillLikeIT\Cleaning\Services;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\SS_List;
use SilverStripe\Security\Member;

/**
 * Bulk SMS and WhatsApp notifications via Twilio.
 *
 * Configuration (in .env or _config/config.yml):
 *   TWILIO_ACCOUNT_SID=ACxxxxxxxxxx
 *   TWILIO_AUTH_TOKEN=your_token
 *   TWILIO_FROM_NUMBER=+49123456789          (SMS)
 *   TWILIO_WHATSAPP_FROM=whatsapp:+14155238886  (WhatsApp sandbox/prod)
 *
 * Install: composer require twilio/sdk
 * This service is OPTIONAL — all methods gracefully fall back if Twilio
 * SDK is not installed or credentials are missing.
 *
 * WhatsApp note: requires a pre-approved template for messages outside
 * the 24-hour session window. For testing, use the sandbox number.
 */
class BulkNotificationService
{
    use Injectable;
    use Configurable;

    // ── Configurable via SilverStripe config system ───────────────────────

    private static string $twilio_account_sid  = '';
    private static string $twilio_auth_token   = '';
    private static string $twilio_from_number  = '';
    private static string $twilio_whatsapp_from = '';

    /** Max recipients per bulk send (Twilio rate limit safety). */
    private static int $bulk_batch_size = 50;

    // ── Public API ────────────────────────────────────────────────────────

    /**
     * Send a plain SMS to all workers in the list.
     *
     * @param SS_List<Member>|Member[] $workers
     * @return array{sent: int, failed: int, errors: string[]}
     */
    public function sendBulkSms(iterable $workers, string $message): array
    {
        $client = $this->getTwilioClient();
        if (!$client) {
            return ['sent' => 0, 'failed' => 0, 'errors' => ['Twilio SDK not available or not configured']];
        }

        $from = $this->resolveConfig('twilio_from_number');
        if (!$from) {
            return ['sent' => 0, 'failed' => 0, 'errors' => ['TWILIO_FROM_NUMBER not configured']];
        }

        return $this->sendBulk($client, $workers, $from, $message, 'sms');
    }

    /**
     * Send a WhatsApp message to all workers in the list.
     *
     * @param SS_List<Member>|Member[] $workers
     * @return array{sent: int, failed: int, errors: string[]}
     */
    public function sendBulkWhatsApp(iterable $workers, string $message): array
    {
        $client = $this->getTwilioClient();
        if (!$client) {
            return ['sent' => 0, 'failed' => 0, 'errors' => ['Twilio SDK not available or not configured']];
        }

        $from = $this->resolveConfig('twilio_whatsapp_from');
        if (!$from) {
            return ['sent' => 0, 'failed' => 0, 'errors' => ['TWILIO_WHATSAPP_FROM not configured']];
        }

        return $this->sendBulk($client, $workers, $from, $message, 'whatsapp');
    }

    /**
     * Notify workers about an assignment cancellation via best available channel.
     * Tries WhatsApp first, falls back to SMS, then skips silently.
     *
     * @param SS_List<Member>|Member[] $workers
     */
    public function notifyCancellation(iterable $workers, string $revirName, string $date): array
    {
        $message = sprintf(
            "⚠️ Ihr Einsatz am %s für Revier „%s“ wurde storniert. Bitte prüfen Sie Ihre Aufträge im Portal.",
            (new \DateTime($date))->format('d.m.Y'),
            $revirName
        );

        // Try WhatsApp first, fall back to SMS
        $waFrom = $this->resolveConfig('twilio_whatsapp_from');
        if ($waFrom) {
            return $this->sendBulkWhatsApp($workers, $message);
        }

        $smsFrom = $this->resolveConfig('twilio_from_number');
        if ($smsFrom) {
            return $this->sendBulkSms($workers, $message);
        }

        return ['sent' => 0, 'failed' => 0, 'errors' => ['No Twilio channels configured']];
    }

    /**
     * Send a single SMS to one worker (used by other services).
     */
    public function sendSingleSms(Member $worker, string $message): bool
    {
        $phone = $this->extractPhone($worker);
        if (!$phone) {
            return false;
        }

        $client = $this->getTwilioClient();
        if (!$client) {
            return false;
        }

        $from = $this->resolveConfig('twilio_from_number');
        if (!$from) {
            return false;
        }

        try {
            $client->messages->create($phone, ['from' => $from, 'body' => $message]);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    // ── Private ───────────────────────────────────────────────────────────

    private function sendBulk(
        object   $client,
        iterable $workers,
        string   $from,
        string   $message,
        string   $channel
    ): array {
        $sent   = 0;
        $failed = 0;
        $errors = [];
        $count  = 0;
        $limit  = (int) static::config()->get('bulk_batch_size');

        foreach ($workers as $worker) {
            if ($count >= $limit) {
                $errors[] = 'Batch limit reached — remaining workers skipped';
                break;
            }

            $phone = $this->extractPhone($worker);
            if (!$phone) {
                $failed++;
                $errors[] = "No phone for worker #{$worker->ID} ({$worker->Name})";
                continue;
            }

            $to = $channel === 'whatsapp' ? "whatsapp:{$phone}" : $phone;

            try {
                $client->messages->create($to, ['from' => $from, 'body' => $message]);
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = "Worker #{$worker->ID}: " . $e->getMessage();
            }

            $count++;

            // Respect Twilio rate limit: 1 msg/sec per number
            if ($count % 10 === 0) {
                usleep(100_000); // 100 ms between batches of 10
            }
        }

        return compact('sent', 'failed', 'errors');
    }

    /**
     * Extract a normalised E.164 phone number from a Member.
     * Assumes Member has a `Phone` field (common SS extension pattern).
     */
    private function extractPhone(Member $worker): ?string
    {
        $phone = $worker->hasField('Phone') ? (string) $worker->Phone : '';
        if (!$phone) {
            return null;
        }

        // Strip spaces, dashes, parens
        $phone = preg_replace('/[\s\-\(\)]/', '', $phone);
        // Convert German 0xxx to +49xxx
        if (str_starts_with($phone, '0') && !str_starts_with($phone, '00')) {
            $phone = '+49' . substr($phone, 1);
        }
        // Convert 00xx to +xx
        if (str_starts_with($phone, '00')) {
            $phone = '+' . substr($phone, 2);
        }

        // Basic E.164 validation
        return preg_match('/^\+[1-9]\d{7,14}$/', $phone) ? $phone : null;
    }

    /** @return object|null Twilio REST client or null if unavailable */
    private function getTwilioClient(): ?object
    {
        if (!class_exists('\Twilio\Rest\Client')) {
            return null;
        }

        $sid   = $this->resolveConfig('twilio_account_sid');
        $token = $this->resolveConfig('twilio_auth_token');

        if (!$sid || !$token) {
            return null;
        }

        try {
            return new \Twilio\Rest\Client($sid, $token);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Resolve config: checks environment first, then SS config system.
     */
    private function resolveConfig(string $key): string
    {
        // ENV_VAR convention: twilio_account_sid → TWILIO_ACCOUNT_SID
        $envKey = strtoupper($key);
        $envVal = getenv($envKey);
        if ($envVal !== false && $envVal !== '') {
            return $envVal;
        }

        return (string) (static::config()->get($key) ?? '');
    }
}
