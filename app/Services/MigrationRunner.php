<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Artisan;

class MigrationRunner
{
    /**
     * Run pending migrations (delegates to artisan migrate).
     *
     * @return array<string, mixed>
     */
    public function run(): array
    {
        $exitCode = Artisan::call('migrate', ['--no-interaction' => true, '--force' => true]);

        return [
            'exitCode' => $exitCode,
            'output' => Artisan::output(),
        ];
    }

    /**
     * Return the status of all migrations.
     *
     * @return array<int, array<string, mixed>>
     */
    public function status(): array
    {
        Artisan::call('migrate:status', ['--no-interaction' => true]);
        $raw = Artisan::output();

        // Parse the tabular output into structured rows.
        $rows = [];
        foreach (explode("\n", trim($raw)) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '+') || str_starts_with($line, '|  Ran?')) {
                continue;
            }

            $cells = array_values(array_map('trim', array_filter(
                explode('|', $line),
                static fn (string $c): bool => $c !== ''
            )));

            if (count($cells) >= 3) {
                $rows[] = [
                    'status' => strtolower($cells[0]) === 'yes' ? 'ran' : 'pending',
                    'migration' => $cells[1],
                    'batch' => $cells[2] !== '0' ? (int) $cells[2] : null,
                ];
            }
        }

        return $rows;
    }
}
