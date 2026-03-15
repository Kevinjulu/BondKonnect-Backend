<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LegacyTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Seeding roles
        $roles = [
            ['Id' => 1, 'Name' => 'Admin', 'Description' => 'System Administrator'],
            ['Id' => 2, 'Name' => 'Broker', 'Description' => 'Bond Broker'],
            ['Id' => 3, 'Name' => 'Dealer', 'Description' => 'Bond Dealer'],
            ['Id' => 4, 'Name' => 'Individual', 'Description' => 'Individual Investor'],
            ['Id' => 5, 'Name' => 'Agent', 'Description' => 'Investment Agent'],
            ['Id' => 6, 'Name' => 'Corporate', 'Description' => 'Corporate Investor'],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(['Id' => $role['Id']], $role);
        }

        // Link user 1 to Admin role
        DB::table('userroles')->updateOrInsert(
            ['User' => 1, 'Role' => 1],
            ['created_on' => now()]
        );

        // Seeding subscriptionplan
        $subscriptionPlans = [
            ['Id' => 1, 'Name' => 'Quote Book (Basic)', 'Description' => 'Basic access to Quote Book', 'Level' => 1, 'IsActive' => true],
            ['Id' => 2, 'Name' => 'Quote Book (Viewer)', 'Description' => 'Viewer access to Quote Book', 'Level' => 2, 'IsActive' => true],
            ['Id' => 3, 'Name' => 'Bondmetrics', 'Description' => 'Access to Bondmetrics', 'Level' => 3, 'IsActive' => true],
            ['Id' => 4, 'Name' => 'Bondmetrics Plus', 'Description' => 'Full access to Bondmetrics Plus', 'Level' => 4, 'IsActive' => true],
        ];

        foreach ($subscriptionPlans as $plan) {
            DB::table('subscriptionplan')->updateOrInsert(['Id' => $plan['Id']], $plan);
        }

        // Seeding notificationtypes
        $notificationTypes = [
            [
                'Id' => 1, 'Name' => 'Message Notifications', 'Description' => 'Alerts for messages.', 
                'AffectAdmins' => true, 'AffectsIndividuals' => true, 'AffectsAgents' => true, 
                'AffectsCorporates' => true, 'AffectsBrokers' => true, 'AffectsAuthorizedDealers' => true, 
                'IsMessageNotification' => true
            ],
            [
                'Id' => 2, 'Name' => 'Service Request Notifications', 'Description' => 'Alerts for incoming service requests.', 
                'AffectAdmins' => true, 'AffectsIndividuals' => true, 'AffectsAgents' => true, 
                'AffectsCorporates' => true, 'AffectsBrokers' => true, 'AffectsAuthorizedDealers' => true, 
                'IsServiceRequestNotification' => true
            ],
            [
                'Id' => 3, 'Name' => 'Account Creation Notifications', 'Description' => 'Confirmation that a sponsor or trustee or relationship manager account has been created.', 
                'AffectAdmins' => true, 'AffectsIndividuals' => true, 'AffectsAgents' => false, 
                'AffectsCorporates' => false, 'AffectsBrokers' => false, 'AffectsAuthorizedDealers' => false, 
                'IsAccountCreationNotification' => true
            ],
            [
                'Id' => 4, 'Name' => 'Failure Alerts Notifications', 'Description' => 'Notifications regarding issues within the portal requiring admin attention.', 
                'AffectAdmins' => true, 'AffectsIndividuals' => false, 'AffectsAgents' => false, 
                'AffectsCorporates' => false, 'AffectsBrokers' => false, 'AffectsAuthorizedDealers' => false, 
                'IsFailureAlertNotification' => true
            ],
            [
                'Id' => 5, 'Name' => 'Portal Notifications', 'Description' => 'Alerts for all portal notifications.', 
                'AffectAdmins' => true, 'AffectsIndividuals' => true, 'AffectsAgents' => true, 
                'AffectsCorporates' => true, 'AffectsBrokers' => true, 'AffectsAuthorizedDealers' => true, 
                'IsPortalNotification' => true
            ],
            [
                'Id' => 6, 'Name' => 'Dashboard Notifications', 'Description' => 'Alerts for incoming dashboard alerts.', 
                'AffectAdmins' => true, 'AffectsIndividuals' => true, 'AffectsAgents' => true, 
                'AffectsCorporates' => true, 'AffectsBrokers' => true, 'AffectsAuthorizedDealers' => true, 
                'IsDashboard' => true
            ],
            [
                'Id' => 7, 'Name' => 'Approval Request Notifications', 'Description' => 'Alerts for incoming approval request notifications.', 
                'AffectAdmins' => true, 'AffectsIndividuals' => true, 'AffectsAgents' => true, 
                'AffectsCorporates' => true, 'AffectsBrokers' => true, 'AffectsAuthorizedDealers' => true, 
                'IsApproval' => true
            ],
            [
                'Id' => 8, 'Name' => 'Bid Status Notifications', 'Description' => 'Alerts for bid/quotes request notifications.', 
                'AffectAdmins' => true, 'AffectsIndividuals' => true, 'AffectsAgents' => true, 
                'AffectsCorporates' => true, 'AffectsBrokers' => true, 'AffectsAuthorizedDealers' => true, 
                'IsBids' => true
            ],
        ];

        foreach ($notificationTypes as $type) {
            DB::table('notificationtypes')->updateOrInsert(['Id' => $type['Id']], $type);
        }
    }
}
