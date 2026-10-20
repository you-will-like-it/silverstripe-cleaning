<?php

declare(strict_types=1);

namespace YouWillLikeIT\Cleaning\Controllers;

use YouWillLikeIT\Cleaning\DataObjects\RevirCheckIn;
use YouWillLikeIT\Cleaning\DataObjects\Revir;
use YouWillLikeIT\Cleaning\DataObjects\ShiftSwapRequest;
use YouWillLikeIT\Cleaning\DataObjects\TimeTracking;
use YouWillLikeIT\Cleaning\DataObjects\WorkerAssignment;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SilverStripe\Security\SecurityToken;
use SilverStripe\View\ArrayData;

/**
 * Worker Portal — a mobile-friendly portal for cleaning workers.
 *
 * Routes:
 *   GET  /portal/                  → index()       — today's assignments + quick status
 *   GET  /portal/schedule          → schedule()    — weekly schedule overview
 *   GET  /portal/checkin/{token}   → checkin()     — QR code scan → check-in
 *   POST /portal/clockIn           → clockIn()     — start time tracking
 *   POST /portal/clockOut          → clockOut()    — stop time tracking
 *   GET  /portal/swaps             → swaps()       — my swap requests
 *   POST /portal/requestSwap       → requestSwap() — create a swap request
 *   GET  /portal/profile           → profile()     — worker profile + hours summary
 */
class WorkerPortalController extends Controller
{
    private static string $url_segment = 'portal';

    /** @var array<string,bool> */
    private static array $allowed_actions = [
        'index'        => true,
        'schedule'     => true,
        'checkin'      => true,
        'clockIn'      => true,
        'clockOut'     => true,
        'swaps'        => true,
        'requestSwap'  => true,
        'profile'      => true,
    ];

    /** @var array<int,string> */
    private static array $url_handlers = [
        'checkin/$Token' => 'checkin',
    ];

    // ── Bootstrap ─────────────────────────────────────────────────────────

    protected function init(): void
    {
        parent::init();

        $user = Security::getCurrentUser();
        if (!$user) {
            $this->redirect(Security::login_url() . '?BackURL=/portal/');
            return;
        }

        if (!Permission::checkMember($user, ['WORKER', 'AREA_MANAGER', 'COMPANY_ADMIN', 'SUPER_ADMIN', 'ADMIN'])) {
            $this->httpError(403, 'Zugriff verweigert');
        }
    }

    // ── Dashboard ─────────────────────────────────────────────────────────

    public function index(HTTPRequest $request): DBHTMLText
    {
        $worker = Security::getCurrentUser();
        $today  = date('Y-m-d');

        $todayAssignments = WorkerAssignment::get()->filter([
            'WorkerID'     => $worker->ID,
            'AssignedDate' => $today,
        ]);

        $activeEntry = TimeTracking::get()->filter([
            'WorkerID' => $worker->ID,
            'Status'   => 'Pending',
            'EndTime'  => null,
        ])->sort('StartTime DESC')->first();

        $pendingSwaps = ShiftSwapRequest::get()->filter([
            'RequestedByID' => $worker->ID,
            'Status'        => 'Pending',
        ])->count();

        return $this->customise([
            'Title'            => 'Mein Arbeitsportal — ' . date('d.m.Y'),
            'TodayDate'        => $today,
            'TodayAssignments' => $todayAssignments,
            'ActiveTimeEntry'  => $activeEntry,
            'PendingSwaps'     => $pendingSwaps,
        ])->renderWith(['WorkerPortal', 'Page']);
    }

    // ── Weekly schedule ───────────────────────────────────────────────────

    public function schedule(HTTPRequest $request): DBHTMLText
    {
        $worker = Security::getCurrentUser();

        // Show current week + next week
        $monday = new \DateTime('monday this week midnight');
        $twoWeeks = clone $monday;
        $twoWeeks->modify('+13 days');

        $assignments = WorkerAssignment::get()->filter([
            'WorkerID'                    => $worker->ID,
            'AssignedDate:GreaterThanOrEqual' => $monday->format('Y-m-d'),
            'AssignedDate:LessThanOrEqual'    => $twoWeeks->format('Y-m-d'),
        ])->sort('AssignedDate ASC');

        return $this->customise([
            'Title'       => 'Mein Dienstplan',
            'Assignments' => $assignments,
            'WeekFrom'    => $monday->format('d.m.Y'),
            'WeekTo'      => $twoWeeks->format('d.m.Y'),
        ])->renderWith(['WorkerPortalSchedule', 'Page']);
    }

    // ── QR check-in ───────────────────────────────────────────────────────

    public function checkin(HTTPRequest $request): DBHTMLText
    {
        $token  = (string) $request->param('Token');
        $worker = Security::getCurrentUser();

        if (empty($token)) {
            $this->httpError(400, 'Kein Token angegeben');
        }

        $revir = Revir::get()->filter('QRToken', $token)->first();
        if (!$revir) {
            return $this->customise([
                'Title'   => 'Check-in fehlgeschlagen',
                'Error'   => 'Unbekannter QR-Code. Bitte wenden Sie sich an Ihren Vorgesetzten.',
                'Success' => false,
            ])->renderWith(['WorkerPortalCheckin', 'Page']);
        }

        // Check that worker has an assignment for this revir today
        $assignment = WorkerAssignment::get()->filter([
            'WorkerID'     => $worker->ID,
            'RevirID'      => $revir->ID,
            'AssignedDate' => date('Y-m-d'),
        ])->first();

        if (!$assignment) {
            return $this->customise([
                'Title'   => 'Check-in nicht möglich',
                'Error'   => 'Sie haben heute keine Zuweisung für dieses Revir: ' . $revir->Name,
                'Success' => false,
                'Revir'   => $revir,
            ])->renderWith(['WorkerPortalCheckin', 'Page']);
        }

        // Idempotent: do not create a duplicate check-in for today
        $alreadyCheckedIn = RevirCheckIn::get()->filter([
            'WorkerID'                    => $worker->ID,
            'RevirID'                     => $revir->ID,
            'ScannedAt:GreaterThanOrEqual' => date('Y-m-d') . ' 00:00:00',
        ])->exists();

        if (!$alreadyCheckedIn) {
            $checkIn = RevirCheckIn::create();
            $checkIn->WorkerID    = (int) $worker->ID;
            $checkIn->RevirID     = (int) $revir->ID;
            $checkIn->AssignmentID = (int) $assignment->ID;
            $checkIn->ScannedAt   = date('Y-m-d H:i:s');
            $checkIn->write();

            // Update assignment status to Confirmed
            if ($assignment->Status === 'Pending') {
                $assignment->Status = 'Confirmed';
                $assignment->write();
            }
        }

        return $this->customise([
            'Title'      => 'Check-in erfolgreich',
            'Success'    => true,
            'Revir'      => $revir,
            'Assignment' => $assignment,
            'Already'    => $alreadyCheckedIn,
        ])->renderWith(['WorkerPortalCheckin', 'Page']);
    }

    // ── Time tracking ─────────────────────────────────────────────────────

    public function clockIn(HTTPRequest $request): HTTPResponse
    {
        $this->validateCsrf($request);

        $worker       = Security::getCurrentUser();
        $assignmentID = (int) $request->postVar('assignment_id');

        $assignment = WorkerAssignment::get()->filter([
            'ID'       => $assignmentID,
            'WorkerID' => $worker->ID,
        ])->first();

        if (!$assignment) {
            return $this->jsonResponse(['success' => false, 'error' => 'Zuweisung nicht gefunden'], 404);
        }

        // Do not allow double clock-in
        $active = TimeTracking::get()->filter([
            'WorkerID' => $worker->ID,
            'Status'   => 'Pending',
            'EndTime'  => null,
        ])->exists();

        if ($active) {
            return $this->jsonResponse(['success' => false, 'error' => 'Bereits eingestempelt'], 409);
        }

        $entry = TimeTracking::create();
        $entry->WorkerID     = (int) $worker->ID;
        $entry->RevirID      = (int) $assignment->RevirID;
        $entry->AssignmentID = $assignmentID;
        $entry->StartTime    = date('Y-m-d H:i:s');
        $entry->Status       = 'Pending';
        $entry->write();

        return $this->jsonResponse([
            'success'  => true,
            'entry_id' => $entry->ID,
            'start'    => $entry->StartTime,
        ]);
    }

    public function clockOut(HTTPRequest $request): HTTPResponse
    {
        $this->validateCsrf($request);

        $worker  = Security::getCurrentUser();
        $entryID = (int) $request->postVar('entry_id');

        $entry = TimeTracking::get()->filter([
            'ID'       => $entryID,
            'WorkerID' => $worker->ID,
        ])->first();

        if (!$entry) {
            return $this->jsonResponse(['success' => false, 'error' => 'Zeiteintrag nicht gefunden'], 404);
        }

        if ($entry->EndTime) {
            return $this->jsonResponse(['success' => false, 'error' => 'Bereits ausgestempelt'], 409);
        }

        $entry->EndTime = date('Y-m-d H:i:s');
        $entry->write();

        return $this->jsonResponse([
            'success'  => true,
            'duration' => $entry->getDurationMinutes(),
            'end'      => $entry->EndTime,
        ]);
    }

    // ── Shift swaps ───────────────────────────────────────────────────────

    public function swaps(HTTPRequest $request): DBHTMLText
    {
        $worker = Security::getCurrentUser();

        $mySwaps = ShiftSwapRequest::get()->filter([
            'RequestedByID' => $worker->ID,
        ])->sort('Created DESC');

        $swapsToMe = ShiftSwapRequest::get()->filter([
            'RequestedToID' => $worker->ID,
            'Status'        => 'Pending',
        ])->sort('Created DESC');

        return $this->customise([
            'Title'      => 'Tauschanfragen',
            'MySwaps'    => $mySwaps,
            'SwapsToMe'  => $swapsToMe,
        ])->renderWith(['WorkerPortalSwaps', 'Page']);
    }

    public function requestSwap(HTTPRequest $request): HTTPResponse
    {
        $this->validateCsrf($request);

        $worker         = Security::getCurrentUser();
        $myAssignmentID = (int) $request->postVar('my_assignment_id');
        $theirWorkerID  = (int) $request->postVar('their_worker_id');
        $theirAssignID  = (int) $request->postVar('their_assignment_id');

        $myAssignment = WorkerAssignment::get()->filter([
            'ID'       => $myAssignmentID,
            'WorkerID' => $worker->ID,
        ])->first();

        if (!$myAssignment) {
            return $this->jsonResponse(['success' => false, 'error' => 'Eigene Zuweisung nicht gefunden'], 404);
        }

        $swap = ShiftSwapRequest::create();
        $swap->RequestedByID       = (int) $worker->ID;
        $swap->RequestedToID       = $theirWorkerID ?: null;
        $swap->OriginalShiftID     = $myAssignmentID;
        $swap->SwapShiftID         = $theirAssignID ?: null;
        $swap->Status              = 'Pending';
        $swap->write();

        return $this->jsonResponse(['success' => true, 'swap_id' => $swap->ID]);
    }

    // ── Profile ───────────────────────────────────────────────────────────

    public function profile(HTTPRequest $request): DBHTMLText
    {
        $worker = Security::getCurrentUser();
        $month  = (int) date('m');
        $year   = (int) date('Y');

        $monthlyHours   = TimeTracking::getMonthlyHours((int) $worker->ID, $year, $month);
        $totalAssignments = WorkerAssignment::get()->filter('WorkerID', $worker->ID)->count();
        $completedCount   = WorkerAssignment::get()->filter([
            'WorkerID' => $worker->ID,
            'Status'   => 'Completed',
        ])->count();

        return $this->customise([
            'Title'            => 'Mein Profil',
            'Worker'           => $worker,
            'MonthlyHours'     => $monthlyHours,
            'Month'            => date('F Y'),
            'TotalAssignments' => $totalAssignments,
            'CompletedCount'   => $completedCount,
        ])->renderWith(['WorkerPortalProfile', 'Page']);
    }

    // ── Private helpers ───────────────────────────────────────────────────

    private function validateCsrf(HTTPRequest $request): void
    {
        $token = $request->getHeader('X-CSRF-Token')
            ?? $request->postVar('SecurityID')
            ?? '';

        if (!SecurityToken::inst()->check($token)) {
            $this->jsonResponse(['error' => 'Invalid CSRF token'], 403);
            exit;
        }
    }

    private function jsonResponse(array $data, int $status = 200): HTTPResponse
    {
        return HTTPResponse::create()
            ->setStatusCode($status)
            ->addHeader('Content-Type', 'application/json')
            ->setBody((string) json_encode($data));
    }
}
