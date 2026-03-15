<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class MigrateFakes extends Command
{
    protected $signature = 'migrate:fakes';
    protected $description = 'Mark all migrations as finished without running them';

    public function handle()
    {
        $this->info('Faking migrations...');

        $migrationPath = database_path('migrations');
        $files = File::files($migrationPath);

        foreach ($files as $file) {
            $name = $file->getBasename('.php');
            
            // Check if already in migrations table
            $exists = DB::table('migrations')->where('migration', $name)->exists();

            if (!$exists) {
                DB::table('migrations')->insert([
                    'migration' => $name,
                    'batch' => 1,
                ]);
                $this->line("<info>Faked:</info> {$name}");
            } else {
                $this->line("<comment>Skipped (already exists):</comment> {$name}");
            }
        }

        $this->info('All migrations have been faked successfully.');
        return 0;
    }
}
