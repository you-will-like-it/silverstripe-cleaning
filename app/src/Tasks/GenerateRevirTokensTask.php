<?php

declare(strict_types=1);

namespace YouWillLikeIT\Cleaning\Tasks;

use SilverStripe\Dev\BuildTask;
use YouWillLikeIT\Cleaning\DataObjects\Revir;

/**
 * GenerateRevirTokensTask — popunjava QRToken za sve Revire koji ga nemaju.
 *
 * Pokreni jednom nakon deployment-a koji uvodi QRToken polje:
 *   vendor/bin/sake dev/tasks/YouWillLikeIT-Cleaning-Tasks-GenerateRevirTokensTask
 *
 * Idempotentno: preskače Revire koji već imaju token.
 */
class GenerateRevirTokensTask extends BuildTask
{
    private static string $segment = 'YouWillLikeIT-Cleaning-Tasks-GenerateRevirTokensTask';

    protected $title = 'Generate Revir QR Tokens — Reinigung SaaS';

    protected $description = 'Popunjava QRToken (32-byte hex) za sve Revire koji nemaju token. Idempotentno — preskače postojeće tokene.';

    public function run($request): void
    {
        $this->output('🔑 GenerateRevirTokensTask — Reinigung SaaS');
        $this->output('');

        /** @var Revir[] $revirs */
        $revirs = Revir::get();

        if (!$revirs->exists()) {
            $this->output('ℹ️  Nema revira u bazi. Ništa za raditi.');
            return;
        }

        $generated = 0;
        $skipped   = 0;

        foreach ($revirs as $revir) {
            if (!empty($revir->QRToken)) {
                $skipped++;
                $this->output("   ⏭  Preskočen: [{$revir->ID}] {$revir->Name} (token već postoji)");
                continue;
            }

            $token = bin2hex(random_bytes(32));

            // Direktan SQL update da zaobiđemo onBeforeWrite logiku
            // i izbjegnemo nepotrebno diranje UpdatedAt / audit loga
            $revir->QRToken = $token;
            $revir->write();

            $generated++;
            $this->output("   ✓  [{$revir->ID}] {$revir->Name} → {$token}");
        }

        $this->output('');
        $this->output("✅ Završeno — generisano: {$generated}, preskočeno (već imaju token): {$skipped}");

        if ($generated > 0) {
            $this->output('');
            $this->output('ℹ️  QR kodovi su sada dostupni u CMS-u pod Reviri → QR Kod kolona.');
        }
    }

    private function output(string $message): void
    {
        echo isset($_SERVER['REQUEST_URI'])
            ? $message . '<br>' . PHP_EOL
            : $message . PHP_EOL;
    }
}
