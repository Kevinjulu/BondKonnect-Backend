<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BackfillPaymentsUserId extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bk:backfill-payments-user-id {--execute : Actually perform updates} {--run-id= : Optional run id} {--chunk=500 : Chunk size}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dry-run and safely backfill payments.user_id from payments.user_email (matches users.email). Use --execute to apply.';

    public function handle(): int
    {
        $execute = $this->option('execute');
        $runId = $this->option('run-id') ?: 'run-' . now()->format('YmdHis') . '-' . Str::random(6);
        $chunk = (int)$this->option('chunk');

        $this->info('Starting backfill (dry-run unless --execute). Run ID: ' . $runId);

        $connection = DB::connection(config('database.default'));

        // Count payments without user_id
        $totalWithout = $connection->table('payments')->whereNull('user_id')->count();
        $this->info("Payments missing user_id: $totalWithout");

        if ($totalWithout === 0) {
            $this->info('Nothing to do.');
            return 0;
        }

        // Show sample unmatched
        $sample = $connection->table('payments')
            ->whereNull('user_id')
            ->select('Id', 'user_email')
            ->limit(10)
            ->get();

        $this->info('Sample payments missing user_id:');
        $this->table(['Id', 'user_email'], $sample->map(function($r){ return (array)$r; })->toArray());

        if (!$execute) {
            $this->info('Dry-run complete. Rerun with --execute to apply updates.');
            return 0;
        }

        $this->info('Beginning updates...');

        $processed = 0;
        $connection->transaction(function() use (&$processed, $connection, $chunk, $runId) {
            $connection->table('payments')->whereNull('user_id')->orderBy('Id')->chunk($chunk, function($payments) use (&$processed, $connection, $runId) {
                foreach ($payments as $p) {
                    $email = trim(strtolower($p->user_email ?? ''));
                    if (empty($email)) continue;

                    $user = $connection->table('users')->whereRaw('LOWER(email) = ?', [$email])->first();
                    if ($user) {
                        $old = $p->user_id;
                        $new = $user->id;

                        // Update payment
                        $connection->table('payments')->where('Id', $p->Id)->update(['user_id' => $new]);

                        // Log change
                        $connection->table('payment_user_backfill_log')->insert([
                            'payment_id' => $p->Id,
                            'old_user_id' => $old,
                            'new_user_id' => $new,
                            'run_id' => $runId,
                            'created_at' => now(),
                        ]);

                        $processed++;
                    }
                }
            });
        });

        $this->info("Backfill complete. Updated rows: $processed. Run ID: $runId");

        $this->info('Recommendation: verify samples and then add FK constraint via migration.');

        return 0;
    }
}
