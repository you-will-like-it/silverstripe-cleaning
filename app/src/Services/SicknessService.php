<?php

declare(strict_types=1);

namespace YouWillLikeIT\Cleaning\Services;

use SilverStripe\Core\Injectable;

/**
 * SicknessService — provjera da li je radnik odsutan određenog dana.
 *
 * Osnovna implementacija koja uvijek vraća false (niko nije bolestan).
 * Host aplikacija može proširiti ovu klasu i registrirati vlastitu
 * implementaciju putem SilverStripe Injector-a ako ima vlastiti sustav
 * za praćenje bolovanja/odsutnosti:
 *
 *   # app/_config/services.yml
 *   SilverStripe\Core\Injector\Injector:
 *     YouWillLikeIT\Cleaning\Services\SicknessService:
 *       class: App\Services\CustomSicknessService
 */
class SicknessService
{
    use Injectable;

    /**
     * Da li je radnik odsutan (bolovanje, godišnji, itd.) određenog dana?
     *
     * @param int    $workerID  ID Member zapisa radnika
     * @param string $date      Format Y-m-d
     */
    public function isWorkerAbsent(int $workerID, string $date): bool
    {
        // Osnovna implementacija — uvijek dostupan.
        // Override putem Injector-a za vlastitu logiku praćenja odsutnosti.
        return false;
    }
}
