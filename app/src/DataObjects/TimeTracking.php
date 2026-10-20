<?php

declare(strict_types=1);

namespace YouWillLikeIT\Cleaning\DataObjects;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;

/**
 * Tracks actual working hours for a WorkerAssignment.
 *
 * Workers clock in/out via the portal; area managers can correct entries.
 * Status: Pending → Approved | Rejected.
 */
class TimeTracking extends DataObject
{
    private static string $table_name    = 'TimeTracking';
    private static string $singular_name = 'Time Entry';
    private static string $plural_name   = 'Time Entries';

    /** @var array<string,string> */
    private static array $db = [
        'StartTime' => 'Datetime',
        'EndTime'   => 'Datetime',
        'Status'    => 'Enum("Pending,Approved,Rejected,Completed","Pending")',
        'Notes'     => 'Text',
    ];

    /** @var array<string,string> */
    private static array $has_one = [
        'Worker'     => Member::class,
        'Revir'      => Revir::class,
        'Assignment' => WorkerAssignment::class,
    ];

    /** @var array<string,string> */
    private static array $summary_fields = [
        'Worker.Name'  => 'Worker',
        'Revir.Name'   => 'Revir',
        'StartTime'    => 'Start',
        'EndTime'      => 'End',
        'Status'       => 'Status',
    ];

    private static string $default_sort = 'StartTime DESC';

    /**
     * Total approved/completed hours for a worker in a given month.
     *
     * Used by ManagerPortalController::minijob() for the 450 € threshold check.
     *
     * @param int $workerID  Member ID
     * @param int $year      e.g. 2025
     * @param int $month     1–12
     */
    public static function getMonthlyHours(int $workerID, int $year, int $month): float
    {
        $from = sprintf('%04d-%02d-01 00:00:00', $year, $month);
        $to   = date('Y-m-d 23:59:59', mktime(0, 0, 0, $month + 1, 0, $year));

        $entries = static::get()->filter([
            'WorkerID'                    => $workerID,
            'Status'                      => ['Approved', 'Completed'],
            'StartTime:GreaterThanOrEqual' => $from,
            'StartTime:LessThanOrEqual'    => $to,
        ]);

        $totalMinutes = 0;
        foreach ($entries as $entry) {
            $mins = $entry->getDurationMinutes();
            if ($mins !== null && $mins > 0) {
                $totalMinutes += $mins;
            }
        }

        return round($totalMinutes / 60, 2);
    }

    /**
     * Duration in minutes, or null if EndTime not set.
     */
    public function getDurationMinutes(): ?int
    {
        if (!$this->StartTime || !$this->EndTime) {
            return null;
        }

        $start = strtotime($this->StartTime);
        $end   = strtotime($this->EndTime);

        return (int) round(($end - $start) / 60);
    }

    // ── Permissions ───────────────────────────────────────────────────────

    public function canView(mixed $member = null): bool
    {
        if (Permission::checkMember($member, ['SUPER_ADMIN', 'COMPANY_ADMIN', 'AREA_MANAGER', 'ADMIN'])) {
            return true;
        }
        $user = \SilverStripe\Security\Security::getCurrentUser();
        return $user && (int) $this->WorkerID === (int) $user->ID;
    }

    public function canCreate(mixed $member = null, $context = []): bool
    {
        return Permission::checkMember($member, ['SUPER_ADMIN', 'COMPANY_ADMIN', 'AREA_MANAGER', 'WORKER', 'ADMIN']);
    }

    public function canEdit(mixed $member = null): bool
    {
        return Permission::checkMember($member, ['SUPER_ADMIN', 'COMPANY_ADMIN', 'AREA_MANAGER', 'ADMIN']);
    }

    public function canDelete(mixed $member = null): bool
    {
        return Permission::checkMember($member, ['SUPER_ADMIN', 'COMPANY_ADMIN', 'ADMIN']);
    }
}
