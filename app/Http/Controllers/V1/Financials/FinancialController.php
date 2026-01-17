<?php

namespace App\Http\Controllers\V1\Financials;

use App\Http\Controllers\Controller;
use App\Http\Controllers\V1\Defaults\StandardFunctions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class FinancialController extends Controller
{
    public function addSubscriptionPlan(Request $request)
    {
        Log::info('Add Subscription Plan Request: '.json_encode($request->all()));

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'level' => 'required | numeric',
            // 'is_active' => 'boolean'
            'user_email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $this->bk_db->beginTransaction();

            $stdfns = new StandardFunctions;
            $user = $stdfns->get_user_id($request->user_email);

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                    'data' => null,
                ]);
            }

            $planId = $this->bk_db->table('subscriptionplan')->insertGetId([
                'Name' => $request->name,
                'Description' => $request->description,
                'Level' => $request->level,
                // 'IsActive' => $request->is_active ?? true,
                'IsActive' => true,
                'created_by' => $user->Id,
                'created_on' => Carbon::now(),
            ]);

            $this->bk_db->commit();

            return response()->json([
                'success' => true,
                'message' => 'Subscription plan created successfully.',
                'data' => ['plan_id' => $planId],
            ]);

        } catch (\Throwable $th) {
            $this->bk_db->rollBack();
            Log::error('Error creating subscription plan: '.$th->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred creating subscription plan.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function addSubFeaturesCategories(Request $request)
    {
        Log::info('Add Subscription Features Categories Request: '.json_encode($request->all()));

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'level' => 'required|numeric',
            'user_email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $this->bk_db->beginTransaction();

            $stdfns = new StandardFunctions;
            $user = $stdfns->get_user_id($request->user_email);

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                    'data' => null,
                ]);
            }

            $categoryId = $this->bk_db->table('subscriptionfeaturescategories')->insertGetId([
                'Name' => $request->name,
                'Description' => $request->description,
                'Level' => $request->level,
                'created_by' => $user->Id,
                'created_on' => Carbon::now(),
            ]);

            $this->bk_db->commit();

            return response()->json([
                'success' => true,
                'message' => 'Subscription features category created successfully.',
                'data' => ['category_id' => $categoryId],
            ]);

        } catch (\Throwable $th) {
            $this->bk_db->rollBack();
            Log::error('Error creating subscription features category: '.$th->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred creating subscription features category.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function addSubFeatures(Request $request)
    {
        Log::info('Add Subscription Features Request: '.json_encode($request->all()));

        $validator = Validator::make($request->all(), [
            'category_id' => 'required|integer|exists:bk_db.subscriptionfeaturescategories,Id',
            'subscription_plan_id' => 'required|integer|exists:bk_db.subscriptionplan,Id',
            'user_email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'level' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $this->bk_db->beginTransaction();

            $stdfns = new StandardFunctions;
            $user = $stdfns->get_user_id($request->user_email);

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                    'data' => null,
                ]);
            }

            $featureId = $this->bk_db->table('subscriptionfeatures')->insertGetId([
                'CategoryId' => $request->category_id,
                'SubscriptionPlanId' => $request->subscription_plan_id,
                'Name' => $request->name,
                'Description' => $request->description,
                'Level' => $request->level,
                'created_by' => $user->Id,
                'created_on' => Carbon::now(),
            ]);

            $this->bk_db->commit();

            return response()->json([
                'success' => true,
                'message' => 'Subscription feature created successfully.',
                'data' => ['feature_id' => $featureId],
            ]);

        } catch (\Throwable $th) {
            $this->bk_db->rollBack();
            Log::error('Error creating subscription feature: '.$th->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred creating subscription feature.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function addBillingDetails(Request $request)
    {
        Log::info('Add Billing Details Request: '.json_encode($request->all()));

        $validator = Validator::make($request->all(), [
            'subscription_plan_id' => 'required|integer|exists:bk_db.subscriptionplan,Id',
            'user_email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
            'days' => 'required|integer',
            'currency' => 'required|integer',
            'unit_price' => 'required|numeric',
            'name' => 'required|string|max:255',
            'description' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $this->bk_db->beginTransaction();

            $stdfns = new StandardFunctions;
            $user = $stdfns->get_user_id($request->user_email);

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                    'data' => null,
                ]);
            }

            $billingId = $this->bk_db->table('billingdetails')->insertGetId([
                'SubscriptionPlanId' => $request->subscription_plan_id,
                'Days' => $request->days,
                'Currency' => $request->currency,
                'UnitPrice' => $request->unit_price,
                'Name' => $request->name,
                'Description' => $request->description,
                'created_by' => $user->Id,
                'created_on' => Carbon::now(),
            ]);

            $this->bk_db->commit();

            return response()->json([
                'success' => true,
                'message' => 'Billing details created successfully.',
                'data' => ['billing_id' => $billingId],
            ]);

        } catch (\Throwable $th) {
            $this->bk_db->rollBack();
            Log::error('Error creating billing details: '.$th->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred creating billing details.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function getAllSubscriptions(Request $request)
    {
        Log::info('Get All Subscriptions Request: '.json_encode($request->all()));

        try {
            // Get all Subscriptions with related plan and user information
            $Subscriptions = $this->bk_db->table('subscriptions')
                ->join('subscriptionplan', 'subscriptions.PlanId', '=', 'subscriptionplan.Id')
                ->join('users', 'subscriptions.UserId', '=', 'users.Id')
                ->select(
                    'subscriptions.*',
                    'subscriptionplan.Name as PlanName',
                    'subscriptionplan.Description as PlanDescription',
                    'users.email as UserEmail'
                )
                ->orderBy('subscriptions.created_on', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'All Subscriptions fetched successfully.',
                'data' => $Subscriptions,
            ]);

        } catch (\Throwable $th) {
            Log::error('Error fetching all Subscriptions: '.$th->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred fetching Subscriptions.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function getAllSubscriptionPlans(Request $request)
    {
        Log::info('Get All Subscription Plans Request');

        try {
            // Get all subscription plans
            $plans = $this->bk_db->table('subscriptionplan')
                ->orderBy('created_on', 'desc')
                ->get();

            // For each plan, get the billing details
            foreach ($plans as $plan) {
                $plan->billingDetails = $this->bk_db->table('billingdetails')
                    ->where('SubscriptionPlanId', $plan->Id)
                    ->get();

                $plan->features = $this->bk_db->table('subscriptionfeatures')
                    ->join('subscriptionfeaturescategories', 'subscriptionfeatures.CategoryId', '=', 'subscriptionfeaturescategories.Id')
                    ->where('subscriptionfeatures.SubscriptionPlanId', $plan->Id)
                    ->select(
                        'subscriptionfeatures.*',
                        'subscriptionfeaturescategories.Name as CategoryName',
                        'subscriptionfeaturescategories.Description as CategoryDescription'
                    )
                    ->get();
            }

            return response()->json([
                'success' => true,
                'message' => 'All subscription plans fetched successfully.',
                'data' => $plans,
            ]);

        } catch (\Throwable $th) {
            Log::error('Error fetching all subscription plans: '.$th->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred fetching subscription plans.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function getAllFeatureCategories(Request $request)
    {
        Log::info('Get All Feature Categories Request');

        try {
            // Get all feature categories
            $categories = $this->bk_db->table('subscriptionfeaturescategories')
                ->orderBy('Level', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'All feature categories fetched successfully.',
                'data' => $categories,
            ]);

        } catch (\Throwable $th) {
            Log::error('Error fetching all feature categories: '.$th->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred fetching feature categories.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function getAllFeatures(Request $request)
    {
        Log::info('Get All Features Request');

        try {
            // Get all subscription features with their category information
            $features = $this->bk_db->table('subscriptionfeatures')
                ->join('subscriptionfeaturescategories', 'subscriptionfeatures.CategoryId', '=', 'subscriptionfeaturescategories.Id')
                ->join('subscriptionplan', 'subscriptionfeatures.SubscriptionPlanId', '=', 'subscriptionplan.Id')
                ->select(
                    'subscriptionfeatures.*',
                    'subscriptionfeaturescategories.Name as CategoryName',
                    'subscriptionfeaturescategories.Description as CategoryDescription',
                    'subscriptionplan.Name as PlanName'
                )
                ->orderBy('subscriptionfeatures.created_on', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'All subscription features fetched successfully.',
                'data' => $features,
            ]);

        } catch (\Throwable $th) {
            Log::error('Error fetching all subscription features: '.$th->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred fetching subscription features.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function getUserSubscriptions(Request $request)
    {
        Log::info('Get User Subscriptions Request: '.json_encode($request->all()));

        $validator = Validator::make($request->all(), [
            'user_email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $stdfns = new StandardFunctions;
            $user = $stdfns->get_user_id($request->user_email);

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                    'data' => null,
                ]);
            }

            // Get user's Subscriptions with plan details
            $Subscriptions = $this->bk_db->table('subscriptions')
                // ->join('subscriptionplan', 'subscriptions.PlanId', '=', 'subscriptionplan.Id')
                ->where('subscriptions.User', $user->Id)
                ->select(
                    'subscriptions.*',
                    // 'subscriptionplan.Name as PlanName',
                    // 'subscriptionplan.Description as PlanDescription'
                )
                ->orderBy('subscriptions.created_on', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'User Subscriptions fetched successfully.',
                'data' => $Subscriptions,
            ]);

        } catch (\Throwable $th) {
            Log::error('Error fetching user Subscriptions: '.$th->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred fetching user Subscriptions.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function getSubscriptionPlanDetails(Request $request)
    {
        Log::info('Get Subscription Plan Details Request: '.json_encode($request->all()));

        $validator = Validator::make($request->all(), [
            'plan_id' => 'required|integer|exists:bk_db.subscriptionplan,Id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Get plan details
            $plan = $this->bk_db->table('subscriptionplan')
                ->where('Id', $request->plan_id)
                ->first();

            if (! $plan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subscription plan not found.',
                    'data' => null,
                ]);
            }

            // Get billing details
            $billingDetails = $this->bk_db->table('billingdetails')
                ->where('SubscriptionPlanId', $request->plan_id)
                ->get();

            // Get features with their categories
            $features = $this->bk_db->table('subscriptionfeatures')
                ->join('subscriptionfeaturescategories', 'subscriptionfeatures.CategoryId', '=', 'subscriptionfeaturescategories.Id')
                ->where('subscriptionfeatures.SubscriptionPlanId', $request->plan_id)
                ->select(
                    'subscriptionfeatures.*',
                    'subscriptionfeaturescategories.Name as CategoryName',
                    'subscriptionfeaturescategories.Description as CategoryDescription'
                )
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Subscription plan details fetched successfully.',
                'data' => [
                    'plan' => $plan,
                    'billing_details' => $billingDetails,
                    'features' => $features,
                ],
            ]);

        } catch (\Throwable $th) {
            Log::error('Error fetching subscription plan details: '.$th->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred fetching subscription plan details.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function addNewSubscription(Request $request)
    {
        Log::info('Add New Subscription Request: '.json_encode($request->all()));

        $validator = Validator::make($request->all(), [
            'user_email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
            'plan_id' => 'required|integer|exists:bk_db.subscriptionplan,Id',
            'amount_paid' => 'required|numeric',
            'discount' => 'required|numeric',
            'subscription_status' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $this->bk_db->beginTransaction();

            $stdfns = new StandardFunctions;
            $user = $stdfns->get_user_id($request->user_email);

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                    'data' => null,
                ]);
            }

            // Get plan details to calculate due date
            $plan = $this->bk_db->table('billingdetails')
                ->where('SubscriptionPlanId', $request->plan_id)
                ->first();

            if (! $plan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subscription plan billing details not found.',
                    'data' => null,
                ]);
            }

            // Calculate due date based on plan days
            $dueDate = Carbon::now()->addDays($plan->Days);

            $subscriptionId = $this->bk_db->table('subscriptions')->insertGetId([
                'User' => $user->Id,
                'PlanId' => $request->plan_id,
                'DueDate' => $dueDate,
                'AmountPaid' => $request->amount_paid,
                'Discount' => $request->discount,
                'SubscriptionStatus' => $request->subscription_status,
                'created_by' => $user->Id,
                'created_on' => Carbon::now(),
            ]);

            $this->bk_db->commit();

            return response()->json([
                'success' => true,
                'message' => 'Subscription created successfully.',
                'data' => [
                    'subscription_id' => $subscriptionId,
                    'due_date' => $dueDate,
                ],
            ]);

        } catch (\Throwable $th) {
            $this->bk_db->rollBack();
            Log::error('Error creating subscription: '.$th->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred creating subscription.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }
}
