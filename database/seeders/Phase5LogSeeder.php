<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Phase5LogSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $users = DB::connection('bk_db')->table('portaluserlogoninfo')->get();

        if ($users->isEmpty()) {
            return;
        }

        foreach ($users as $user) {
            $role = DB::connection('bk_db')->table('userroles')->where('User', $user->Id)->first();

            // 1. Create Activity Logs
            $actions = [
                ['type' => 'authentication', 'action' => 'login_successful', 'desc' => 'User logged in successfully'],
                ['type' => 'modification', 'action' => 'profile_update', 'desc' => 'Updated contact information'],
                ['type' => 'data_access', 'action' => 'report_generation', 'desc' => 'Generated Portfolio Performance Report'],
                ['type' => 'authentication', 'action' => 'action_successful', 'desc' => 'User performed an action'],
            ];

            foreach ($actions as $act) {
                DB::connection('bk_db')->table('activitylogs')->insert([
                    'User' => $user->Id,
                    'Role' => $role->Role ?? null,
                    'ActivityType' => $act['type'],
                    'Action' => $act['action'],
                    'Description' => $act['desc'],
                    'SeverityLevel' => 'info',
                    'IpAddress' => '192.168.1.' . rand(1, 254),
                    'UserAgent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'StatusCode' => '200',
                    'created_on' => $now->subHours(rand(1, 72)),
                ]);
            }

            // 2. Create Notifications
            DB::connection('bk_db')->table('notificationservices')->insert([
                [
                    'Recipient' => $user->Id,
                    'Type' => 1,
                    'Message' => 'Welcome to BondKonnect! Your account is now active.',
                    'IsRead' => 1,
                    'created_on' => $now->subDays(5),
                ],
                [
                    'Recipient' => $user->Id,
                    'Type' => 2,
                    'Message' => 'Market Alert: Infrastructure Bond IFB1/2023/17 yield is trending upwards.',
                    'IsRead' => 0,
                    'created_on' => $now->subMinutes(30),
                ]
            ]);
        }
    }
}
