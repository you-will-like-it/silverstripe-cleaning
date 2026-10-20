<?php

declare(strict_types=1);

namespace YouWillLikeIT\Cleaning\Tests\Services;

use YouWillLikeIT\Cleaning\DataObjects\Revir;
use YouWillLikeIT\Cleaning\DataObjects\WorkerAssignment;
use YouWillLikeIT\Cleaning\Services\ICalService;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Member;

/**
 * @covers \YouWillLikeIT\Cleaning\Services\ICalService
 */
class ICalServiceTest extends SapphireTest
{
    protected static string $fixture_file = '../Fixtures/ICalFixture.yml';

    public function testOutputStartsWithVcalendar(): void
    {
        $worker = $this->objFromFixture(Member::class, 'worker1');
        $ics    = ICalService::create()->generateForWorker($worker);

        $this->assertStringStartsWith('BEGIN:VCALENDAR', $ics);
    }

    public function testOutputEndsWithVcalendar(): void
    {
        $worker = $this->objFromFixture(Member::class, 'worker1');
        $ics    = ICalService::create()->generateForWorker($worker);

        $this->assertStringContainsString('END:VCALENDAR', $ics);
    }

    public function testVeventPresentForAssignment(): void
    {
        $worker = $this->objFromFixture(Member::class, 'worker1');
        $ics    = ICalService::create()->generateForWorker($worker);

        $this->assertStringContainsString('BEGIN:VEVENT', $ics);
        $this->assertStringContainsString('END:VEVENT', $ics);
    }

    public function testUidContainsAssignmentId(): void
    {
        $worker     = $this->objFromFixture(Member::class, 'worker1');
        $assignment = $this->objFromFixture(WorkerAssignment::class, 'assign1');
        $ics        = ICalService::create()->generateForWorker($worker);

        $this->assertStringContainsString('UID:assignment-' . $assignment->ID, $ics);
    }

    public function testSummaryContainsRevirName(): void
    {
        $worker = $this->objFromFixture(Member::class, 'worker1');
        $revir  = $this->objFromFixture(Revir::class, 'revir1');
        $ics    = ICalService::create()->generateForWorker($worker);

        $this->assertStringContainsString($revir->Name, $ics);
    }

    public function testDateRangeFilterExcludesPastAssignments(): void
    {
        $worker = $this->objFromFixture(Member::class, 'worker1');
        $ics    = ICalService::create()->generateForWorker(
            $worker,
            '2030-01-01',
            '2030-12-31'
        );

        // No events in this future range
        $this->assertStringNotContainsString('BEGIN:VEVENT', $ics);
    }

    public function testEmptyCalendarForWorkerWithNoAssignments(): void
    {
        $worker = $this->objFromFixture(Member::class, 'worker2');
        $ics    = ICalService::create()->generateForWorker($worker);

        $this->assertStringNotContainsString('BEGIN:VEVENT', $ics);
    }

    public function testLineFoldingAt75Octets(): void
    {
        $worker = $this->objFromFixture(Member::class, 'worker1');
        $ics    = ICalService::create()->generateForWorker($worker);

        foreach (explode("\r\n", $ics) as $line) {
            $this->assertLessThanOrEqual(
                75,
                strlen($line),
                "Line exceeds 75 octets: {$line}"
            );
        }
    }
}
