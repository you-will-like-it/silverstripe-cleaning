<?php

declare(strict_types=1);

namespace YouWillLikeIT\Cleaning\Controllers;

use YouWillLikeIT\Cleaning\DataObjects\Revir;
use YouWillLikeIT\Cleaning\DataObjects\RevirCheckIn;
use YouWillLikeIT\Cleaning\DataObjects\ShiftSwapRequest;
use YouWillLikeIT\Cleaning\DataObjects\WorkerAssignment;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SilverStripe\View\ArrayData;

/**
 * Area Manager Portal — a focused read-only dashboard for area managers.
 *
 * Routes:
 *   GET  /manager/                → index() — today overview
 *   GET  /manager/workers         → workers() — all workers in manager's area
 *   GET  /manager/revirs          → revirs()  — revir status + check-ins today
 *   GET  /manager/swaps           → swaps()   — open swap requests
 *   POST /manager/approveSwap     → approveSwap() (AJAX)
 *   GET  /manager/minijob         → minijob() — workers near 450 € threshold
 *   GET  /manager/api/summary     → apiSummary() — JSON summary for dashboard
 */
class ManagerPortalController extends Controller
{
    private static string $url_segment = 'manager';

    /** @var array<string,string> */
    private static array $allowed_actions = [
        'index',
        'workers',
        'revirs',
        'swaps',
        'approveSwap',
        'minijob',
        'apiSummary',
    ];

    // ── Bootstrap ─────────────────────────────────────────────────────────

    protected function init(): void
    {
        parent::init();

        $user = Security::getCurrentUser();
        if (!$user) {
            $this->redirect(Security::login_url() . '?BackURL=/manager/');
            return;
        }

        if (!Permission::checkMember($user, ['AREA_MANAGER', 'COMPANY_ADMIN', 'SUPER_ADMIN', 'ADMIN'])) {
            $this->httpError(403, 'Zugriff verweigert');
        }
    }

    // ── Overview ──────────────────────────────────────────────────────────

    public function index(HTTPRequest $request): string
    {
        $today   = date('Y-m-d');
        $manager = Security::getCurrentUser();

        $assignments = $this->getTodayAssignments($manager);
        $pendingSwaps = $this->getPendingSwaps($manager);
        $checkIns    = $this->getTodayCheckIns($manager);

        return $this->customise([
            'Title'          => 'Manager Portal — ' . date('d.m.Y'),
            'TodayDate'      => $today,
            'TodayCount'     => $assignments->count(),
            'CheckInCount'   => $checkIns->count(),
            'PendingSwaps'   => $pendingSwaps->count(),
            'Assignments'    => $assignments,
            'CheckIns'       => $checkIns,
            'RecentSwaps'    => $pendingSwaps,
        ])->renderWith(['ManagerPortal', 'Page']);
    }

    // ── Workers list ──────────────────────────────────────────────────────

    public function workers(HTTPRequest $request): string
    {
        $manager = Security::getCurrentUser();
        $workers = $this->getAreaWorkers($manager);

        // Annotate with today's assignment status
        $annotated = ArrayList::create();
        foreach ($workers as $worker) {
            $todayAssignment = WorkerAssignment::get()->filter([
                'WorkerID'     => $worker->ID,
                'AssignedDate' => date('Y-m-d'),
            ])->first();

            $annotated->push(ArrayData::create([
                'Worker'          => $worker,
                'TodayAssignment' => $todayAssignment,
                'HasCheckedIn'    => RevirCheckIn::get()->filter([
                    'WorkerID'  => $worker->ID,
                    'ScannedAt:GreaterThanOrEqual' => date('Y-m-d') . ' 00:00:00',
                ])->exists(),
            ]));
        }

        return $this->customise([
            'Title'   => 'Mitarbeiter',
            'Workers' => $annotated,
        ])->renderWith(['ManagerPortalWorkers', 'Page']);
    }

    // ── Revir status ──────────────────────────────────────────────────────

    public function revirs(HTTPRequest $request): string
    {
        $manager = Security::getCurrentUser();
        $revirs  = $this->getAreaRevirs($manager);
        $today   = date('Y-m-d');

        $annotated = ArrayList::create();
        foreach ($revirs as $revir) {
            $assignment = WorkerAssignment::get()->filter([
                'RevirID'      => $revir->ID,
                'AssignedDate' => $today,
            ])->first();

            $lastCheckIn = RevirCheckIn::get()->filter([
                'RevirID'   => $revir->ID,
                'ScannedAt:GreaterThanOrEqual' => $today . ' 00:00:00',
            ])->sort('ScannedAt DESC')->first();

            $annotated->push(ArrayData::create([
                'Revir'          => $revir,
                'Assignment'     => $assignment,
                'Worker'         => $assignment?->Worker(),
                'LastCheckIn'    => $lastCheckIn,
                'CheckedIn'      => $lastCheckIn !== null,
                'StatusClass'    => $this->revirStatusClass($assignment, $lastCheckIn),
            ]));
        }

        return $this->customise([
            'Title'  => 'Revir-Status heute',
            'Revirs' => $annotated,
        ])->renderWith(['ManagerPortalRevirs', 'Page']);
    }

    // ── Shift swaps ───────────────────────────────────────────────────────

    public function swaps(HTTPRequest $request): string
    {
        $manager = Security::getCurrentUser();
        $swaps   = $this->getPendingSwaps($manager);

        return $this->customise([
            'Title' => 'Offene Tauschanfragen',
            'Swaps' => $swaps,
        ])->renderWith(['ManagerPortalSwaps', 'Page']);
    }

    /**
     * AJAX: Approve or reject a swap request.
     */
    public function approveSwap(HTTPRequest $request): HTTPResponse
    {
        $this->validateCsrf($request);

        $swapID = (int) $request->postVar('swap_id');
        $action = $request->postVar('action') === 'reject' ? 'Rejected' : 'Approved';

        $swap = ShiftSwapRequest::get()->byID($swapID);
        if (!$swap) {
            return $this->jsonResponse(['success' => false, 'error' => 'Not found'], 404);
        }

        if (!$swap->isPending()) {
            return $this->jsonResponse(['success' => false, 'error' => 'Already processed'], 409);
        }

        $swap->Status = $action;
        $swap->write();

        return $this->jsonResponse(['success' => true, 'status' => $action]);
    }

    // ── Minijob risk ──────────────────────────────────────────────────────

    public function minijob(HTTPRequest $request): string
    {
        $manager = Security::getCurrentUser();
        $workers = $this->getAreaWorkers($manager);
        $month   = (int) date('m');
        $year    = (int) date('Y');

        $atRisk  = ArrayList::create();
        foreach ($workers as $worker) {
            $hours = \YouWillLikeIT\Cleaning\DataObjects\TimeTracking::getMonthlyHours(
                (int) $worker->ID,
                $year,
                $month
            );
            $earnings  = $hours * (float) ($worker->HourlyRate ?? 12.41);
            $threshold = (float) ($worker->MinijobLimit ?? 538.0);
            $pct       = $threshold > 0 ? round($earnings / $threshold * 100, 1) : 0;

            if ($pct >= 75.0) {
                $atRisk->push(ArrayData::create([
                    'Worker'    => $worker,
                    'Hours'     => round($hours, 2),
                    'Earnings'  => round($earnings, 2),
                    'Threshold' => $threshold,
                    'Percent'   => $pct,
                    'Critical'  => $pct >= 90,
                ]));
            }
        }

        return $this->customise([
            'Title'  => 'Minijob-Überwachung',
            'Month'  => date('F Y'),
            'AtRisk' => $atRisk,
        ])->renderWith(['ManagerPortalMinijob', 'Page']);
    }

    // ── JSON API ──────────────────────────────────────────────────────────

    public function apiSummary(HTTPRequest $request): HTTPResponse
    {
        $manager     = Security::getCurrentUser();
        $assignments = $this->getTodayAssignments($manager);
        $checkIns    = $this->getTodayCheckIns($manager);
        $pendingSwaps = $this->getPendingSwaps($manager);

        return $this->jsonResponse([
            'today'           => date('Y-m-d'),
            'assignments'     => $assignments->count(),
            'check_ins'       => $checkIns->count(),
            'pending_swaps'   => $pendingSwaps->count(),
            'coverage_pct'    => $assignments->count() > 0
                ? round($checkIns->count() / $assignments->count() * 100)
                : 0,
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────

    private function getTodayAssignments(Member $manager): DataList
    {
        $filter = ['AssignedDate' => date('Y-m-d')];

        if (!Permission::checkMember($manager, ['COMPANY_ADMIN', 'SUPER_ADMIN', 'ADMIN'])) {
            $workerIDs = $this->getAreaWorkerIDs($manager);
            if (empty($workerIDs)) {
                return WorkerAssignment::get()->filter('ID', 0);
            }
            $filter['WorkerID'] = $workerIDs;
        }

        return WorkerAssignment::get()->filter($filter);
    }

    private function getTodayCheckIns(Member $manager): DataList
    {
        $filter = ['ScannedAt:GreaterThanOrEqual' => date('Y-m-d') . ' 00:00:00'];

        if (!Permission::checkMember($manager, ['COMPANY_ADMIN', 'SUPER_ADMIN', 'ADMIN'])) {
            $workerIDs = $this->getAreaWorkerIDs($manager);
            if (empty($workerIDs)) {
                return RevirCheckIn::get()->filter('ID', 0);
            }
            $filter['WorkerID'] = $workerIDs;
        }

        return RevirCheckIn::get()->filter($filter)->sort('ScannedAt DESC');
    }

    private function getPendingSwaps(Member $manager): DataList
    {
        $filter = ['Status' => 'Pending'];

        if (!Permission::checkMember($manager, ['COMPANY_ADMIN', 'SUPER_ADMIN', 'ADMIN'])) {
            $workerIDs = $this->getAreaWorkerIDs($manager);
            if (empty($workerIDs)) {
                return ShiftSwapRequest::get()->filter('ID', 0);
            }
            $filter['RequesterID'] = $workerIDs;
        }

        return ShiftSwapRequest::get()->filter($filter)->sort('Created DESC');
    }

    private function getAreaWorkers(Member $manager): DataList
    {
        if (Permission::checkMember($manager, ['COMPANY_ADMIN', 'SUPER_ADMIN', 'ADMIN'])) {
            $group = Group::get()->filter('Code', 'workers')->first();
            return $group ? $group->Members() : Member::get()->filter('ID', 0);
        }

        $workerIDs = $this->getAreaWorkerIDs($manager);
        return empty($workerIDs)
            ? Member::get()->filter('ID', 0)
            : Member::get()->filter('ID', $workerIDs);
    }

    private function getAreaRevirs(Member $manager): DataList
    {
        // AREA_MANAGER sees revirs associated with their workers
        if (Permission::checkMember($manager, ['COMPANY_ADMIN', 'SUPER_ADMIN', 'ADMIN'])) {
            return Revir::get()->filter('IsActive', true);
        }

        $workerIDs = $this->getAreaWorkerIDs($manager);
        if (empty($workerIDs)) {
            return Revir::get()->filter('ID', 0);
        }

        // Revirs where the assigned worker is in our area
        return Revir::get()->filter([
            'AssignedWorkerID' => $workerIDs,
            'IsActive'         => true,
        ]);
    }

    /** @return list<int> */
    private function getAreaWorkerIDs(Member $manager): array
    {
        // Workers managed by this area manager: Members in 'workers' group
        // whose AreaManagerID matches.
        // Falls back to all workers if the field doesn't exist.
        $group = Group::get()->filter('Code', 'workers')->first();
        if (!$group) {
            return [];
        }

        $members = $group->Members()->filter('AreaManagerID', $manager->ID);
        return $members->column('ID') ?: [];
    }

    private function revirStatusClass(?WorkerAssignment $assignment, ?RevirCheckIn $checkIn): string
    {
        if ($assignment === null) {
            return 'status--unassigned';
        }
        if ($checkIn !== null) {
            return 'status--checked-in';
        }
        if (in_array($assignment->Status, ['Pending', 'Confirmed'], true)) {
            return 'status--pending';
        }
        return 'status--absent';
    }

    private function validateCsrf(HTTPRequest $request): void
    {
        $token = $request->getHeader('X-CSRF-Token')
            ?? $request->postVar('SecurityID')
            ?? '';

        if (!\SilverStripe\Security\SecurityToken::inst()->check($token)) {
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
