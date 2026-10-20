<?php

declare(strict_types=1);

namespace YouWillLikeIT\Cleaning\DataObjects;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;

/**
 * A worker's request to swap their shift with another worker.
 *
 * RequestedBy wants to swap OriginalShift with RequestedTo's SwapShift.
 * Area manager or company admin approves/rejects.
 * On approval, the two WorkerAssignment.WorkerID values are swapped.
 */
class ShiftSwapRequest extends DataObject
{
    private static string $table_name    = 'ShiftSwapRequest';
    private static string $singular_name = 'Shift Swap Request';
    private static string $plural_name   = 'Shift Swap Requests';

    /** @var array<string,string> */
    private static array $db = [
        'Status' => 'Enum("Pending,Approved,Rejected,Cancelled","Pending")',
        'Reason' => 'Text',
        'Notes'  => 'Text',   // manager notes on approval/rejection
    ];

    /** @var array<string,string> */
    private static array $has_one = [
        'RequestedBy'   => Member::class,
        'RequestedTo'   => Member::class,
        'OriginalShift' => WorkerAssignment::class,
        'SwapShift'     => WorkerAssignment::class,
    ];

    /** @var array<string,string> */
    private static array $summary_fields = [
        'RequestedBy.Name' => 'Requested By',
        'RequestedTo.Name' => 'Requested To',
        'OriginalShift.AssignedDate' => 'Original Date',
        'SwapShift.AssignedDate'     => 'Swap Date',
        'Status'           => 'Status',
    ];

    private static string $default_sort = 'Created DESC';

    // ── Permissions ───────────────────────────────────────────────────────

    public function canView(mixed $member = null): bool
    {
        if (Permission::checkMember($member, ['SUPER_ADMIN', 'COMPANY_ADMIN', 'AREA_MANAGER', 'ADMIN'])) {
            return true;
        }
        $user = \SilverStripe\Security\Security::getCurrentUser();
        if ($user) {
            return (int) $this->RequestedByID === (int) $user->ID
                || (int) $this->RequestedToID === (int) $user->ID;
        }
        return false;
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
