<?php

declare(strict_types=1);

namespace YouWillLikeIT\Cleaning\DataObjects;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\HasManyList;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;

/**
 * A physical location (building / property) that is cleaned by the company.
 *
 * Has many Revirs (zones/areas), each assigned to a worker.
 * Belongs to a client (Member with CLIENT permission).
 */
class CleaningObject extends DataObject
{
    private static string $table_name    = 'CleaningObject';
    private static string $singular_name = 'Cleaning Object';
    private static string $plural_name   = 'Cleaning Objects';

    /** @var array<string,string> */
    private static array $db = [
        'Name'     => 'Varchar(255)',
        'Street'   => 'Varchar(255)',
        'ZipCode'  => 'Varchar(10)',
        'City'     => 'Varchar(100)',
        'Country'  => 'Varchar(100)',
        'Notes'    => 'Text',
        'IsActive' => 'Boolean',
    ];

    /** @var array<string,string> */
    private static array $has_one = [
        'Client' => Member::class,
    ];

    /** @var array<string,string> */
    private static array $has_many = [
        'Revirs'    => Revir::class,
        'Schedules' => CleaningSchedule::class,
    ];

    /** @var array<string,string> */
    private static array $summary_fields = [
        'Name'     => 'Name',
        'Street'   => 'Street',
        'ZipCode'  => 'ZIP',
        'City'     => 'City',
        'IsActive' => 'Active',
    ];

    private static array $defaults = [
        'IsActive' => true,
        'Country'  => 'Deutschland',
    ];

    private static string $default_sort = 'Name ASC';

    public function getFullAddress(): string
    {
        return implode(', ', array_filter([
            $this->Street,
            $this->ZipCode . ' ' . $this->City,
            $this->Country,
        ]));
    }

    // ── Permissions ───────────────────────────────────────────────────────

    public function canView(mixed $member = null): bool
    {
        if (Permission::checkMember($member, ['SUPER_ADMIN', 'COMPANY_ADMIN', 'AREA_MANAGER', 'ADMIN'])) {
            return true;
        }
        // Client sees only their own objects
        $user = \SilverStripe\Security\Security::getCurrentUser();
        return $user && (int) $this->ClientID === (int) $user->ID;
    }

    public function canCreate(mixed $member = null, $context = []): bool
    {
        return Permission::checkMember($member, ['SUPER_ADMIN', 'COMPANY_ADMIN', 'ADMIN']);
    }

    public function canEdit(mixed $member = null): bool
    {
        return Permission::checkMember($member, ['SUPER_ADMIN', 'COMPANY_ADMIN', 'ADMIN']);
    }

    public function canDelete(mixed $member = null): bool
    {
        return Permission::checkMember($member, ['SUPER_ADMIN', 'COMPANY_ADMIN', 'ADMIN']);
    }
}
