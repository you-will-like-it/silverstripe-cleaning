<?php

declare(strict_types=1);

namespace YouWillLikeIT\Cleaning\DataObjects;

use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;

/**
 * Before/after photo attached to a WorkerAssignment.
 *
 * Workers upload via portal; area managers and company admins view in CMS.
 * Photos are stored in the SS asset store (protected until admin approves,
 * or public — configure via WorkerPortalController upload handling).
 */
class AssignmentPhoto extends DataObject
{
    private static string $table_name    = 'AssignmentPhoto';
    private static string $singular_name = 'Assignment Photo';
    private static string $plural_name   = 'Assignment Photos';

    /** @var array<string,string> */
    private static array $db = [
        'PhotoType' => 'Enum("Before,After","Before")',
        'Caption'   => 'Varchar(255)',
        'TakenAt'   => 'Datetime',
    ];

    /** @var array<string,string> */
    private static array $has_one = [
        'Assignment' => WorkerAssignment::class,
        'Photo'      => Image::class,
        'UploadedBy' => Member::class,
    ];

    /** @var list<string> */
    private static array $owns = ['Photo'];

    /** @var array<string,string> */
    private static array $summary_fields = [
        'PhotoType'         => 'Type',
        'Photo.CMSThumbnail' => 'Preview',
        'TakenAt'           => 'Taken At',
        'Caption'           => 'Caption',
        'UploadedBy.Name'   => 'Uploaded By',
    ];

    /** @var string */
    private static string $default_sort = 'TakenAt ASC';

    // ── Hooks ─────────────────────────────────────────────────────────────

    protected function onBeforeWrite(): void
    {
        parent::onBeforeWrite();
        if (!$this->TakenAt) {
            $this->TakenAt = date('Y-m-d H:i:s');
        }
        if (!$this->UploadedByID) {
            $user = \SilverStripe\Security\Security::getCurrentUser();
            $this->UploadedByID = (int) ($user?->ID ?? 0);
        }
    }

    // ── Permissions ───────────────────────────────────────────────────────

    public function canView(mixed $member = null): bool
    {
        if (Permission::checkMember($member, ['SUPER_ADMIN', 'COMPANY_ADMIN', 'AREA_MANAGER', 'ADMIN'])) {
            return true;
        }
        // Worker can see photos on their own assignments
        $user = \SilverStripe\Security\Security::getCurrentUser();
        if ($user) {
            $assignment = $this->Assignment();
            return $assignment && (int) $assignment->WorkerID === (int) $user->ID;
        }
        return false;
    }

    public function canCreate(mixed $member = null, $context = []): bool
    {
        return Permission::checkMember(
            $member,
            ['SUPER_ADMIN', 'COMPANY_ADMIN', 'AREA_MANAGER', 'WORKER', 'ADMIN']
        );
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
