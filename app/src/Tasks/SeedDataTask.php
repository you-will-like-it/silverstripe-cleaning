<?php

declare(strict_types=1);

namespace YouWillLikeIT\Cleaning\Tasks;

use DateTime;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use YouWillLikeIT\Cleaning\DataObjects\RevirCheckIn;

/**
 * SeedDataTask — popunjava bazu realističnim demo podacima za razvoj i testiranje.
 *
 * UPOZORENJE: Briše sve postojeće podatke modula! Ne koristiti u produkciji.
 *
 * Pokretanje:
 *   vendor/bin/sake dev/tasks/YouWillLikeIT-Cleaning-Tasks-SeedDataTask --confirm
 *
 * Opcije:
 *   --confirm      Obavezno — zaštita od slučajnog pokretanja
 *   --keep-users   Ne briše postojeće korisnike/grupe
 *
 * DataObjecti koji nisu dio ovog modula (CleaningObject, Revir, WorkerAssignment,
 * itd.) se učitavaju dinamički putem class_exists() — task se gracefully prilagođava
 * onome što je dostupno u instalaciji.
 */
class SeedDataTask extends BuildTask
{
    protected string $title = 'Seed Demo Data — Reinigung SaaS';

    protected static string $description = 'Kreira realistične demo podatke. UPOZORENJE: briše sve postojeće podatke modula! Zahtijeva --confirm.';

    /** @var array<string, Member> */
    private array $members = [];

    /** @var array<string, Group> */
    private array $groups = [];

    /** @var array<string, object> CleaningObject instances */
    private array $objects = [];

    /** @var array<string, object> Revir instances */
    private array $revirs = [];

    private PolyOutput $out;

    /**
     * FQCN za DataObjecte koji žive u glavnoj aplikaciji (ne u modulu).
     * Mijenjaj ove konstante ako tvoja aplikacija koristi drugačiji namespace.
     */
    private const CLASS_CLEANING_OBJECT  = 'YouWillLikeIT\\Cleaning\\DataObjects\\CleaningObject';
    private const CLASS_REVIR            = 'YouWillLikeIT\\Cleaning\\DataObjects\\Revir';
    private const CLASS_WORKER_ASSIGN    = 'YouWillLikeIT\\Cleaning\\DataObjects\\WorkerAssignment';
    private const CLASS_CLEANING_SCHED   = 'YouWillLikeIT\\Cleaning\\DataObjects\\CleaningSchedule';
    private const CLASS_TIME_TRACKING    = 'YouWillLikeIT\\Cleaning\\DataObjects\\TimeTracking';
    private const CLASS_SHIFT_SWAP       = 'YouWillLikeIT\\Cleaning\\DataObjects\\ShiftSwapRequest';
    private const CLASS_CLIENT_RATING    = 'YouWillLikeIT\\Cleaning\\DataObjects\\ClientRating';

    public function getOptions(): array
    {
        return [
            new InputOption('confirm',    null, InputOption::VALUE_NONE, 'Required — confirms you want to wipe and re-seed data'),
            new InputOption('keep-users', null, InputOption::VALUE_NONE, 'Skip deleting existing members and groups'),
        ];
    }

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $this->out = $output;

        if (!$input->getOption('confirm')) {
            $output->writeln('ERROR: This task wipes all module data. Re-run with --confirm to proceed.');
            $output->writeln('  vendor/bin/sake dev/tasks/YouWillLikeIT-Cleaning-Tasks-SeedDataTask --confirm');
            return Command::FAILURE;
        }

        $keepUsers = (bool) $input->getOption('keep-users');

        $output->writeln('Seeding demo data for Reinigung SaaS...');
        $output->writeln('');

        $this->clearData($keepUsers);
        $this->seedGroups();
        $this->seedMembers($keepUsers);
        $this->seedCleaningObjects();
        $this->seedRevirs();
        $this->seedSchedules();
        $this->seedAssignments();
        $this->seedTimeTracking();
        $this->seedCheckIns();
        $this->seedSwapRequests();
        $this->seedClientRatings();

        $output->writeln('');
        $output->writeln('Done. Login credentials:');
        $output->writeln('  super@reinigung.de         / Demo1234!  (SuperAdmin)');
        $output->writeln('  admin@reinigung.de         / Demo1234!  (CompanyAdmin)');
        $output->writeln('  manager@reinigung.de       / Demo1234!  (AreaManager)');
        $output->writeln('  anna.mueller@reinigung.de  / Demo1234!  (Worker)');
        $output->writeln('  thomas.weber@reinigung.de  / Demo1234!  (Worker)');
        $output->writeln('  fatima.yilmaz@reinigung.de / Demo1234!  (Worker)');
        $output->writeln('  kunde@firma-mustermann.de  / Demo1234!  (Client)');

        return Command::SUCCESS;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Kreira instancu DataObjecta ako klasa postoji; logira skip ako ne postoji.
     */
    private function makeObject(string $class): ?object
    {
        if (!class_exists($class)) {
            $short = substr($class, strrpos($class, '\\') + 1);
            $this->out->writeln("  SKIP   {$short} class not found — skipping section");
            return null;
        }

        return $class::create();
    }

    /**
     * Dohvati DataList za klasu; null ako klasa ne postoji.
     *
     * @return \SilverStripe\ORM\DataList<\SilverStripe\ORM\DataObject>|null
     */
    private function getList(string $class): ?\SilverStripe\ORM\DataList
    {
        if (!class_exists($class)) {
            return null;
        }

        return $class::get();
    }

    // ─── Clear ────────────────────────────────────────────────────────────────

    private function clearData(bool $keepUsers): void
    {
        $this->out->writeln('Clearing existing module data...');

        $classes = [
            self::CLASS_CLIENT_RATING,
            self::CLASS_SHIFT_SWAP,
            RevirCheckIn::class,
            self::CLASS_TIME_TRACKING,
            self::CLASS_WORKER_ASSIGN,
            self::CLASS_CLEANING_SCHED,
            self::CLASS_REVIR,
            self::CLASS_CLEANING_OBJECT,
        ];

        foreach ($classes as $class) {
            if (class_exists($class)) {
                $class::get()->removeAll();
            }
        }

        if (!$keepUsers) {
            Member::get()->filter(['Email:EndsWith' => '@reinigung.de'])->removeAll();
            Member::get()->filter(['Email:EndsWith' => '@firma-mustermann.de'])->removeAll();

            foreach (['super-admin', 'company-admin', 'area-manager', 'worker', 'client'] as $code) {
                Group::get()->filter(['Code' => $code])->removeAll();
            }
        }

        $this->out->writeln('  cleared.');
    }

    // ─── Groups ───────────────────────────────────────────────────────────────

    private function seedGroups(): void
    {
        $this->out->writeln('Creating groups...');

        $defs = [
            'super-admin'   => ['Super Admin',   'SUPER_ADMIN'],
            'company-admin' => ['Company Admin', 'COMPANY_ADMIN'],
            'area-manager'  => ['Area Manager',  'AREA_MANAGER'],
            'worker'        => ['Worker',        'WORKER'],
            'client'        => ['Client',        'CLIENT'],
        ];

        foreach ($defs as $code => [$title, $perm]) {
            $group = Group::get()->filter(['Code' => $code])->first();

            if (!$group) {
                $group        = Group::create();
                $group->Title = $title;
                $group->Code  = $code;
                $group->write();
                Permission::grant($group->ID, $perm);
            }

            $this->groups[$code] = $group;
            $this->out->writeln("  GROUP  {$title} (ID: {$group->ID})");
        }
    }

    // ─── Members ──────────────────────────────────────────────────────────────

    private function seedMembers(bool $keepUsers): void
    {
        $this->out->writeln('Creating members...');

        $defs = [
            'super_admin'   => ['Super',  'Admin',      'super@reinigung.de',         'super-admin',   null],
            'company_admin' => ['Klaus',  'Schmidt',    'admin@reinigung.de',         'company-admin', '+4915112340000'],
            'area_manager'  => ['Maria',  'Hofmann',    'manager@reinigung.de',       'area-manager',  '+4915112345678'],
            'worker1'       => ['Anna',   'Müller',     'anna.mueller@reinigung.de',  'worker',        '+4915123456789'],
            'worker2'       => ['Thomas', 'Weber',      'thomas.weber@reinigung.de',  'worker',        '+4915234567890'],
            'worker3'       => ['Fatima', 'Yılmaz',     'fatima.yilmaz@reinigung.de', 'worker',        '+4915345678901'],
            'client'        => ['Peter',  'Mustermann', 'kunde@firma-mustermann.de',  'client',        '+4930123456789'],
        ];

        foreach ($defs as $key => [$first, $last, $email, $groupCode, $phone]) {
            $member = $keepUsers
                ? Member::get()->filter(['Email' => $email])->first()
                : null;

            if (!$member) {
                $member            = Member::create();
                $member->FirstName = $first;
                $member->Surname   = $last;
                $member->Email     = $email;
                if ($phone && $member->hasField('Phone')) {
                    $member->Phone = $phone;
                }
                $member->write();
                $member->changePassword('Demo1234!');

                if (isset($this->groups[$groupCode])) {
                    $member->Groups()->add($this->groups[$groupCode]);
                }
            }

            $this->members[$key] = $member;
            $this->out->writeln("  MEMBER {$first} {$last} <{$email}>");
        }

        // Link workers to area manager
        $manager = $this->members['area_manager'] ?? null;
        foreach (['worker1', 'worker2', 'worker3'] as $wKey) {
            $w = $this->members[$wKey] ?? null;
            if ($w && $manager && $w->hasField('AreaManagerID')) {
                $w->AreaManagerID = $manager->ID;
                $w->write();
            }
        }
    }

    // ─── Cleaning Objects ─────────────────────────────────────────────────────

    private function seedCleaningObjects(): void
    {
        $this->out->writeln('Creating cleaning objects...');

        if (!class_exists(self::CLASS_CLEANING_OBJECT)) {
            $this->out->writeln('  SKIP   CleaningObject class not found');
            return;
        }

        $class = self::CLASS_CLEANING_OBJECT;

        $defs = [
            'buerohaus_nord'  => ['Bürohaus Nord',         'Kantstraße 45',    '10627', 'Berlin'],
            'einkaufszentrum' => ['Einkaufszentrum Mitte', 'Alexanderplatz 3', '10178', 'Berlin'],
        ];

        foreach ($defs as $key => [$name, $street, $zip, $city]) {
            /** @var \SilverStripe\ORM\DataObject $obj */
            $obj           = $class::create();
            $obj->Name     = $name;
            $obj->IsActive = true;
            if ($obj->hasField('Street'))  $obj->Street  = $street;
            if ($obj->hasField('ZipCode')) $obj->ZipCode = $zip;
            if ($obj->hasField('City'))    $obj->City    = $city;

            $client = $this->members['client'] ?? null;
            if ($client && $obj->hasField('ClientID')) {
                $obj->ClientID = $client->ID;
            }

            $obj->write();
            $this->objects[$key] = $obj;
            $this->out->writeln("  OBJECT {$name} — {$street}, {$city}");
        }
    }

    // ─── Revirs ───────────────────────────────────────────────────────────────

    private function seedRevirs(): void
    {
        $this->out->writeln('Creating revirs...');

        if (!class_exists(self::CLASS_REVIR)) {
            $this->out->writeln('  SKIP   Revir class not found');
            return;
        }

        $class = self::CLASS_REVIR;

        $defs = [
            'erdgeschoss'   => ['Bürohaus Nord — Erdgeschoss', 'buerohaus_nord',  'worker1', 450.0],
            'obergeschoss1' => ['Bürohaus Nord — 1. OG',       'buerohaus_nord',  'worker2', 380.0],
            'einkauf_west'  => ['EKZ Mitte — West-Flügel',     'einkaufszentrum', 'worker3', 620.0],
            'einkauf_ost'   => ['EKZ Mitte — Ost-Flügel',      'einkaufszentrum', null,      580.0],
        ];

        foreach ($defs as $key => [$name, $objKey, $workerKey, $area]) {
            /** @var \SilverStripe\ORM\DataObject $revir */
            $revir           = $class::create();
            $revir->Name     = $name;
            $revir->IsActive = true;

            $obj = $this->objects[$objKey] ?? null;
            if ($obj && $revir->hasField('CleaningObjectID')) {
                $revir->CleaningObjectID = $obj->ID;
            }

            if ($workerKey) {
                $worker = $this->members[$workerKey] ?? null;
                if ($worker && $revir->hasField('AssignedWorkerID')) {
                    $revir->AssignedWorkerID = $worker->ID;
                }
            }

            if ($revir->hasField('FloorArea')) {
                $revir->FloorArea = $area;
            }

            if ($revir->hasField('QRToken')) {
                $revir->QRToken = bin2hex(random_bytes(32));
            }

            $revir->write();
            $this->revirs[$key] = $revir;

            $workerName = $workerKey
                ? (($this->members[$workerKey] ?? null)?->getName() ?? '?')
                : '(unassigned)';
            $this->out->writeln("  REVIR  {$name} → {$workerName}");
        }
    }

    // ─── Schedules ────────────────────────────────────────────────────────────

    private function seedSchedules(): void
    {
        $this->out->writeln('Creating schedules...');

        if (!class_exists(self::CLASS_CLEANING_SCHED)) {
            $this->out->writeln('  SKIP   CleaningSchedule class not found');
            return;
        }

        $class = self::CLASS_CLEANING_SCHED;

        $defs = [
            ['buerohaus_nord',  'Mon,Tue,Wed,Thu,Fri', '07:00:00', '10:00:00'],
            ['einkaufszentrum', 'Mon,Wed,Fri,Sat',     '06:00:00', '09:30:00'],
        ];

        foreach ($defs as [$objKey, $days, $start, $end]) {
            /** @var \SilverStripe\ORM\DataObject $schedule */
            $schedule           = $class::create();
            $schedule->IsActive = true;
            if ($schedule->hasField('DaysOfWeek')) $schedule->DaysOfWeek = $days;
            if ($schedule->hasField('StartTime'))  $schedule->StartTime  = $start;
            if ($schedule->hasField('EndTime'))    $schedule->EndTime    = $end;

            $obj = $this->objects[$objKey] ?? null;
            if ($obj && $schedule->hasField('CleaningObjectID')) {
                $schedule->CleaningObjectID = $obj->ID;
            }

            $schedule->write();
            $this->out->writeln("  SCHED  {$obj?->Name}: {$days} {$start}–{$end}");
        }
    }

    // ─── Assignments ──────────────────────────────────────────────────────────

    private function seedAssignments(): void
    {
        $this->out->writeln('Creating assignments for current week...');

        if (!class_exists(self::CLASS_WORKER_ASSIGN)) {
            $this->out->writeln('  SKIP   WorkerAssignment class not found');
            return;
        }

        $class  = self::CLASS_WORKER_ASSIGN;
        $monday = new DateTime('monday this week');

        // [revir_key, worker_key, day_offset, status]
        $defs = [
            ['erdgeschoss',   'worker1', 0, 'Confirmed'],
            ['erdgeschoss',   'worker1', 1, 'Confirmed'],
            ['erdgeschoss',   'worker1', 2, 'Pending'],
            ['erdgeschoss',   'worker1', 3, 'Pending'],
            ['erdgeschoss',   'worker1', 4, 'Pending'],
            ['obergeschoss1', 'worker2', 0, 'Confirmed'],
            ['obergeschoss1', 'worker2', 2, 'Pending'],
            ['obergeschoss1', 'worker2', 4, 'Pending'],
            ['einkauf_west',  'worker3', 0, 'Confirmed'],
            ['einkauf_west',  'worker3', 2, 'Pending'],
            ['einkauf_west',  'worker3', 4, 'Pending'],
            ['einkauf_west',  'worker3', 5, 'Pending'],
        ];

        $count = 0;
        foreach ($defs as [$revirKey, $workerKey, $offset, $status]) {
            $date   = (clone $monday)->modify("+{$offset} days");
            $revir  = $this->revirs[$revirKey] ?? null;
            $worker = $this->members[$workerKey] ?? null;

            if (!$revir || !$worker) {
                continue;
            }

            /** @var \SilverStripe\ORM\DataObject $a */
            $a = $class::create();
            $a->Status = $status;
            if ($a->hasField('AssignedDate')) $a->AssignedDate = $date->format('Y-m-d');
            if ($a->hasField('WorkerID'))     $a->WorkerID     = $worker->ID;
            if ($a->hasField('RevirID'))      $a->RevirID      = $revir->ID;
            $a->write();
            $count++;
        }

        $this->out->writeln("  created {$count} assignments");
    }

    // ─── Time Tracking ────────────────────────────────────────────────────────

    private function seedTimeTracking(): void
    {
        $this->out->writeln('Creating time tracking entries...');

        if (!class_exists(self::CLASS_TIME_TRACKING) || !class_exists(self::CLASS_WORKER_ASSIGN)) {
            $this->out->writeln('  SKIP   TimeTracking or WorkerAssignment class not found');
            return;
        }

        $ttClass    = self::CLASS_TIME_TRACKING;
        $assignClass = self::CLASS_WORKER_ASSIGN;
        $monday     = new DateTime('monday this week');
        $count      = 0;

        foreach ([
            ['worker1', 'erdgeschoss',   '07:05', '09:58'],
            ['worker2', 'obergeschoss1', '07:02', '10:03'],
            ['worker3', 'einkauf_west',  '06:04', '09:31'],
        ] as [$workerKey, $revirKey, $startH, $endH]) {
            $worker = $this->members[$workerKey] ?? null;
            $revir  = $this->revirs[$revirKey] ?? null;

            if (!$worker || !$revir) {
                continue;
            }

            foreach ([0, 1] as $dayOffset) {
                $date = (clone $monday)->modify("+{$dayOffset} days")->format('Y-m-d');

                $assignment = $assignClass::get()->filter([
                    'WorkerID'     => $worker->ID,
                    'RevirID'      => $revir->ID,
                    'AssignedDate' => $date,
                ])->first();

                /** @var \SilverStripe\ORM\DataObject $tt */
                $tt = $ttClass::create();
                if ($tt->hasField('WorkerID'))  $tt->WorkerID  = $worker->ID;
                if ($tt->hasField('RevirID'))   $tt->RevirID   = $revir->ID;
                if ($tt->hasField('StartTime')) $tt->StartTime = "{$date} {$startH}:00";
                if ($tt->hasField('EndTime'))   $tt->EndTime   = "{$date} {$endH}:00";
                if ($tt->hasField('Status'))    $tt->Status    = 'Completed';
                if ($assignment && $tt->hasField('AssignmentID')) {
                    $tt->AssignmentID = $assignment->ID;
                }
                $tt->write();
                $count++;
            }
        }

        $this->out->writeln("  created {$count} time tracking entries");
    }

    // ─── Check-ins ────────────────────────────────────────────────────────────

    private function seedCheckIns(): void
    {
        $this->out->writeln('Creating QR check-ins...');

        $worker = $this->members['worker1'] ?? null;
        $revir  = $this->revirs['erdgeschoss'] ?? null;

        if (!$worker || !$revir) {
            $this->out->writeln('  SKIP — missing worker1 or erdgeschoss revir');
            return;
        }

        $monday     = new DateTime('monday this week');
        $assignment = null;

        if (class_exists(self::CLASS_WORKER_ASSIGN)) {
            $assignment = (self::CLASS_WORKER_ASSIGN)::get()->filter([
                'WorkerID'     => $worker->ID,
                'RevirID'      => $revir->ID,
                'AssignedDate' => $monday->format('Y-m-d'),
            ])->first();
        }

        $ci            = RevirCheckIn::create();
        $ci->ScannedAt = $monday->format('Y-m-d') . ' 07:06:23';
        $ci->IPAddress = '192.168.1.42';
        $ci->UserAgent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)';
        $ci->Latitude  = 52.516275;
        $ci->Longitude = 13.377704;
        if ($ci->hasField('WorkerID')) $ci->WorkerID = $worker->ID;
        if ($ci->hasField('RevirID'))  $ci->RevirID  = $revir->ID;
        if ($assignment && $ci->hasField('AssignmentID')) {
            $ci->AssignmentID = $assignment->ID;
        }
        $ci->write();

        $this->out->writeln('  check-in: Anna Müller @ Bürohaus Nord EG, Monday');
    }

    // ─── Swap Requests ────────────────────────────────────────────────────────

    private function seedSwapRequests(): void
    {
        $this->out->writeln('Creating shift swap request...');

        if (!class_exists(self::CLASS_SHIFT_SWAP) || !class_exists(self::CLASS_WORKER_ASSIGN)) {
            $this->out->writeln('  SKIP   ShiftSwapRequest or WorkerAssignment class not found');
            return;
        }

        $swapClass   = self::CLASS_SHIFT_SWAP;
        $assignClass = self::CLASS_WORKER_ASSIGN;

        $w1 = $this->members['worker1'] ?? null;
        $w2 = $this->members['worker2'] ?? null;

        if (!$w1 || !$w2) {
            $this->out->writeln('  SKIP — need at least 2 workers');
            return;
        }

        $wednesday = (new DateTime('monday this week'))->modify('+2 days');
        $dateStr   = $wednesday->format('Y-m-d');

        $a1 = $assignClass::get()->filter(['WorkerID' => $w1->ID, 'AssignedDate' => $dateStr])->first();
        $a2 = $assignClass::get()->filter(['WorkerID' => $w2->ID, 'AssignedDate' => $dateStr])->first();

        if (!$a1 || !$a2) {
            $this->out->writeln('  SKIP — no assignments found for Wednesday');
            return;
        }

        /** @var \SilverStripe\ORM\DataObject $swap */
        $swap         = $swapClass::create();
        $swap->Status = 'Pending';
        $swap->Reason = 'Privatni termin — liječnik. Molim zamjenu sa Thomasom.';
        if ($swap->hasField('RequestedByID'))   $swap->RequestedByID   = $w1->ID;
        if ($swap->hasField('RequestedToID'))   $swap->RequestedToID   = $w2->ID;
        if ($swap->hasField('OriginalShiftID')) $swap->OriginalShiftID = $a1->ID;
        if ($swap->hasField('SwapShiftID'))     $swap->SwapShiftID     = $a2->ID;
        $swap->write();

        $this->out->writeln('  swap: Anna Müller ↔ Thomas Weber (Wednesday)');
    }

    // ─── Client Ratings ───────────────────────────────────────────────────────

    private function seedClientRatings(): void
    {
        $this->out->writeln('Creating client ratings...');

        if (!class_exists(self::CLASS_CLIENT_RATING)) {
            $this->out->writeln('  SKIP   ClientRating class not found');
            return;
        }

        $class  = self::CLASS_CLIENT_RATING;
        $client = $this->members['client'] ?? null;

        if (!$client) {
            $this->out->writeln('  SKIP — no client member');
            return;
        }

        $defs = [
            ['erdgeschoss',  5, 'Sehr sauber und pünktlich! Frau Müller macht einen ausgezeichneten Job.', 3],
            ['einkauf_west', 4, 'Gute Arbeit, nur eine kleine Stelle beim Eingang wurde übersehen.', 5],
        ];

        foreach ($defs as [$revirKey, $stars, $comment, $daysAgo]) {
            $revir = $this->revirs[$revirKey] ?? null;
            if (!$revir) {
                continue;
            }

            $date = (new DateTime("-{$daysAgo} days"))->format('Y-m-d H:i:s');

            /** @var \SilverStripe\ORM\DataObject $rating */
            $rating = $class::create();
            if ($rating->hasField('ClientID')) $rating->ClientID = $client->ID;
            if ($rating->hasField('RevirID'))  $rating->RevirID  = $revir->ID;
            if ($rating->hasField('Rating'))   $rating->Rating   = $stars;
            if ($rating->hasField('Comment'))  $rating->Comment  = $comment;
            if ($rating->hasField('RatedAt'))  $rating->RatedAt  = $date;
            $rating->write();

            $this->out->writeln("  RATING {$stars}★ — {$revir->Name}");
        }
    }
}
