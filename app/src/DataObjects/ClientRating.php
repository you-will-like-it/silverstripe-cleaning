<?php

declare(strict_types=1);

namespace YouWillLikeIT\Cleaning\DataObjects;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;

/**
 * A client's quality rating for a specific Revir.
 *
 * Clients submit ratings via the client portal.
 * Rating is 1–5 stars. Optional free-text comment.
 */
class ClientRating extends DataObject
{
    private static string $table_name    = 'ClientRating';
    private static string $singular_name = 'Client Rating';
    private static string $plural_name   = 'Client Ratings';

    /** @var array<string,string> */
    private static array $db = [
        'Rating'  => 'Int',         // 1–5
        'Comment' => 'Text',
        'RatedAt' => 'Datetime',
    ];

    /** @var array<string,string> */
    private static array $has_one = [
        'Client' => Member::class,
        'Revir'  => Revir::class,
    ];

    /** @var array<string,string> */
    private static array $summary_fields = [
        'Client.Name' => 'Client',
        'Revir.Name'  => 'Revir',
        'Rating'      => 'Rating',
        'RatedAt'     => 'Rated At',
    ];

    private static string $default_sort = 'RatedAt DESC';

    protected function onBeforeWrite(): void
    {
        parent::onBeforeWrite();

        // Clamp rating to 1–5
        if ($this->Rating < 1) {
            $this->Rating = 1;
        }
        if ($this->Rating > 5) {
            $this->Rating = 5;
        }

        if (!$this->RatedAt) {
            $this->RatedAt = date('Y-m-d H:i:s');
        }
    }

    // ── Permissions ───────────────────────────────────────────────────────

    public function canView(mixed $member = null): bool
    {
        if (Permission::checkMember($member, ['SUPER_ADMIN', 'COMPANY_ADMIN', 'AREA_MANAGER', 'ADMIN'])) {
            return true;
        }
        $user = \SilverStripe\Security\Security::getCurrentUser();
        return $user && (int) $this->ClientID === (int) $user->ID;
    }

    public function canCreate(mixed $member = null, $context = []): bool
    {
        return Permission::checkMember($member, ['SUPER_ADMIN', 'COMPANY_ADMIN', 'CLIENT', 'ADMIN']);
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
