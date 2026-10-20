<?php

declare(strict_types=1);

namespace YouWillLikeIT\Cleaning\DataObjects;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;

/**
 * A single scheduled cleaning shift: one Worker, one Revir, one Date.
 *
 * Generated weekly by GenerateWeeklyAssignmentsTask / WeeklyAssignmentJob.
 * Workers can request swaps via ShiftSwapRequest.
 * Status lifecycle: Pending → Confirmed → Completed | Cancelled.
 */
class WorkerAssignment extends DataObject
{
    private static string $table_name    = 'WorkerAssignment';
    private static string $singular_name = 'Worker Assignment';
    private static string $plural_name   = 'Worker Assignments';

    /** @var array<string,string> */
    private static array $db = [
        'AssignedDate' => 'Date',
        'Status'       => 'Enum("Pending,Confirmed,Completed,Cancelled","Pending")',
        'Notes'        => 'Text',
    ];

    /** @var array<string,string> */
    private static array $has_one = [
        'Worker'          => Member::class,
        'Revir'           => Revir::class,
        'CleaningSchedule' => CleaningSchedule::class,
    ];

    /** @var array<string,string> */
    private static array $has_many = [
        'Photos'         => AssignmentPhoto::class,
        'CheckIns'       => RevirCheckIn::class,
        'TimeEntries'    => TimeTracking::class,
        'SwapRequests'   => ShiftSwapRequest::class . '.OriginalShift',
    ];

    /** @var array<string,string> */
    private static array $summary_fields = [
        'AssignedDate'   => 'Date',
        'Worker.Name'    => 'Worker',
        'Revir.Name'     => 'Revir',
        'Status'         => 'Status',
    ];

    private static string $default_sort = 'AssignedDate DESC';

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
        return Permission::checkMember($member, ['SUPER_ADMIN', 'COMPANY_ADMIN', 'AREA_MANAGER', 'ADMIN']);
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
