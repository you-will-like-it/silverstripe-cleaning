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
