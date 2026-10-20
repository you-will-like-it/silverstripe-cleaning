<?php

declare(strict_types=1);

namespace YouWillLikeIT\Cleaning\DataObjects;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;

/**
 * Recurring schedule for a CleaningObject.
 *
 * DaysOfWeek is a comma-separated list of 3-letter day codes: Mon,Tue,Wed,Thu,Fri,Sat,Sun.
 * Used by GenerateWeeklyAssignmentsTask to create WorkerAssignments each week.
 */
class CleaningSchedule extends DataObject
{
    private static string $table_name    = 'CleaningSchedule';
    private static string $singular_name = 'Cleaning Schedule';
    private static string $plural_name   = 'Cleaning Schedules';

    /** @var array<string,string> */
    private static array $db = [
        'DaysOfWeek' => 'Varchar(50)',   // e.g. "Mon,Wed,Fri"
        'StartTime'  => 'Time',
        'EndTime'    => 'Time',
        'Notes'      => 'Text',
        'IsActive'   => 'Boolean',
    ];

    /** @var array<string,string> */
    private static array $has_one = [
        'CleaningObject' => CleaningObject::class,
    ];

    /** @var array<string,string> */
    private static array $has_many = [
        'Assignments' => WorkerAssignment::class,
    ];

    /** @var array<string,string> */
    private static array $summary_fields = [
        'CleaningObject.Name' => 'Object',
        'DaysOfWeek'          => 'Days',
        'StartTime'           => 'Start',
        'EndTime'             => 'End',
        'IsActive'            => 'Active',
    ];

    private static array $defaults = [
        'IsActive' => true,
    ];

    private static string $default_sort = 'CleaningObjectID ASC, StartTime ASC';

    /**
     * Return days as an array: ['Mon', 'Wed', 'Fri']
     *
     * @return list<string>
     */
    public function getDaysArray(): array
    {
        if (empty($this->DaysOfWeek)) {
            return [];
        }

        return array_map('trim', explode(',', $this->DaysOfWeek));
    }

    /**
     * Human-readable days string for task output.
     */
    public function getFormattedDays(): string
    {
        return $this->DaysOfWeek ?? '';
    }

    // ── Permissions ───────────────────────────────────────────────────────

    public function canView(mixed $member = null): bool
    {
        return Permission::checkMember($member, ['SUPER_ADMIN', 'COMPANY_ADMIN', 'AREA_MANAGER', 'ADMIN']);
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
