<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\Auth\AuthController;
use App\Http\Controllers\V1\Admin\CacheController;
use App\Http\Controllers\V1\Bonds\BondsController;
use App\Http\Controllers\V1\Defaults\UserManagement;
use App\Http\Controllers\V1\Bonds\DataUploadController;
use App\Http\Controllers\V1\Defaults\MessageController;
use App\Http\Controllers\V1\Defaults\StandardFunctions;
use App\Http\Controllers\V1\Financials\FinancialController;
use App\Http\Controllers\V1\Financials\MpesaController;
use App\Http\Controllers\V1\Financials\PaypalController;
use App\Http\Controllers\V1\Defaults\CommunicationManagement;
use App\Http\Controllers\V1\RoleActions\PermissionManagement;
use App\Http\Controllers\V1\Notifications\NotificationController;
use App\Http\Controllers\V1\AiController;

// Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
//     return $request->user();
// });
Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

// Health check for Railway
Route::get('/health', function () {
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        return response()->json(['status' => 'ok', 'database' => 'connected'], 200);
    } catch (\Exception $e) {
        return response()->json(['status' => 'error', 'database' => 'disconnected', 'message' => $e->getMessage()], 500);
    }
});

//v1(Authentication)
Route::group(
    [
      'prefix' => 'V1/auth',

    ],
    function () {
        Route::get('testDBDR', [AuthController::class, 'dbDR']);
        Route::post('user-register', [AuthController::class, 'registerUsers']);
        Route::post('set-password', [AuthController::class, 'setPassword']);
        Route::post('user-login', [AuthController::class, 'loginUser']);
        Route::post('verify-otp', [AuthController::class, 'otpVerification']);
        Route::post('user-reset-password', [AuthController::class, 'resetPassword']);
        Route::post('resend-otp', [AuthController::class, 'otpResend']);
        Route::post('user-logout', [AuthController::class, 'logoutUser']);
        Route::post('delegate-user-leave', [AuthController::class, 'delegateUserLeave']);
        Route::post('get-user-details', [AuthController::class, 'getCurrentUserDetails']);
        Route::post('set-active-role', [AuthController::class, 'setActiveRole']);
        Route::get('generate-csrf-token', [StandardFunctions::class, 'generateCsrfToken']);
        Route::post('upload-data', [DataUploadController::class, 'upload']);
        Route::post('get-all-activity-logs', [AuthController::class, 'getActivityLogs']);
        Route::post('get-all-users', [AuthController::class, 'getAllUsers']);
        Route::post('get-all-broker-dealers', [AuthController::class, 'getAllBrokersAndDealers']);
        Route::post('suspend-user', [AuthController::class, 'suspendUser']);
        Route::post('reactivate-user', [AuthController::class, 'reactivateUser']);
        Route::post('complete-intermediary-registration', [AuthController::class, 'completeIntermediaryRegistration']);
        Route::post('approve-intermediary-client', [AuthController::class, 'approveIntermediaryClient']);
        Route::get('get-admin-users', [AuthController::class, 'getAdminUsers']);
        Route::get('get-user-intermediaries', [AuthController::class, 'getUserIntermediaries']);
        Route::get('concurrent-users', [AuthController::class, 'getConcurrentUsers']);
        Route::post('update-profile', [AuthController::class, 'updateProfile']);
        Route::get('active-sessions', [AuthController::class, 'getActiveSessions']);
        Route::post('revoke-session', [AuthController::class, 'revokeSession']);
    }
  );



//V1/services(Bonds services)
Route::group(
    [
      'prefix' => 'V1/services',

    ],
    function () {
        //bonds
        Route::middleware(['subscription'])->group(function () {
            // Route::get('upload-csv', [BondsController::class, 'uploadCsv']);
            Route::post('total-return-screen', [BondsController::class, 'getTotalReturnScreen']);
            Route::post('total-duration-screen', [BondsController::class, 'getTotalDurationScreen']);
            Route::post('barbell-bullet', [BondsController::class, 'getBarbellAndBullet']);
            Route::post('stats-table', [BondsController::class, 'getStatsTable']);
            Route::get('get-bondCalc-Details', [BondsController::class, 'getBondCalculatorDetails']);

            Route::get ('get-secondary-market-bonds', [BondsController::class, 'getSecondaryMarketBonds']);
            Route::get ('get-primary-market-bonds', [BondsController::class, 'getPrimaryMarketBonds']);
            Route::get ('get-bond-market-performance', [BondsController::class, 'getBondMarketPerformance']);
            Route::get ('get-spot-yield-curve', [BondsController::class, 'getSpotYieldCurve']);
            Route::get ('get-projection-bands', [BondsController::class, 'projectionBands']);
            Route::get ('get-historical-bands', [BondsController::class, 'historicalBands']);
            Route::get ('get-table-params', [BondsController::class, 'getTableParams']);
        });
        //Quotes management
        Route::middleware(['broker'])->group(function () {
            Route::post('create-quote', [BondsController::class, 'createQuote']);
            Route::post('get-all-quotes', [BondsController::class, 'getQuotes']);    //Using Now
            Route::post('get-all-active-quotes', [BondsController::class, 'getActiveQuotes']);
            Route::post('get-quotes-user', [BondsController::class, 'getUserQuotes']);
            Route::post('get-viewing-party-quotes', [BondsController::class, 'getViewingPartyQuotes']);
            Route::post('get-delegated-quotes', [BondsController::class, 'delegatedQuotes']);
            Route::post('activate-quote', [BondsController::class, 'activateQuote']);
            Route::post('suspend-quote', [BondsController::class, 'suspendQuote']);
            Route::post('update-quote', [BondsController::class, 'updateQuote']);
            Route::post('create-transaction', [BondsController::class, 'createTransaction']); //-- url: /V1/services/create-transaction
            Route::post('mark-transaction-status', [BondsController::class, 'markTransactionStatus']);
            Route::post('get-all-transactions', [BondsController::class, 'getAllTransactions']);
            Route::post('get-all-transactions-per-quote', [BondsController::class, 'getAllTransactionsPerQuote']);
            Route::post('get-user-transactions', [BondsController::class, 'getUserTransactions']);
            Route::post('get-sent-transactions', [BondsController::class, 'getSentTransactions']);
            Route::post('get-delegated-transactions', [BondsController::class, 'getDelegatedTransactions']);
        });

        //Portfolio management
        Route::middleware(['subscription'])->group(function () {
            Route::post('add-new-portfolio', [BondsController::class, 'addNewPortfolio']);
            Route::post('get-user-portfolios', [BondsController::class, 'getUserPortfolios']);
            Route::post('manage-portfolio', [BondsController::class, 'manageBondsInPortfolio']);

            Route::post('update-portfolio', [BondsController::class, 'updatePortfolio']);
            Route::get('export-portfolio-excel', [BondsController::class, 'exportPortfolioExcel']);
        });



    }
  );


//V1/communication (Has notifications, Messages, etc)

Route::group(
    [
      'prefix' => 'V1/communication',

    ],
    function () {
        //messages
        Route::post('send-message', [MessageController::class, 'sendMessage']);
        Route::post('reply-message', [MessageController::class, 'replyMessage']);

        Route::get('user-messages', [MessageController::class, 'getMessageByUser']);
        // Route::get('unread-user-messages', [MessageController::class, 'getUnreadMessageByUser']);
        Route::get('all-unread-user-messages', [MessageController::class, 'getAllUnreadMessagesForUser']);
        Route::post('mark-message-as-read', [MessageController::class, 'markMessageAsRead']);
        Route::get('message-thread', [MessageController::class, 'messageThread']);
        Route::get('user-message-participants', [MessageController::class, 'getMessageParticipants']);
        Route::post('close-chat', [MessageController::class, 'closeChat']);

        // Message polling endpoints for real-time updates
        Route::get('unread-messages-count', [MessageController::class, 'getUnreadMessagesCount']);
        Route::get('recent-messages', [MessageController::class, 'getRecentMessages']);
        Route::get('message-thread-updates', [MessageController::class, 'getMessageThreadUpdates']);

        //notifications
        Route::get('get-unread-notifications', [NotificationController::class, 'getUnreadNotifications']);
        Route::get('get-all-notifications', [NotificationController::class, 'getAllNotifications']);
        Route::post('mark-all-as-read', [NotificationController::class, 'markAllNotificationsAsRead']);
        Route::post('send-notification', [NotificationController::class, 'sendNotification']);
        Route::post('mark-one-as-read', [NotificationController::class, 'markOneNotificationsAsRead']);
        Route::post('mark-notification', [NotificationController::class, 'markOneNotificationsAsFavoriteOrArchive']);
        Route::post('send-user-notification', [NotificationController::class, 'sendUserNotification']);
        Route::get('unread-count', [NotificationController::class, 'getUnreadCount']);
        Route::get('recent-notifications', [NotificationController::class, 'getRecentNotifications']);

        //emails
        Route::post('create-email', [CommunicationManagement::class, 'createEmail']);
        Route::get('get-email-templates', [CommunicationManagement::class, 'getEmailTemplates']);
        Route::get('get-recipients-by-role', [CommunicationManagement::class, 'getRecipientsByRole']);
        Route::post('preview-template', [CommunicationManagement::class, 'previewTemplate']);

        //sms
        Route::post('create-sms', [CommunicationManagement::class, 'createSms']);
    }
  );

//V1/Permissions

Route::group(
    [
      'prefix' => 'V1/permissions',
    //   'middleware' => [
    //     CheckClientCredentials::class,
    //     //'throttle:30,1'
    //   ]
    ],
    function () {
        Route::get('get-user-permissions', [PermissionManagement::class, 'getUserPermissions']);
        Route::get('get-role-permissions', [PermissionManagement::class, 'getRolePermissions']);
        Route::post('add-user-to-role', [PermissionManagement::class, 'addUserToNewRole']);
        Route::get('get-all-user-roles', [PermissionManagement::class, 'getAllRolesForUser']);
        // Route::post('modify-role-permissions', [PermissionManagement::class, 'modifyRolePermissions']);
        Route::post('modify-user-permissions', [PermissionManagement::class, 'modifyUserPermissions']);
        Route::get('get-users-by-role', [PermissionManagement::class, 'getUsersByRole']);
        Route::get('get-roles', [PermissionManagement::class, 'getRoles']);
    }
  );


  Route::group(
    [
      'prefix' => 'V1/financials',
    //   'middleware' => [
    //     CheckClientCredentials::class,
    //     //'throttle:30,1'
    //   ]
    ],
    function () {
        Route::post('add-subscription-plan', [FinancialController::class, 'addSubscriptionPlan']);
        Route::post('add-sub-features-categories', [FinancialController::class, 'addSubFeaturesCategories']);
        Route::post('add-sub-features', [FinancialController::class, 'addSubFeatures']);
        Route::post('add-billing-details', [FinancialController::class, 'addBillingDetails']);

        Route::post('add-new-subscription', [FinancialController::class, 'addNewSubscription']);
        Route::get('get-sub-plan-details', [FinancialController::class, 'getSubscriptionPlanDetails']);
        Route::get('get-all-subscriptions', [FinancialController::class, 'getAllSubscriptions']);
        Route::get('get-user-subscriptions', [FinancialController::class, 'getUserSubscriptions']);
        Route::get('get-all-sub-plans', [FinancialController::class, 'getAllSubscriptionPlans']);
        Route::get('get-all-sub-features', [FinancialController::class, 'getAllFeatures']);
        Route::get('get-all-feature-categories', [FinancialController::class, 'getAllFeatureCategories']);


    }
  );

//V1/payments (M-Pesa, PayPal, etc)
Route::group(
    [
        'prefix' => 'V1/payments',
    ],
    function () {
        Route::post('mpesa/stk-push', [MpesaController::class, 'initiateStkPush']);
        Route::post('mpesa/callback', [MpesaController::class, 'handleCallback'])->name('mpesa.callback');
        Route::get('mpesa/check-status', [MpesaController::class, 'checkStatus']);
        Route::post('paypal/create-order', [PaypalController::class, 'createOrder']);
        Route::post('paypal/capture-order', [PaypalController::class, 'captureOrder']);
        // PayPal webhook for asynchronous notifications
        Route::post('paypal/webhook', [PaypalController::class, 'handleWebhook'])->name('paypal.webhook');
    }
);

//V1/admin (Admin operations including cache management)
Route::group(
    [
      'prefix' => 'V1/admin',
    ],
    function () {
        // Cache Management Routes
        Route::get('cache/stats', [CacheController::class, 'getCacheStats']);
        Route::post('cache/clear-all', [CacheController::class, 'clearAllCache']);
        Route::post('cache/clear-user', [CacheController::class, 'clearUserCache']);
        Route::post('cache/warm-up-user', [CacheController::class, 'warmUpUserCache']);
    }
  );


//V1/AI (Gemini/DeepSeek Integration)
Route::group(
    [
        'prefix' => 'V1/ai',
    ],
    function () {
        Route::post('chat', [AiController::class, 'chat']);
    }
);
