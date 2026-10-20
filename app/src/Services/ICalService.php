<?php

declare(strict_types=1);

namespace YouWillLikeIT\Cleaning\Services;

use YouWillLikeIT\Cleaning\DataObjects\WorkerAssignment;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataList;
use SilverStripe\Security\Member;

/**
 * Generates RFC 5545 iCalendar (.ics) files for a worker's assignments.
 *
 * Usage:
 *   $ics = ICalService::create()->generateForWorker($member, $from, $to);
 *
 * VEVENT properties:
 *   DTSTART / DTEND  — from AssignedDate + CleaningSchedule start/end times
 *   SUMMARY          — "{RevirName} @ {CleaningObjectName}"
 *   LOCATION         — CleaningObject address fields
 *   DESCRIPTION      — Assignment status, notes, QR check-in URL
 *   UID              — "assignment-{ID}@cleaning-saas"
 *   LAST-MODIFIED    — Assignment LastEdited
 */
class ICalService
{
    use Injectable;

    // RFC 5545 date format
    private const DATE_FORMAT     = 'Ymd\THis\Z';
    private const DATE_FORMAT_UTC = 'Ymd\THis\Z';

    // Default shift duration when no schedule time is set
    private const DEFAULT_DURATION_HOURS = 2;

    // ── Public API ────────────────────────────────────────────────────────

    /**
     * Generate iCal calendar for a worker, optionally filtered by date range.
     *
     * @param Member $worker
     * @param string $from  Y-m-d (defaults to today)
     * @param string $to    Y-m-d (defaults to +1 year)
     */
    public function generateForWorker(
        Member $worker,
        string $from = '',
        string $to   = ''
    ): string {
        $from = $from ?: date('Y-m-d');
        $to   = $to   ?: date('Y-m-d', strtotime('+1 year'));

        $assignments = WorkerAssignment::get()->filter([
            'WorkerID'             => $worker->ID,
            'AssignedDate:GreaterThanOrEqual' => $from,
            'AssignedDate:LessThanOrEqual'    => $to,
        ])->sort('AssignedDate ASC');

        return $this->buildCalendar($assignments, $worker->Name ?: 'Worker');
    }

    // ── Calendar builder ──────────────────────────────────────────────────

    private function buildCalendar(DataList $assignments, string $calName): string
    {
        $lines   = [];
        $lines[] = 'BEGIN:VCALENDAR';
        $lines[] = 'VERSION:2.0';
        $lines[] = 'PRODID:-//YouWillLikeIT//Cleaning Schedule//DE';
        $lines[] = 'CALSCALE:GREGORIAN';
        $lines[] = 'METHOD:PUBLISH';
        $lines[] = 'X-WR-CALNAME:' . $this->escape($calName . ' — Reinigungsdienst');
        $lines[] = 'X-WR-CALDESC:Generiert von YouWillLikeIT Cleaning';
        $lines[] = 'X-WR-TIMEZONE:Europe/Berlin';

        foreach ($assignments as $assignment) {
            $event = $this->buildEvent($assignment);
            if ($event !== null) {
                $lines = array_merge($lines, $event);
            }
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    /** @return list<string>|null */
    private function buildEvent(WorkerAssignment $assignment): ?array
    {
        $revir  = $assignment->Revir();
        $object = $revir?->CleaningObject();

        if (!$revir || !$revir->exists()) {
            return null;
        }

        [$dtStart, $dtEnd] = $this->getEventTimes($assignment);

        $summary     = $this->buildSummary($revir, $object);
        $location    = $this->buildLocation($object);
        $description = $this->buildDescription($assignment);
        $uid         = 'assignment-' . $assignment->ID . '@cleaning-saas';
        $lastMod     = $this->toUtc($assignment->LastEdited);
        $dtstamp     = gmdate(self::DATE_FORMAT);

        $lines   = [];
        $lines[] = 'BEGIN:VEVENT';
        $lines[] = 'UID:' . $uid;
        $lines[] = 'DTSTAMP:' . $dtstamp;
        $lines[] = 'DTSTART:' . $dtStart;
        $lines[] = 'DTEND:' . $dtEnd;
        $lines[] = 'SUMMARY:' . $this->escape($summary);
        $lines[] = 'STATUS:' . $this->mapStatus($assignment->Status);

        if ($location) {
            $lines[] = 'LOCATION:' . $this->escape($location);
        }
        if ($description) {
            $lines[] = 'DESCRIPTION:' . $this->escape($description);
        }
        if ($lastMod) {
            $lines[] = 'LAST-MODIFIED:' . $lastMod;
        }

        // Add alarm 1h before
        $lines = array_merge($lines, $this->buildAlarm($summary));

        $lines[] = 'END:VEVENT';

        return array_map(fn(string $l) => $this->fold($l), $lines);
    }

    // ── Time helpers ──────────────────────────────────────────────────────

    /** @return array{string, string} [dtStart, dtEnd] in UTC iCal format */
    private function getEventTimes(WorkerAssignment $assignment): array
    {
        $date     = $assignment->AssignedDate;       // Y-m-d
        $schedule = $assignment->CleaningSchedule();

        // Try to get start/end times from the linked schedule
        $startTime = $schedule?->hasField('StartTime') ? (string) ($schedule->StartTime ?? '') : '';
        $endTime   = $schedule?->hasField('EndTime')   ? (string) ($schedule->EndTime   ?? '') : '';

        // Berlin timezone → UTC
        $tz = new \DateTimeZone('Europe/Berlin');

        if ($startTime && preg_match('/^\d{2}:\d{2}/', $startTime)) {
            $start = new \DateTime("{$date} {$startTime}", $tz);
        } else {
            $start = new \DateTime("{$date} 08:00:00", $tz);
        }

        if ($endTime && preg_match('/^\d{2}:\d{2}/', $endTime)) {
            $end = new \DateTime("{$date} {$endTime}", $tz);
        } else {
            $end = clone $start;
            $end->modify('+' . self::DEFAULT_DURATION_HOURS . ' hours');
        }

        // Ensure end is after start
        if ($end <= $start) {
            $end = clone $start;
            $end->modify('+' . self::DEFAULT_DURATION_HOURS . ' hours');
        }

        return [
            $start->setTimezone(new \DateTimeZone('UTC'))->format(self::DATE_FORMAT),
            $end->setTimezone(new \DateTimeZone('UTC'))->format(self::DATE_FORMAT),
        ];
    }

    private function toUtc(string $datetime): string
    {
        try {
            $dt = new \DateTime($datetime, new \DateTimeZone('Europe/Berlin'));
            return $dt->setTimezone(new \DateTimeZone('UTC'))->format(self::DATE_FORMAT);
        } catch (\Throwable) {
            return gmdate(self::DATE_FORMAT);
        }
    }

    // ── Content helpers ───────────────────────────────────────────────────

    private function buildSummary(
        \YouWillLikeIT\Cleaning\DataObjects\Revir $revir,
        ?\YouWillLikeIT\Cleaning\DataObjects\CleaningObject $object
    ): string {
        $name = $revir->Name ?? 'Revier';
        if ($object?->exists()) {
            return $name . ' @ ' . ($object->Name ?? '');
        }
        return $name;
    }

    private function buildLocation(?\YouWillLikeIT\Cleaning\DataObjects\CleaningObject $object): string
    {
        if (!$object?->exists()) {
            return '';
        }

        $parts = array_filter([
            $object->hasField('Street')  ? (string) ($object->Street  ?? '') : '',
            $object->hasField('City')    ? (string) ($object->City    ?? '') : '',
            $object->hasField('ZipCode') ? (string) ($object->ZipCode ?? '') : '',
        ]);

        return implode(', ', $parts);
    }

    private function buildDescription(WorkerAssignment $assignment): string
    {
        $parts = [];

        $parts[] = 'Status: ' . ($assignment->Status ?? 'Pending');

        if ($assignment->Notes) {
            $parts[] = 'Notiz: ' . strip_tags($assignment->Notes);
        }

        // Link to QR check-in for this revir
        $revir = $assignment->Revir();
        if ($revir?->exists() && $revir->QRToken) {
            $parts[] = 'QR Check-In: ' . \SilverStripe\Control\Director::absoluteBaseURL()
                . 'revir-qr/scan/' . $revir->QRToken;
        }

        return implode('\n', $parts);
    }

    private function mapStatus(string $status): string
    {
        return match ($status) {
            'Completed' => 'COMPLETED',
            'Cancelled' => 'CANCELLED',
            default     => 'CONFIRMED',
        };
    }

    /** @return list<string> */
    private function buildAlarm(string $summary): array
    {
        return [
            'BEGIN:VALARM',
            'TRIGGER:-PT1H',
            'ACTION:DISPLAY',
            'DESCRIPTION:Erinnerung: ' . $this->escape($summary),
            'END:VALARM',
        ];
    }

    // ── RFC 5545 text escaping and line folding ────────────────────────────

    /**
     * Escape special characters for iCal text values.
     */
    private function escape(string $value): string
    {
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace("\n", '\\n', $value);
        $value = str_replace("\r", '', $value);
        $value = str_replace(',', '\\,', $value);
        $value = str_replace(';', '\\;', $value);
        return $value;
    }

    /**
     * RFC 5545 §3.1: fold long lines at 75 octets.
     */
    private function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $folded = '';
        while (strlen($line) > 75) {
            $folded .= mb_substr($line, 0, 75) . "\r\n ";
            $line    = mb_substr($line, 75);
        }
        $folded .= $line;
        return $folded;
    }
}
