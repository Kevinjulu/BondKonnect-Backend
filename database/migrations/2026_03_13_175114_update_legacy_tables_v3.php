<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Table: notificationservices
        if (!Schema::hasTable('notificationservices')) {
            Schema::create('notificationservices', function (Blueprint $table) {
                $table->increments('Id');
                $table->integer('created_by')->nullable();
                $table->timestamp('created_on')->nullable();
                $table->integer('altered_by')->nullable();
                $table->timestamp('dola')->nullable();
                $table->integer('Recipient')->nullable();
                $table->integer('Type')->nullable();
                $table->text('Message')->nullable();
                $table->string('Link', 100)->nullable();
                $table->text('NotificationUrl')->nullable();
                $table->integer('ActionRecipientId')->nullable();
                $table->boolean('IsArchive')->default(false);
                $table->boolean('IsFavorite')->default(false);
                $table->boolean('IsRead')->default(false);
            });
        }

        // Table: notificationtypes
        if (!Schema::hasTable('notificationtypes')) {
            Schema::create('notificationtypes', function (Blueprint $table) {
                $table->increments('Id');
                $table->integer('created_by')->nullable();
                $table->timestamp('created_on')->nullable();
                $table->integer('altered_by')->nullable();
                $table->timestamp('dola')->nullable();
                $table->string('Name', 100)->nullable();
                $table->text('Description')->nullable();
                $table->boolean('AffectAdmins')->default(false);
                $table->boolean('AffectsIndividuals')->default(false);
                $table->boolean('AffectsAgents')->default(false);
                $table->boolean('AffectsCorporates')->default(false);
                $table->boolean('AffectsBrokers')->default(false);
                $table->boolean('AffectsAuthorizedDealers')->default(false);
                $table->boolean('IsMessageNotification')->default(false);
                $table->boolean('IsServiceRequestNotification')->default(false);
                $table->boolean('IsAccountCreationNotification')->default(false);
                $table->boolean('IsFailureAlertNotification')->default(false);
                $table->boolean('IsPortalNotification')->default(false);
                $table->boolean('IsDashboard')->default(false);
                $table->boolean('IsApproval')->default(false);
                $table->boolean('IsBids')->default(false);
            });
        }

        // Table: portaluserrolepermissions
        if (!Schema::hasTable('portaluserrolepermissions')) {
            Schema::create('portaluserrolepermissions', function (Blueprint $table) {
                $table->increments('Id');
                $table->integer('created_by')->nullable();
                $table->timestamp('created_on')->nullable();
                $table->integer('altered_by')->nullable();
                $table->timestamp('dola')->nullable();
                $table->integer('Role')->nullable();
                $table->integer('User')->nullable();
                $table->boolean('CanAccessResearchAssistant')->default(false);
                $table->boolean('CanAccessBondscreens')->default(false);
                $table->boolean('CanAccessBondStats')->default(false);
                $table->boolean('CanManagePortfolio')->default(false);
                $table->boolean('CanManageQuotes')->default(false);
                $table->boolean('CanManageTransactions')->default(false);
                $table->boolean('CanAccessMessages')->default(false);
                $table->boolean('CanUpdateAccountSettings')->default(false);
                $table->boolean('CanReceiveNotifications')->default(false);
                $table->boolean('CanManageUploads')->default(false);
                $table->boolean('CanAccessSubscriptions')->default(false);
                $table->boolean('CanManageAccounts')->default(false);
                $table->boolean('CanAccessFAQ')->default(false);
                $table->boolean('CanAccessHelp')->default(false);
                $table->boolean('CanAccessActivityLogs')->default(false);
                $table->boolean('CanAccessAdmin')->default(false);
                $table->boolean('CanAccessAnalysis')->default(false);
                $table->boolean('CanAccessInvoices')->default(false);
                $table->boolean('CanAccessFinancials')->default(false);
                $table->boolean('CanAccessResearchAssistantTools')->default(false);
                $table->boolean('CanAccessBondCalc')->default(false);
                $table->boolean('CanSubmitMessage')->default(false);
                $table->boolean('CanApproveIntermediary')->default(false);
                $table->boolean('CanGenerateQuote')->default(false);
                $table->boolean('CanSubmitBid')->default(false);
                $table->boolean('CanApproveQuote')->default(false);
                $table->boolean('CanRejectQuote')->default(false);
                $table->boolean('CanDelegateQuote')->default(false);
                $table->boolean('CanCreateUserAccount')->default(false);
                $table->boolean('CanViewUserAccounts')->default(false);
                $table->boolean('CanResetPassword')->default(false);
                $table->boolean('CanDeleteUserAccount')->default(false);
                $table->boolean('CanCreateSubscriptionPackage')->default(false);
                $table->boolean('CanPurchaseSubscription')->default(false);
                $table->boolean('CanViewYieldGraphs')->default(false);
                $table->boolean('CanAccessDurationScreen')->default(false);
                $table->boolean('CanAccessReturnScreen')->default(false);
                $table->boolean('CanAccessBarbellScreen')->default(false);
                $table->boolean('CanViewBondStats')->default(false);
                $table->boolean('CanAccessRiskMetrics')->default(false);
                $table->boolean('CanAccessPortfolioNotepad')->default(false);
                $table->boolean('CanAccessProfitAndLoss')->default(false);
                $table->boolean('CanAccessPortfolioScorecard')->default(false);
                $table->boolean('CanAccessRiskProfile')->default(false);
                $table->boolean('CanAccessStressTesting')->default(false);
                $table->boolean('CanViewFaceValue')->default(false);
                $table->boolean('CanAccessMyTransactions')->default(false);
                $table->boolean('CanAccessAllTransactions')->default(false);
                $table->boolean('CanViewInvoices')->default(false);
                $table->boolean('CanViewAnalysis')->default(false);
                $table->boolean('CanViewFinancials')->default(false);
            });
        }

        // Table: activitylogs
        if (!Schema::hasTable('activitylogs')) {
            Schema::create('activitylogs', function (Blueprint $table) {
                $table->increments('Id');
                $table->integer('created_by')->nullable();
                $table->timestamp('created_on')->nullable();
                $table->integer('altered_by')->nullable();
                $table->timestamp('dola')->nullable();
                $table->integer('User')->nullable();
                $table->integer('Role')->nullable();
                $table->string('ActivityType', 100)->nullable();
                $table->string('Action', 100)->nullable();
                $table->string('SeverityLevel', 100)->nullable();
                $table->text('Description')->nullable();
                $table->string('UserAgent', 100)->nullable();
                $table->string('IpAddress', 100)->nullable();
                $table->text('RequestMethod')->nullable();
                $table->text('RequestUrl')->nullable();
                $table->text('RequestHeaders')->nullable();
                $table->string('Location', 100)->nullable();
                $table->string('Compression', 100)->nullable();
                $table->string('StatusCode', 100)->nullable();
            });
        }

        // Table: subscriptionplan
        if (!Schema::hasTable('subscriptionplan')) {
            Schema::create('subscriptionplan', function (Blueprint $table) {
                $table->increments('Id');
                $table->integer('created_by')->nullable();
                $table->timestamp('created_on')->nullable();
                $table->integer('altered_by')->nullable();
                $table->timestamp('dola')->nullable();
                $table->string('Name', 100)->nullable();
                $table->text('Description')->nullable();
                $table->integer('Level')->nullable();
                $table->boolean('IsActive')->default(true);
            });
        }

        // Update emaillogs if needed
        if (Schema::hasTable('emaillogs')) {
            Schema::table('emaillogs', function (Blueprint $table) {
                if (!Schema::hasColumn('emaillogs', 'created_by')) {
                    $table->integer('created_by')->nullable();
                }
                if (!Schema::hasColumn('emaillogs', 'altered_by')) {
                    $table->integer('altered_by')->nullable();
                }
                if (!Schema::hasColumn('emaillogs', 'dola')) {
                    $table->timestamp('dola')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptionplan');
        Schema::dropIfExists('activitylogs');
        Schema::dropIfExists('portaluserrolepermissions');
        Schema::dropIfExists('notificationtypes');
        Schema::dropIfExists('notificationservices');
    }
};
