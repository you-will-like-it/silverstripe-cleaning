<?php

declare(strict_types=1);

namespace YouWillLikeIT\Cleaning\Services;

use SilverStripe\Core\Injectable;
use SilverStripe\Security\Member;
use YouWillLikeIT\Cleaning\DataObjects\WorkerAssignment;

/**
 * NotificationService — šalje obavijesti radnicima o novim/promijenjenim smjenama.
 *
 * Osnovna implementacija koristi SilverStripe Email::create().
 * Host aplikacija može proširiti i registrirati vlastitu implementaciju
 * putem Injector-a za SMS/WhatsApp (vidi BulkNotificationService):
 *
 *   # app/_config/services.yml
 *   SilverStripe\Core\Injector\Injector:
 *     YouWillLikeIT\Cleaning\Services\NotificationService:
 *       class: App\Services\CustomNotificationService
 */
class NotificationService
{
    use Injectable;

    /**
     * Pošalji radniku obavijest o novoj smjeni.
     *
     * Gracefully pada ako radnik nema email adresu.
     */
    public function sendAssignmentNotification(WorkerAssignment $assignment): void
    {
        $worker = $assignment->Worker();

        if (!$worker || !$worker->exists() || empty($worker->Email)) {
            return;
        }

        $revir = $assignment->Revir();
        $date  = $assignment->AssignedDate;

        try {
            \SilverStripe\Control\Email\Email::create()
                ->setTo($worker->Email)
                ->setSubject('Neue Schichtzuweisung: ' . ($revir?->Name ?? '') . ' am ' . $date)
                ->setBody($this->buildAssignmentBody($worker, $assignment))
                ->send();
        } catch (\Throwable) {
            // Notifikacija nije kritična — log i nastavi
        }
    }

    private function buildAssignmentBody(Member $worker, WorkerAssignment $assignment): string
    {
        $revir  = $assignment->Revir();
        $object = $revir?->CleaningObject();

        return implode("\n", [
            'Hallo ' . $worker->FirstName . ',',
            '',
            'Sie haben eine neue Schichtzuweisung erhalten:',
            '',
            'Datum:   ' . $assignment->AssignedDate,
            'Revir:   ' . ($revir?->Name ?? '-'),
            'Objekt:  ' . ($object?->Name ?? '-'),
            'Adresse: ' . ($object?->getFullAddress() ?? '-'),
            '',
            'Mit freundlichen Grüßen',
            'Reinigung SaaS',
        ]);
    }
}
