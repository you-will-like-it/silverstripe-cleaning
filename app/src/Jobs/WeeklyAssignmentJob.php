<?php

declare(strict_types=1);

namespace YouWillLikeIT\Cleaning\Jobs;

use YouWillLikeIT\Cleaning\DataObjects\CleaningSchedule;
use YouWillLikeIT\Cleaning\DataObjects\WorkerAssignment;
use YouWillLikeIT\Cleaning\Services\NotificationService;
use YouWillLikeIT\Cleaning\Services\SicknessService;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;

/**
 * QueuedJob version of GenerateWeeklyAssignmentsTask.
 *
 * Requires: symbiote/silverstripe-queuedjobs
 *   composer require symbiote/silverstripe-queuedjobs
 *
 * Schedule on boot via _config or extension:
 *   $nextSunday = new \DateTime('next sunday 22:00');
 *   QueuedJobService::singleton()->queueJob(
 *       new WeeklyAssignmentJob(),
 *       $nextSunday->format('Y-m-d H:i:s')
 *   );
 *
 * The job re-queues itself for the following Sunday on completion.
 */
class WeeklyAssignmentJob extends AbstractQueuedJob implements QueuedJob
{
    /** How many weeks ahead to generate assignments for. */
    private const WEEK_OFFSET = 1;

    /** Day-of-week map: PHP date('N') → schedule day abbreviation */
    private const DOW_MAP = [
        1 => 'Mon',
        2 => 'Tue',
        3 => 'Wed',
        4 => 'Thu',
        5 => 'Fri',
        6 => 'Sat',
        7 => 'Sun',
    ];

    public function getTitle(): string
    {
        return 'Weekly Assignment Generator';
    }

    public function getJobType(): int
    {
        return QueuedJob::QUEUED;
    }

    public function setup(): void
    {
        $this->totalSteps = 1;
    }

    public function process(): void
    {
        $this->addMessage('Starting weekly assignment generation…');

        $monday = new \DateTime('monday this week midnight');
        $monday->modify('+' . self::WEEK_OFFSET . ' week');
        $sunday = clone $monday;
        $sunday->modify('+6 days');

        $this->addMessage(sprintf(
            'Target week: %s → %s',
            $monday->format('Y-m-d'),
            $sunday->format('Y-m-d')
        ));

        $schedules = CleaningSchedule::get()->filter('IsActive', true);
        $created = $skipped = $errors = 0;

        foreach ($schedules as $schedule) {
            $days   = $schedule->getDaysArray();
            $object = $schedule->CleaningObject();
            if (!$object || !$object->IsActive) {
                continue;
            }

            foreach ($object->Revirs()->filter('IsActive', true) as $revir) {
                $worker = $revir->AssignedWorker();
                if (!$worker || !$worker->exists()) {
                    $skipped++;
                    continue;
                }

                $current = clone $monday;
                while ($current <= $sunday) {
                    $dow     = (int) $current->format('N');
                    $dayName = self::DOW_MAP[$dow];
                    $dateStr = $current->format('Y-m-d');

                    if (in_array($dayName, $days, true)) {
                        $result = $this->processOneDay($revir, $worker, $schedule, $dateStr);
                        match ($result) {
                            'created' => $created++,
                            'skipped' => $skipped++,
                            default   => $errors++,
                        };
                    }

                    $current->modify('+1 day');
                }
            }
        }

        $this->addMessage(sprintf(
            'Completed. Created: %d, Skipped: %d, Errors: %d',
            $created,
            $skipped,
            $errors
        ));

        $this->currentStep = 1;
        $this->isComplete  = true;

        // Re-queue for next Sunday at 22:00
        $this->requeueForNextWeek();
    }

    /**
     * @return 'created'|'skipped'|'error'
     */
    private function processOneDay(
        \YouWillLikeIT\Cleaning\DataObjects\Revir $revir,
        \SilverStripe\Security\Member   $worker,
        CleaningSchedule                $schedule,
        string                          $date
    ): string {
        $exists = WorkerAssignment::get()->filter([
            'WorkerID'     => $worker->ID,
            'RevirID'      => $revir->ID,
            'AssignedDate' => $date,
        ])->exists();

        if ($exists) {
            return 'skipped';
        }

        if (SicknessService::create()->isWorkerAbsent((int) $worker->ID, $date)) {
            return 'skipped';
        }

        try {
            $assignment                    = WorkerAssignment::create();
            $assignment->WorkerID          = (int) $worker->ID;
            $assignment->RevirID           = (int) $revir->ID;
            $assignment->CleaningScheduleID = (int) $schedule->ID;
            $assignment->AssignedDate      = $date;
            $assignment->Status            = 'Pending';
            $assignment->Notes             = "Auto-generated from Schedule #{$schedule->ID}";
            $assignment->write();

            NotificationService::create()->sendAssignmentNotification($assignment);

            return 'created';
        } catch (\Throwable $e) {
            $this->addMessage("ERROR: {$e->getMessage()}");
            return 'error';
        }
    }

    private function requeueForNextWeek(): void
    {
        $nextSunday = new \DateTime('next sunday 22:00');

        QueuedJobService::singleton()->queueJob(
            new WeeklyAssignmentJob(),
            $nextSunday->format('Y-m-d H:i:s')
        );

        $this->addMessage('Re-queued for: ' . $nextSunday->format('Y-m-d H:i:s'));
    }
}
