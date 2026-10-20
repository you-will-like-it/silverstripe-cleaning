<?php

declare(strict_types=1);

namespace YouWillLikeIT\Cleaning\Tests\DataObjects;

use YouWillLikeIT\Cleaning\DataObjects\AssignmentPhoto;
use YouWillLikeIT\Cleaning\DataObjects\WorkerAssignment;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Member;

/**
 * @covers \YouWillLikeIT\Cleaning\DataObjects\AssignmentPhoto
 */
class AssignmentPhotoTest extends SapphireTest
{
    protected static string $fixture_file = '../Fixtures/AssignmentPhotoFixture.yml';

    public function testWorkerCanViewOwnAssignmentPhoto(): void
    {
        $worker  = $this->objFromFixture(Member::class, 'worker1');
        $photo   = $this->objFromFixture(AssignmentPhoto::class, 'photo1');

        $this->logInAs($worker);
        $this->assertTrue($photo->canView($worker));
    }

    public function testOtherWorkerCannotViewPhoto(): void
    {
        $other = $this->objFromFixture(Member::class, 'worker2');
        $photo = $this->objFromFixture(AssignmentPhoto::class, 'photo1');

        $this->logInAs($other);
        $this->assertFalse($photo->canView($other));
    }

    public function testManagerCanViewPhoto(): void
    {
        $manager = $this->objFromFixture(Member::class, 'manager1');
        $photo   = $this->objFromFixture(AssignmentPhoto::class, 'photo1');

        $this->logInAs($manager);
        $this->assertTrue($photo->canView($manager));
    }

    public function testPhotoTypeDefaultIsBefore(): void
    {
        $photo = AssignmentPhoto::create();
        $this->assertEquals('Before', $photo->PhotoType);
    }

    public function testPhotoCannotBeEditedByWorker(): void
    {
        $worker = $this->objFromFixture(Member::class, 'worker1');
        $photo  = $this->objFromFixture(AssignmentPhoto::class, 'photo1');

        $this->logInAs($worker);
        $this->assertFalse($photo->canEdit($worker));
    }

    public function testTakenAtSetOnWrite(): void
    {
        $assignment = $this->objFromFixture(WorkerAssignment::class, 'assign1');
        $worker     = $this->objFromFixture(Member::class, 'worker1');
        $this->logInAs($worker);

        $photo               = AssignmentPhoto::create();
        $photo->AssignmentID = (int) $assignment->ID;
        $photo->PhotoType    = 'After';
        $photo->write();

        $this->assertNotEmpty($photo->TakenAt);
    }
}
