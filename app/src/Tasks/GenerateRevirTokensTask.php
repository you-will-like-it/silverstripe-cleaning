<?php

declare(strict_types=1);

namespace YouWillLikeIT\Cleaning\Tasks;

use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
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
    protected string $title = 'Generate Revir QR Tokens — Reinigung SaaS';

    protected static string $description = 'Popunjava QRToken (32-byte hex) za sve Revire koji nemaju token. Idempotentno.';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $output->writeln('Generating QR tokens for Revirs...');
        $output->writeln('');

        $revirs = Revir::get();

        if (!$revirs->exists()) {
            $output->writeln('No Revirs found in database. Nothing to do.');
            return Command::SUCCESS;
        }

        $generated = 0;
        $skipped   = 0;

        foreach ($revirs as $revir) {
            if (!empty($revir->QRToken)) {
                $skipped++;
                $output->writeln("  SKIP   [{$revir->ID}] {$revir->Name} — token already exists");
                continue;
            }

            $revir->QRToken = bin2hex(random_bytes(32));
            $revir->write();

            $generated++;
            $output->writeln("  CREATE [{$revir->ID}] {$revir->Name} → {$revir->QRToken}");
        }

        $output->writeln('');
        $output->writeln("Done. Generated: {$generated}, skipped (had token): {$skipped}");

        return Command::SUCCESS;
    }
}
