<?php

declare(strict_types=1);

namespace YouWillLikeIT\Cleaning\Controllers;

use YouWillLikeIT\Cleaning\DataObjects\AssignmentPhoto;
use YouWillLikeIT\Cleaning\DataObjects\WorkerAssignment;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\Assets\Upload;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SilverStripe\Security\SecurityToken;

/**
 * Handles worker photo uploads (before/after) for a WorkerAssignment.
 *
 * Routes:
 *   POST /portal/photo/upload   → upload()
 *   GET  /portal/photo/list/$ID → list()
 *   POST /portal/photo/delete/$ID → delete()
 */
class PhotoUploadController extends Controller
{
    private static string $url_segment = 'portal/photo';

    /** @var array<string,string> */
    private static array $allowed_actions = [
        'upload',
        'list',
        'delete',
    ];

    protected function init(): void
    {
        parent::init();

        $user = Security::getCurrentUser();
        if (!$user || !Permission::checkMember($user, ['WORKER', 'AREA_MANAGER', 'COMPANY_ADMIN', 'SUPER_ADMIN', 'ADMIN'])) {
            $this->httpError(403);
        }
    }

    // ── Upload ────────────────────────────────────────────────────────────

    public function upload(HTTPRequest $request): HTTPResponse
    {
        $this->validateCsrf($request);

        $worker       = Security::getCurrentUser();
        $assignmentID = (int) $request->postVar('assignment_id');
        $photoType    = $request->postVar('photo_type') === 'After' ? 'After' : 'Before';
        $caption      = substr(strip_tags($request->postVar('caption') ?? ''), 0, 255);

        // Verify worker owns this assignment
        $assignment = WorkerAssignment::get()->filter([
            'ID'       => $assignmentID,
            'WorkerID' => $worker->ID,
        ])->first();

        if (!$assignment) {
            return $this->jsonResponse(['success' => false, 'error' => 'Assignment not found'], 404);
        }

        // Handle file upload
        $uploadedFile = $_FILES['photo'] ?? null;
        if (!$uploadedFile || $uploadedFile['error'] !== UPLOAD_ERR_OK) {
            return $this->jsonResponse(['success' => false, 'error' => 'No file uploaded'], 400);
        }

        // Validate mime type
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/heic'];
        $finfo        = new \finfo(FILEINFO_MIME_TYPE);
        $mime         = $finfo->file($uploadedFile['tmp_name']);

        if (!in_array($mime, $allowedMimes, true)) {
            return $this->jsonResponse(['success' => false, 'error' => 'Invalid file type'], 400);
        }

        // Max 10 MB
        if ($uploadedFile['size'] > 10 * 1024 * 1024) {
            return $this->jsonResponse(['success' => false, 'error' => 'File too large (max 10 MB)'], 400);
        }

        try {
            $upload = Upload::create();
            $upload->setAllowedMaxFileSize(['image/*' => '10m']);

            $image = Image::create();
            $image->setFromLocalFile(
                $uploadedFile['tmp_name'],
                'assignments/' . $assignmentID . '/' . $photoType . '/' . basename($uploadedFile['name'])
            );
            $image->write();
            $image->publishSingle();

            $photo               = AssignmentPhoto::create();
            $photo->AssignmentID = $assignmentID;
            $photo->PhotoID      = (int) $image->ID;
            $photo->PhotoType    = $photoType;
            $photo->Caption      = $caption;
            $photo->UploadedByID = (int) $worker->ID;
            $photo->TakenAt      = date('Y-m-d H:i:s');
            $photo->write();

            return $this->jsonResponse([
                'success'  => true,
                'photo_id' => $photo->ID,
                'url'      => $image->getURL(),
                'thumb'    => $image->ScaleWidth(300)?->getURL() ?? $image->getURL(),
                'type'     => $photoType,
            ]);
        } catch (\Throwable $e) {
            return $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ── List ──────────────────────────────────────────────────────────────

    public function list(HTTPRequest $request): HTTPResponse
    {
        $worker       = Security::getCurrentUser();
        $assignmentID = (int) $request->param('ID');

        $assignment = WorkerAssignment::get()->filter([
            'ID'       => $assignmentID,
            'WorkerID' => $worker->ID,
        ])->first();

        if (!$assignment) {
            // Area managers can see all
            if (!Permission::checkMember($worker, ['AREA_MANAGER', 'COMPANY_ADMIN', 'SUPER_ADMIN', 'ADMIN'])) {
                return $this->jsonResponse(['success' => false, 'error' => 'Not found'], 404);
            }
            $assignment = WorkerAssignment::get()->byID($assignmentID);
            if (!$assignment) {
                return $this->jsonResponse(['success' => false, 'error' => 'Not found'], 404);
            }
        }

        $photos = AssignmentPhoto::get()
            ->filter('AssignmentID', $assignmentID)
            ->sort('TakenAt ASC');

        $result = [];
        foreach ($photos as $photo) {
            $image    = $photo->Photo();
            $result[] = [
                'id'      => $photo->ID,
                'type'    => $photo->PhotoType,
                'url'     => $image?->getURL() ?? '',
                'thumb'   => $image?->ScaleWidth(300)?->getURL() ?? '',
                'caption' => $photo->Caption,
                'taken'   => $photo->TakenAt,
            ];
        }

        return $this->jsonResponse(['success' => true, 'photos' => $result]);
    }

    // ── Delete ────────────────────────────────────────────────────────────

    public function delete(HTTPRequest $request): HTTPResponse
    {
        $this->validateCsrf($request);

        $worker  = Security::getCurrentUser();
        $photoID = (int) $request->param('ID');

        $photo = AssignmentPhoto::get()->byID($photoID);
        if (!$photo) {
            return $this->jsonResponse(['success' => false, 'error' => 'Not found'], 404);
        }

        // Only uploader or admin can delete
        $isOwn  = (int) $photo->UploadedByID === (int) $worker->ID;
        $isAdmin = Permission::checkMember($worker, ['SUPER_ADMIN', 'COMPANY_ADMIN', 'ADMIN']);

        if (!$isOwn && !$isAdmin) {
            return $this->jsonResponse(['success' => false, 'error' => 'Forbidden'], 403);
        }

        $image = $photo->Photo();
        $photo->delete();
        if ($image?->exists()) {
            $image->delete();
        }

        return $this->jsonResponse(['success' => true]);
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
