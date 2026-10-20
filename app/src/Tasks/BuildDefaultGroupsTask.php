<?php

declare(strict_types=1);

namespace YouWillLikeIT\Cleaning\Tasks;

use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\Security\Group;
use SilverStripe\Security\Permission;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

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
    protected string $title = 'Build Default Groups — Reinigung SaaS';

    protected static string $description = 'Kreira grupe SUPER_ADMIN, COMPANY_ADMIN, AREA_MANAGER, WORKER, CLIENT s odgovarajućim permisijama. Idempotentno.';

    /**
     * Definicija grupa: code → [title, permission, description]
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

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $output->writeln('Building default groups for Reinigung SaaS...');
        $output->writeln('');

        $created  = 0;
        $existing = 0;

        foreach (self::GROUPS as $code => $def) {
            $group = Group::get()->filter(['Code' => $code])->first();

            if ($group) {
                $existing++;
                $this->ensurePermission($group->ID, $def['permission']);
                $output->writeln("  SKIP   {$def['title']} (code: {$code}, ID: {$group->ID}) — already exists");
                continue;
            }

            $group              = Group::create();
            $group->Title       = $def['title'];
            $group->Code        = $code;
            $group->Description = $def['description'];
            $group->write();

            Permission::grant($group->ID, $def['permission']);

            $created++;
            $output->writeln("  CREATE {$def['title']} (code: {$code}, ID: {$group->ID}, perm: {$def['permission']})");
        }

        $output->writeln('');
        $output->writeln("Done. Created: {$created}, already existed: {$existing}");

        return Command::SUCCESS;
    }

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
}
