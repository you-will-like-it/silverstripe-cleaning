<?php

declare(strict_types=1);

namespace YouWillLikeIT\Cleaning\Tasks;

use SilverStripe\Dev\BuildTask;
use SilverStripe\Security\Group;
use SilverStripe\Security\Permission;

/**
 * BuildDefaultGroupsTask — kreira sve potrebne grupe i permisije za modul.
 *
 * Idempotentno: može se pokrenuti više puta bez dupliranja.
 *
 * Pokretanje:
 *   vendor/bin/sake dev/tasks/YouWillLikeIT-Cleaning-Tasks-BuildDefaultGroupsTask
 */
class BuildDefaultGroupsTask extends BuildTask
{
    private static string $segment = 'YouWillLikeIT-Cleaning-Tasks-BuildDefaultGroupsTask';

    protected $title = 'Build Default Groups — Reinigung SaaS';

    protected $description = 'Kreira grupe SUPER_ADMIN, COMPANY_ADMIN, AREA_MANAGER, WORKER, CLIENT s odgovarajućim permisijama. Idempotentno.';

    /**
     * Definicija grupa: code → [title, permission_code, description]
     *
     * @var array<string, array{title: string, permission: string, description: string}>
     */
    private const GROUPS = [
        'super-admin' => [
            'title'       => 'Super Admin',
            'permission'  => 'SUPER_ADMIN',
            'description' => 'Puni pristup svim funkcijama platforme.',
        ],
        'company-admin' => [
            'title'       => 'Company Admin',
            'permission'  => 'COMPANY_ADMIN',
            'description' => 'Upravljanje kompanijom, zaposlenicima i objektima.',
        ],
        'area-manager' => [
            'title'       => 'Area Manager',
            'permission'  => 'AREA_MANAGER',
            'description' => 'Nadzor radnika i revira u dodijeljenom području.',
        ],
        'worker' => [
            'title'       => 'Worker',
            'permission'  => 'WORKER',
            'description' => 'Radnik — pristup portalu, zadacima i QR check-inu.',
        ],
        'client' => [
            'title'       => 'Client',
            'permission'  => 'CLIENT',
            'description' => 'Klijent — pregled stanja i ocjenjivanje usluge.',
        ],
    ];

    public function run($request): void
    {
        $this->output('🔧 BuildDefaultGroupsTask — Reinigung SaaS');
        $this->output('');

        $created = 0;
        $existing = 0;

        foreach (self::GROUPS as $code => $def) {
            $group = Group::get()->filter(['Code' => $code])->first();

            if ($group) {
                $existing++;
                $this->output("   ⏭  Postoji: {$def['title']} (Code: {$code}, ID: {$group->ID})");
                // Osiguraj da permisija postoji čak i ako je grupa stara
                $this->ensurePermission($group->ID, $def['permission']);
                continue;
            }

            $group              = Group::create();
            $group->Title       = $def['title'];
            $group->Code        = $code;
            $group->Description = $def['description'];
            $group->write();

            Permission::grant($group->ID, $def['permission']);

            $created++;
            $this->output("   ✓  Kreirana: {$def['title']} (Code: {$code}, ID: {$group->ID}, Permission: {$def['permission']})");
        }

        $this->output('');
        $this->output("✅ Završeno — kreirano: {$created}, već postojalo: {$existing}");
    }

    /**
     * Provjeri da permisija postoji za grupu; ako ne, dodaj je.
     */
    private function ensurePermission(int $groupID, string $permCode): void
    {
        $exists = Permission::get()->filter([
            'GroupID' => $groupID,
            'Code'    => $permCode,
        ])->exists();

        if (!$exists) {
            Permission::grant($groupID, $permCode);
        }
    }

    private function output(string $message): void
    {
        echo isset($_SERVER['REQUEST_URI'])
            ? $message . '<br>' . PHP_EOL
            : $message . PHP_EOL;
    }
}
