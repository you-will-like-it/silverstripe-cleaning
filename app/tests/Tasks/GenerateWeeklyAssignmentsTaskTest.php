<?php

declare(strict_types=1);

namespace YouWillLikeIT\Cleaning\Tests\Tasks;

use YouWillLikeIT\Cleaning\DataObjects\CleaningSchedule;
use YouWillLikeIT\Cleaning\DataObjects\Revir;
use YouWillLikeIT\Cleaning\DataObjects\WorkerAssignment;
use YouWillLikeIT\Cleaning\Tasks\GenerateWeeklyAssignmentsTask;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Member;

/**
 * Unit tests for GenerateWeeklyAssignmentsTask.
 *
 * Uses in-memory SQLite3 and fixture YAML.
 * @covers \YouWillLikeIT\Cleaning\Tasks\GenerateWeeklyAssignmentsTask
 */
class GenerateWeeklyAssignmentsTaskTest extends SapphireTest
{
    protected static string $fixture_file = '../Fixtures/WeeklyAssignmentsFixture.yml';

    // ── Tests ─────────────────────────────────────────────────────────────

    public function testAssignmentsCreatedForActiveDays(): void
    {
        $task     = new GenerateWeeklyAssignmentsTask();
        $schedule = $this->objFromFixture(CleaningSchedule::class, 'schedule1');
        $worker   = $this->objFromFixture(Member::class, 'worker1');
        $revir    = $this->objFromFixture(Revir::class, 'revir1');

        // Before: no assignments
        $this->assertEquals(0, WorkerAssignment::get()->filter('WorkerID', $worker->ID)->count());

        // Run task for a specific Monday within fixture's days
        $monday   = new \DateTime('next monday');
        $params   = ['week_offset' => 1, 'dry_run' => 0];
        $task->run($this->buildRequest($params));

        // At least one assignment should have been created
        $created = WorkerAssignment::get()->filter([
            'WorkerID' => $worker->ID,
            'RevirID'  => $revir->ID,
        ])->count();

        $this->assertGreaterThan(0, $created);
    }

    public function testIdempotency(): void
    {
        $task   = new GenerateWeeklyAssignmentsTask();
        $params = ['week_offset' => 1, 'dry_run' => 0];
        $req    = $this->buildRequest($params);

        $task->run($req);
        $after1 = WorkerAssignment::get()->count();

        $task->run($req);
        $after2 = WorkerAssignment::get()->count();

        $this->assertEquals($after1, $after2, 'Second run should not create duplicate assignments');
    }

    public function testDryRunCreatesNoAssignments(): void
    {
        $task   = new GenerateWeeklyAssignmentsTask();
        $params = ['week_offset' => 1, 'dry_run' => 1];
        $task->run($this->buildRequest($params));

        $this->assertEquals(0, WorkerAssignment::get()->count());
    }

    public function testSkipsRevirWithNoWorker(): void
    {
        // revir2 has no AssignedWorker in fixture
        $task   = new GenerateWeeklyAssignmentsTask();
        $revir2 = $this->objFromFixture(Revir::class, 'revir2');
        $task->run($this->buildRequest(['week_offset' => 1, 'dry_run' => 0]));

        $this->assertEquals(0, WorkerAssignment::get()->filter('RevirID', $revir2->ID)->count());
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function buildRequest(array $params): \SilverStripe\Control\HTTPRequest
    {
        $req = new \SilverStripe\Control\HTTPRequest('GET', '/dev/tasks/GenerateWeeklyAssignmentsTask');
        foreach ($params as $k => $v) {
            $req->offsetSet($k, (string) $v);
        }
        return $req;
    }
}
