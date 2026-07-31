<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 1. Rename legacy migrations table
if (Schema::hasTable('migrations') && ! Schema::hasColumn('migrations', 'batch')) {
    Schema::rename('migrations', 'legacy_migrations');
    echo "Renamed legacy migrations table.\n";
}

// 2. Install new migrations table
if (! Schema::hasTable('migrations')) {
    Artisan::call('migrate:install');
    echo "Installed Laravel migrations table.\n";
}

// 3. Sync existing migrations
$files = scandir(__DIR__.'/database/migrations');
$batch = 1;

foreach ($files as $file) {
    if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
        $migrationName = pathinfo($file, PATHINFO_FILENAME);

        // Skip migrations that we actually need to run (because their tables don't exist yet)
        if (str_contains($migrationName, 'personal_access_tokens')) {
            continue;
        }
        if (str_contains($migrationName, 'cache_table') && ! Schema::hasTable('cache')) {
            continue;
        }
        if (str_contains($migrationName, 'jobs_table') && ! Schema::hasTable('jobs')) {
            continue;
        }

        // For everything else, assume they are already represented in the DB schema
        // We insert them into the migrations table so Laravel thinks they've already run.
        $exists = DB::table('migrations')->where('migration', $migrationName)->exists();
        if (! $exists) {
            DB::table('migrations')->insert([
                'migration' => $migrationName,
                'batch' => $batch,
            ]);
            echo "Marked $migrationName as migrated.\n";
        }
    }
}

echo "Sync complete.\n";
