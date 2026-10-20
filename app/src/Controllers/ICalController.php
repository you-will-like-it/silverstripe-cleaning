<?php

declare(strict_types=1);

namespace YouWillLikeIT\Cleaning\Controllers;

use YouWillLikeIT\Cleaning\Services\ICalService;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;

/**
 * Serves RFC 5545 iCalendar feeds for workers.
 *
 * Routes:
 *   GET /portal/calendar.ics              → current worker's upcoming assignments
 *   GET /portal/calendar.ics?from=Y-m-d   → from custom start date
 *   GET /portal/calendar.ics?to=Y-m-d     → up to custom end date
 *
 * The feed is authenticated via session (same as portal).
 * Workers can add this URL to any calendar app (Google Calendar,
 * Apple Calendar, Outlook) using "Subscribe" / "Import from URL".
 *
 * Area managers and admins can add ?worker_id=N to fetch another worker's feed.
 */
class ICalController extends Controller
{
    private static string $url_segment = 'portal/calendar';

    /** @var array<string,string> */
    private static array $allowed_actions = [
        'ics',
        'index',
    ];

    protected function init(): void
    {
        parent::init();

        $user = Security::getCurrentUser();
        if (!$user) {
            // Calendar clients don't follow redirects well — return 401
            $this->httpError(401, 'Authentication required');
        }
    }

    /**
     * Serve the .ics file.
     * Accessible via /portal/calendar.ics (mapped via routes.yml suffix rule).
     */
    public function index(HTTPRequest $request): HTTPResponse
    {
        return $this->ics($request);
    }

    public function ics(HTTPRequest $request): HTTPResponse
    {
        $currentUser = Security::getCurrentUser();

        // Admins/managers can request another worker's calendar
        $workerID = (int) ($request->getVar('worker_id') ?? 0);
        if ($workerID && $workerID !== (int) $currentUser->ID) {
            if (!Permission::checkMember($currentUser, ['AREA_MANAGER', 'COMPANY_ADMIN', 'SUPER_ADMIN', 'ADMIN'])) {
                return $this->errorResponse(403, 'Forbidden');
            }
            $worker = \SilverStripe\Security\Member::get()->byID($workerID);
            if (!$worker) {
                return $this->errorResponse(404, 'Worker not found');
            }
        } else {
            $worker = $currentUser;
        }

        $from = (string) ($request->getVar('from') ?? '');
        $to   = (string) ($request->getVar('to')   ?? '');

        // Validate date formats if provided
        if ($from && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $from = '';
        }
        if ($to && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $to = '';
        }

        $ics      = ICalService::create()->generateForWorker($worker, $from, $to);
        $filename = 'cleaning-schedule-' . date('Y-m') . '.ics';

        return HTTPResponse::create()
            ->setStatusCode(200)
            ->addHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->addHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->addHeader('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->addHeader('Pragma', 'no-cache')
            ->addHeader('Content-Length', (string) strlen($ics))
            ->setBody($ics);
    }

    private function errorResponse(int $status, string $message): HTTPResponse
    {
        return HTTPResponse::create()
            ->setStatusCode($status)
            ->addHeader('Content-Type', 'text/plain; charset=utf-8')
            ->setBody($message);
    }
}
