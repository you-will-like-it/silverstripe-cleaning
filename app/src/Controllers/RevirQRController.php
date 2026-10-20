<?php

declare(strict_types=1);

namespace YouWillLikeIT\Cleaning\Controllers;

use YouWillLikeIT\Cleaning\DataObjects\Revir;
use YouWillLikeIT\Cleaning\DataObjects\RevirCheckIn;
use YouWillLikeIT\Cleaning\DataObjects\WorkerAssignment;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SilverStripe\Security\SecurityToken;

/**
 * Handles Revir QR code scanning for location-proof check-in.
 *
 * Routes:
 *   GET  /revir-qr/scan/$Token        → scan()  (worker scans QR, redirected here)
 *   POST /revir-qr/confirm            → confirm() (AJAX, records check-in with optional coords)
 *   GET  /admin/qrcode/revir/$RevirID → generateRevirQR() (admin generates/rotates QR)
 *
 * QR URL format: https://example.com/revir-qr/scan/{token}
 * Token: 32-byte hex, stored on Revir.QRToken, static per revir.
 * Admin can rotate (invalidate old) any time.
 */
class RevirQRController extends Controller
{
    private static string $url_segment = 'revir-qr';

    /** @var array<string,string> */
    private static array $allowed_actions = [
        'scan'    => true,
        'confirm' => true,
    ];

    // ── Rate limiting ─────────────────────────────────────────────────────

    /** Max QR scans per IP per hour (prevents replay flooding). */
    private const RATE_LIMIT = 30;

    // ── Scan ──────────────────────────────────────────────────────────────

    /**
     * Worker scans the QR code; redirected here.
     * Shows a confirmation page (or auto-confirms if already logged in).
     */
    public function scan(HTTPRequest $request): HTTPResponse|string
    {
        $token = $request->param('Token') ?? '';

        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return $this->httpError(400, 'Invalid QR token');
        }

        // Rate limit by IP
        $ip = $request->getIP();
        if ($this->isRateLimited($request, $ip)) {
            return $this->httpError(429, 'Too many scan attempts');
        }

        $revir = Revir::get()->filter('QRToken', $token)->first();
        if (!$revir) {
            return $this->httpError(404, 'Invalid or expired QR code');
        }

        $user = Security::getCurrentUser();
        if (!$user) {
            // Not logged in — redirect to login with return URL
            $loginUrl = Security::login_url() . '?BackURL=' . urlencode('/revir-qr/scan/' . $token);
            return $this->redirect($loginUrl);
        }

        // Render confirmation page
        return $this->customise([
            'Revir'          => $revir,
            'Token'          => $token,
            'Worker'         => $user,
            'CleaningObject' => $revir->CleaningObject(),
        ])->renderWith(['RevirQRConfirm', 'Page']);
    }

    // ── Confirm ───────────────────────────────────────────────────────────

    /**
     * AJAX POST: record the check-in with optional GPS coordinates.
     */
    public function confirm(HTTPRequest $request): HTTPResponse
    {
        $this->validateCsrf($request);

        $worker = Security::getCurrentUser();
        if (!$worker) {
            return $this->jsonResponse(['success' => false, 'error' => 'Not authenticated'], 401);
        }

        $token    = $request->postVar('token') ?? '';
        $lat      = (float) ($request->postVar('latitude')  ?? 0);
        $lng      = (float) ($request->postVar('longitude') ?? 0);

        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return $this->jsonResponse(['success' => false, 'error' => 'Invalid token'], 400);
        }

        $revir = Revir::get()->filter('QRToken', $token)->first();
        if (!$revir) {
            return $this->jsonResponse(['success' => false, 'error' => 'Revir not found'], 404);
        }

        // Find active assignment for this worker + revir today
        $assignment = WorkerAssignment::get()->filter([
            'WorkerID'     => $worker->ID,
            'RevirID'      => $revir->ID,
            'AssignedDate' => date('Y-m-d'),
            'Status'       => ['Pending', 'Confirmed', 'InProgress'],
        ])->first();

        // Record check-in (even without assignment — presence is presence)
        $checkIn              = RevirCheckIn::create();
        $checkIn->WorkerID    = (int) $worker->ID;
        $checkIn->RevirID     = (int) $revir->ID;
        $checkIn->AssignmentID = (int) ($assignment?->ID ?? 0);
        $checkIn->ScannedAt   = date('Y-m-d H:i:s');
        $checkIn->IPAddress   = $request->getIP();
        $checkIn->UserAgent   = substr($request->getHeader('User-Agent') ?? '', 0, 512);
        $checkIn->Latitude    = $lat !== 0.0 ? $lat : null;
        $checkIn->Longitude   = $lng !== 0.0 ? $lng : null;
        $checkIn->write();

        // Auto-confirm assignment if still Pending
        if ($assignment && $assignment->Status === 'Pending') {
            $assignment->Status = 'Confirmed';
            $assignment->write();
        }

        return $this->jsonResponse([
            'success'     => true,
            'revir_name'  => $revir->Name,
            'scanned_at'  => $checkIn->ScannedAt,
            'assignment'  => $assignment?->ID,
        ]);
    }

    // ── Token management (static helpers for admin) ───────────────────────

    /**
     * Generate a fresh QR token for a Revir.
     * Called from QRCodeAdmin or a BuildTask.
     */
    public static function generateRevirToken(Revir $revir): string
    {
        $token           = bin2hex(random_bytes(32)); // 64 hex chars
        $revir->QRToken  = $token;
        $revir->write();
        return $token;
    }

    /**
     * Full QR scan URL for a revir.
     */
    public static function getQRUrl(Revir $revir): string
    {
        return \SilverStripe\Control\Director::absoluteBaseURL()
            . 'revir-qr/scan/' . ($revir->QRToken ?? '');
    }

    // ── Rate limiting ─────────────────────────────────────────────────────

    private function isRateLimited(HTTPRequest $request, string $ip): bool
    {
        $key       = 'revir_qr_' . md5($ip) . '_' . date('YmdH');
        $session   = $request->getSession();
        $attempts  = (int) ($session->get($key) ?? 0);

        if ($attempts >= self::RATE_LIMIT) {
            return true;
        }

        $session->set($key, $attempts + 1);
        return false;
    }

    // ── Helpers ───────────────────────────────────────────────────────────

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
