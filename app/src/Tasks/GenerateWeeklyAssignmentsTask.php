<?php

declare(strict_types=1);

namespace YouWillLikeIT\Cleaning\Tasks;

use YouWillLikeIT\Cleaning\DataObjects\CleaningSchedule;
use YouWillLikeIT\Cleaning\DataObjects\Revir;
use YouWillLikeIT\Cleaning\DataObjects\WorkerAssignment;
use YouWillLikeIT\Cleaning\Services\NotificationService;
use YouWillLikeIT\Cleaning\Services\SicknessService;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Generates WorkerAssignment records for the upcoming week
 * based on active CleaningSchedule entries.
 *
 * Run manually:
 *   vendor/bin/sake dev/tasks/GenerateWeeklyAssignmentsTask
 *
 * Optional GET params:
 *   ?week_offset=1   Generate for 1 week from now (default: 1)
 *   ?dry_run=1       Log what would be created without writing
 *
 * Typically scheduled via cron every Sunday night:
 *   0 22 * * 0 /path/to/vendor/bin/sake dev/tasks/GenerateWeeklyAssignmentsTask
 *
 * Or via QueuedJob (see WeeklyAssignmentJob).
 *
 * Idempotent: skips dates where an assignment for the same
 * Worker + Revir + Date already exists.
 */
class GenerateWeeklyAssignmentsTask extends BuildTask
{
    protected string $title = 'Generate Weekly Assignments';
    protected static string $description = 'Creates WorkerAssignment records for the next week based on CleaningSchedule entries.';
    private PolyOutput $output;
    /** Day-of-week map: PHP date('N') → schedule day names */
    private const DOW_MAP = [
        1 => 'Mon',
        2 => 'Tue',
        3 => 'Wed',
        4 => 'Thu',
        5 => 'Fri',
        6 => 'Sat',
        7 => 'Sun',
    ];

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $this->output = $output;
        $weekOffset = (int) ($input->getOption('week_offset') ?? 1);
        $dryRun     = (bool) $input->getOption('dry_run');
        $weekOffset = max(0, min(4, $weekOffset)); // clamp 0–4

        $monday = new \DateTime('monday this week midnight');
        $monday->modify("+{$weekOffset} week");
        $sunday = clone $monday;
        $sunday->modify('+6 days');

        $this->log(sprintf(
            'Generating assignments for %s → %s%s',
            $monday->format('Y-m-d'),
            $sunday->format('Y-m-d'),
            $dryRun ? ' [DRY RUN]' : ''
        ));

        $schedules = CleaningSchedule::get()->filter('IsActive', true);
        $created   = 0;
        $skipped   = 0;
        $errors    = 0;

        foreach ($schedules as $schedule) {
            $days      = $schedule->getDaysArray(); // ['Mon','Wed','Fri']
            $object    = $schedule->CleaningObject();
            if (!$object || !$object->IsActive) {
                continue;
            }

            $revirs = $object->Revirs()->filter('IsActive', true);

            foreach ($revirs as $revir) {
                $worker = $revir->AssignedWorker();
                if (!$worker || !$worker->exists()) {
                    $this->log("  SKIP  Revir #{$revir->ID} '{$revir->Name}' — no assigned worker");
                    $skipped++;
                    continue;
                }

                // Walk each day of the target week
                $current = clone $monday;
                while ($current <= $sunday) {
                    $dow      = (int) $current->format('N'); // 1=Mon … 7=Sun
                    $dayName  = self::DOW_MAP[$dow];
                    $dateStr  = $current->format('Y-m-d');

                    if (in_array($dayName, $days, true)) {
                        $result = $this->createAssignment(
                            $revir,
                            $worker,
                            $schedule,
                            $dateStr,
                            $dryRun
                        );

                        if ($result === 'created') {
                            $created++;
                        } elseif ($result === 'skipped') {
                            $skipped++;
                        } else {
                            $errors++;
                        }
                    }

                    $current->modify('+1 day');
                }
            }
        }

        $this->log(sprintf(
            'Done. Created: %d, Skipped (exists/absent): %d, Errors: %d',
            $created,
            $skipped,
            $errors
        ));

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    public function getOptions(): array
    {
        return [
            new InputOption('week_offset', null, InputOption::VALUE_REQUIRED, 'Weeks from now (0-4)', 1),
            new InputOption('dry_run', null, InputOption::VALUE_NONE, 'Log without writing'),
        ];
    }

    /**
     * Create one WorkerAssignment if it doesn't already exist.
     *
     * @return 'created'|'skipped'|'error'
     */
    private function createAssignment(
        Revir            $revir,
        \SilverStripe\Security\Member $worker,
        CleaningSchedule $schedule,
        string           $date,
        bool             $dryRun
    ): string {
        // Idempotency check
        $exists = WorkerAssignment::get()->filter([
            'WorkerID'     => $worker->ID,
            'RevirID'      => $revir->ID,
            'AssignedDate' => $date,
        ])->exists();

        if ($exists) {
            return 'skipped';
        }

        // Skip if worker has reported sick/absent that day
        $absent = SicknessService::create()->isWorkerAbsent((int) $worker->ID, $date);
        if ($absent) {
            $this->log("  ABSENT {$worker->getName()} on {$date} — skipping Revir {$revir->Name}");
            return 'skipped';
        }

        if ($dryRun) {
            $this->log("  [DRY]  Would create: {$worker->getName()} → {$revir->Name} on {$date}");
            return 'created';
        }

        try {
            $assignment = WorkerAssignment::create();
            $assignment->WorkerID          = (int) $worker->ID;
            $assignment->RevirID           = (int) $revir->ID;
            $assignment->CleaningScheduleID = (int) $schedule->ID;
            $assignment->AssignedDate      = $date;
            $assignment->Status            = 'Pending';
            $assignment->Notes             = sprintf(
                'Auto-generated from Schedule #%d (%s)',
                $schedule->ID,
                $schedule->getFormattedDays()
            );
            $assignment->write();

            NotificationService::create()->sendAssignmentNotification($assignment);

            $this->log("  CREATED {$worker->getName()} → {$revir->Name} on {$date}");
            return 'created';
        } catch (\Throwable $e) {
            $this->log("  ERROR  {$worker->getName()} → {$revir->Name} on {$date}: " . $e->getMessage());
            return 'error';
        }
    }

    private function log(string $msg): void
    {
        $this->output->writeln($msg);
    }
}
