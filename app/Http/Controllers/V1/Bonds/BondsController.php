<?php

namespace App\Http\Controllers\V1\Bonds;

use Carbon\Carbon;
use League\Csv\Reader;
use Illuminate\Http\Request;
use App\Events\SendEmailEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Http\Controllers\V1\Defaults\StandardFunctions;
use App\Http\Controllers\V1\Defaults\CommunicationManagement;
use App\Http\Controllers\V1\Notifications\NotificationController;

class BondsController extends Controller
{
    public function uploadCsv(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'CsvFile' => 'required|file|mimes:csv,txt',
            'IsYtm' => 'boolean',
            'IsStatsTable' => 'boolean',
            'IsObiTable' => 'boolean',
            'IsGraphTable' => 'boolean',
            'IsPrimaryMarketTable' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Parse the uploaded CSV file
        $file = $request->file('CsvFile');
        $csv = Reader::createFromPath($file->getRealPath(), 'r');
        $csv->setHeaderOffset(0); // Assumes the first row contains headers
        $records = $csv->getRecords();

        // Define headers for validation
        $headersMap = [
            'Stats Table' => ['otr', 'Filter1', 'Filter2', 'ID', 'Bond Issue No', 'Issue Date', 'Maturity Date', 'Value Date', 'Quoted Yield', 'Spot Yield', 'Dirty Price', 'Coupon', 'Next Cpn (Days)', 'DTM (Yrs)', 'DTC', 'Duration', 'M Duration', 'Convexity', 'Expected Return', 'Expected Shortfall', 'DV01', 'Last 91 Days', 'Last 364 Days', 'LQD Rank', 'Spread', 'Credit Risk premium', 'MD Rank', 'ER Rank', 'Basis'],
            'Graph Table' => ['Date', 'Year', 'Spot rate', 'Nse rate', 'Upper band', 'Lower band'],
            'OBI Table' => ['Date', 'Quoted Yield', 'Spot Yield', 'Dirty Price', 'OBI (K) Index', 'Coupon', 'Duration', 'Expected Return', 'DV01', 'Expected Shortfall', 'OBI TR'],
            'YTM Table' => ['Date', 'taylor Rule', 'Ceiling', 'Floor', 'lamda1', 'lamda2', 'Alpha', 'Beta1', 'Beta2', 'Beta3', 'CBR', 'Rate Projection', 'Inflation', 'Level', 'Slope', 'Carvature'],
            'Primary Market Table' => [ 'Bond Issues', 'Pricing Method', 'DTM / WAL', 'Day-Count', '1st coupon date', '2nd coupon date', 'Spot Rate (%)', 'Par Yield (%)' ],
        ];

        $responseMessages = [];

        // Process based on flags and table mapping
        if ($request->boolean('IsStatsTable')) {
            $this->processTable($records, 'Stats Table', $headersMap['Stats Table'], 'stats_table', $responseMessages);
        }

        if ($request->boolean('IsGraphTable')) {
            $this->processTable($records, 'Graph Table', $headersMap['Graph Table'], 'graph_table', $responseMessages);
        }

        if ($request->boolean('IsObiTable')) {
            $this->processTable($records, 'OBI Table', $headersMap['OBI Table'],'obi_table', $responseMessages);
        }

        if ($request->boolean('IsYtm')) {
            $this->processTable($records, 'YTM Table', $headersMap['YTM Table'], 'ytm_table', $responseMessages);
        }

        if ($request->boolean('IsPrimaryMarketTable')) {
            $this->processTable($records, 'Primary Market Table', $headersMap['Primary Market Table'], 'primary_market_table', $responseMessages);
        }


        return response()->json([
            'message' => 'CSV uploaded and processed successfully',
            'responses' => $responseMessages,
        ], 200);
    }

    private function processTable($records, $tableType, $expectedHeaders, $dbTable, &$responseMessages)
    {
        $recordArray = iterator_to_array($records);
        if (empty($recordArray)) {
            $responseMessages[] = "{$tableType}: No records to process.";
            return;
        }

        $csvHeaders = array_keys($recordArray[0]);

        // Validate CSV headers
        if ($csvHeaders !== $expectedHeaders) {
            $responseMessages[] = "{$tableType}: Invalid headers.";
            return;
        }

        // Insert records into the database
        foreach ($recordArray as $row) {
            $filteredRow = array_combine(
                array_map(fn($header) => str_replace(' ', '', strtolower($header)), $expectedHeaders),
                $row
            );

            $this->bk_db->table($dbTable)->insert($filteredRow);
        }

        $responseMessages[] = "{$tableType}: Successfully inserted records.";
    }

    //Bond Screens Data
    public function getTotalReturnScreen(Request $request)
    {
        try {
        $bonds =  $this->bk_db->table('statstable')
            ->where('Last91Days', '!=', 0)
            ->orderBy('Last91Days', 'desc')
            ->limit(5)
            ->get();

            return response()->json($bonds, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch total return screen bonds', 'message' => $e->getMessage()], 404);
        }
    }

    public function getTotalDurationScreen(Request $request)
    {
        try {
            // Get target duration from request body
            $targetDuration = $request->input('targetDuration');

            //fetch table params for inflation and ytmTr
            $tableParams = $this->bk_db->table('tableparams')->first();
            $inflation = $tableParams->Inflation;
            $ytmTr = $tableParams->YtmTr;
            Log::info("inflation ".$inflation);

            $rateOutlook = $ytmTr > $inflation ? ceil($ytmTr / 0.0025) * 0.0025 : $inflation;

            $averageOTR = round($this->bk_db->table('statstable')->where('Otr', 'OTR')->avg('SpotYield') *100,4) ;

            $averageYield = $this->bk_db->table('statstable')
                ->where('Filter1', '>=', 2)
                ->avg('Coupon') ;

            $averageYield = round($averageYield,4);
            $averageLast91Days = round($this->bk_db->table('statstable')->where('Otr','OTR')->avg('Last91Days'));
            $averageCoupon = round($this->bk_db->table('statstable')->where('Otr','OTR')->avg('Coupon'),4);
            $averageDTMYrs = round($this->bk_db->table('statstable')->where('Otr','OTR')->avg('DtmYrs'), 4);
            $averageDirtyPrice = round($this->bk_db->table('statstable')->where('Otr','OTR')->avg('DirtyPrice'), 4);
            $averageDuration = round($this->bk_db->table('statstable')->where('Otr','OTR')->avg('Duration'), 4);
            $averageMDuration = round($this->bk_db->table('statstable')->where('Otr','OTR')->avg('MDuration'), 4);
            $averageConvexity = round($this->bk_db->table('statstable')->where('Otr','OTR')->avg('Convexity'), 2);
            $averageExpectedReturn = round($this->bk_db->table('statstable')->where('Otr','OTR')->avg('ExpectedReturn'), 2);
            $averageExpectedShortfall = round($this->bk_db->table('statstable')->where('Otr','OTR')->avg('ExpectedShortfall'), 2) * 100;
            $averageDV01 = round($this->bk_db->table('statstable')->where('Otr','OTR')->avg('DV01'), 4);

            // Use provided target duration or fall back to average
            // If targetDuration is provided, use it; otherwise use the calculated average
            $userMDuration = $targetDuration !== null ? (float)$targetDuration * -1 : $averageMDuration * -1;

            Log::info("Target Duration: " . ($targetDuration ?? 'null'));
            Log::info("User M Duration: " . $userMDuration);

            if (empty($userMDuration) || $userMDuration == 0) {
                $bonds = [];
            } else {
                $bonds = $this->bk_db->table('statstable')->get();
                foreach ($bonds as $bond) {
                    $bond->targetMDDev = $userMDuration == 0 ? abs($userMDuration - $bond->MDuration) : abs($userMDuration - $bond->MDuration);
                }
                $bonds = collect($bonds)->sortBy('targetMDDev')->take(5)->values()->toArray();
            }

            $allAverages = [
                'averageOTR' => $averageOTR,
                'averageYield' => $averageYield,
                'averageLast91Days' => $averageLast91Days,
                'averageCoupon' => $averageCoupon,
                'averageDTMYrs' => $averageDTMYrs,
                'averageDirtyPrice' => $averageDirtyPrice,
                'averageDuration' => $averageDuration,
                'averageMDuration' => $averageMDuration,
                'averageConvexity' => $averageConvexity,
                'averageExpectedReturn' => $averageExpectedReturn,
                'averageExpectedShortfall' => $averageExpectedShortfall,
                'averageDV01' => $averageDV01,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Duration Screen Fetched',
                'averages' => $allAverages,
                'rateOutlook' => $rateOutlook,
                'targetDuration' => $targetDuration ?? $averageMDuration,
                'data' => $bonds
            ], 200);
        } catch (\Exception $e) {
            Log::error('Duration Screen Error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch total duration screen bonds', 'message' => $e->getMessage()], 404);
        }
    }

    public function getBarbellAndBullet()
    {
        try {
            $bonds =  $this->bk_db->table('statstable')
                ->where('Last91Days', '!=', 0)
                ->orderBy('Last91Days', 'desc')
                ->limit(5)
                ->get();

            $short = $bonds->sortByDesc(function($bond) {
                return $bond->MDuration;
            })->take(1)->values()->toArray();

            $long = $bonds->sortByDesc(function($bond) {
                return $bond->MDuration;
            })->skip(4)->take(1)->values()->toArray();

            $bullet = $bonds->sortByDesc(function($bond) {
                return $bond->MDuration;
            })->skip(1)->take(3)->values()->toArray();

            return response()->json([
                'short' => $short,
                'long' => $long,
                'bullet' => $bullet
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => 'Failed to fetch barbell and bullet data', 'message' => $e->getMessage()], 404);
        }

    }

    public function getStatsTable()
    {
        try {
            $stats = $this->bk_db->table('statstable')->get()->toArray();

            return response()->json($stats, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch stats table data', 'message' => $e->getMessage()], 500);
        }
    }

    //Portfolio Management

    public function addNewPortfolio(Request $request)
    {
        Log::info('Request: ' . json_encode($request->all()));
        $request->validate([
            'portfolio_name' => 'required|string|max:255',
            'value_date' => 'required|date',
            'description' => 'required|string',
            'user_email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
            'bonds' => 'nullable|array',
            'bonds.*.bond_id' => 'required|integer|exists:bk_db.statstable,Id',
            'bonds.*.type' => 'required|string|in:HFS,HTM,AFS',
            'bonds.*.buying_date' => 'required|date',
            'bonds.*.selling_date' => 'nullable|date',
            'bonds.*.buying_price' => 'required|numeric',
            'bonds.*.selling_price' => 'nullable|numeric',
            'bonds.*.buying_wap' => 'required|numeric',
            'bonds.*.selling_wap' => 'nullable|numeric',
            'bonds.*.face_value_buys' => 'required|integer',
            'bonds.*.face_value_sales' => 'nullable|integer',
            'bonds.*.face_value_bal' => 'required|integer',
            'bonds.*.closing_price' => 'required|numeric',
            'bonds.*.coupon_net' => 'required|numeric',
            'bonds.*.next_cpn_days' => 'required|string',
            'bonds.*.realized_pnl' => 'required|string',
            'bonds.*.unrealized_pnl' => 'required|string',
            'bonds.*.one_yr_total_return' => 'required|numeric',
            'bonds.*.portfolio_value' => 'required|string'
        ]);

        try {
            $this->bk_db->beginTransaction();

            $stdfns = new StandardFunctions();
            $user = $stdfns->get_user_id($request->user_email);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                    'data' => null
                ]);
            }

            // Create new portfolio
            $portfolioId = $this->bk_db->table('portfolio')->insertGetId([
                'Name' => $request->portfolio_name,
                'ValueDate' => $request->value_date,
                'Description' => $request->description,
                'UserId' => $user->Id,
                'created_by' => $user->Id,
                'created_on' => Carbon::now()
            ], 'Id');

            // Add bonds to portfolio data
            foreach ($request->bonds as $bond) {
                $bondData = $this->bk_db->table('statstable')
                    ->where('Id', $bond['bond_id'])
                    ->first();

                if (!$bondData) {
                    continue;
                }

                $this->bk_db->table('portfoliodata')->insert([
                    'PortfolioId' => $portfolioId,
                    'BondId' => $bond['bond_id'],
                    'User' => $user->Id,
                    'Type' => $bond['type'],
                    'BuyingDate' => $bond['buying_date'],
                    'SellingDate' => $bond['selling_date'] ?? null,
                    'BuyingPrice' => $bond['buying_price'],
                    'SellingPrice' => $bond['selling_price'] ?? null,
                    'BuyingWAP' => $bond['buying_wap'],
                    'SellingWAP' => $bond['selling_wap'] ?? null,
                    'FaceValueBuys' => $bond['face_value_buys'],
                    'FaceValueSales' => $bond['face_value_sales'] ?? 0,
                    'FaceValueBAL' => $bond['face_value_bal'],
                    'ClosingPrice' => $bond['closing_price'],
                    'CouponNET' => $bond['coupon_net'],
                    'NextCpnDays' => $bond['next_cpn_days'],
                    'RealizedPNL' => $bond['realized_pnl'],
                    'UnrealizedPNL' => $bond['unrealized_pnl'],
                    'OneYrTotalReturn' => $bond['one_yr_total_return'],
                    'PortfolioValue' => $bond['portfolio_value'],
                    'IsActive' => true,
                    'SpotYTM' => $bondData->SpotYield,
                    'Coupon' => $bondData->Coupon,
                    'Duration' => $bondData->Duration,
                    'MDuration' => $bondData->MDuration,
                    'Dv01' => $bondData->Dv01,
                    'ExpectedShortfall' => $bondData->ExpectedShortfall,
                    'DirtyPrice' => $bondData->DirtyPrice,
                    'created_by' => $user->Id,
                    'created_on' => Carbon::now()
                ]);
            }

            $this->bk_db->commit();

            return response()->json([
                'success' => true,
                'message' => 'Portfolio created successfully.',
                'data' => [
                    'portfolio_id' => $portfolioId
                ]
            ]);

        } catch (\Throwable $th) {
            $this->bk_db->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'An error occurred creating portfolio.',
                'data' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
            ]);
        }
    }

    public function manageBondsInPortfolio(Request $request){

        $request->validate([
            'bond_id' => 'required|integer|exists:bk_db.statstable,Id',
            'action' => 'required|string|in:add,remove',
            'user_email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email'
        ]);

        $bond_id = $request->bond_id;
        $user_email = $request->user_email;

        try{
            // Start transaction
            $this->bk_db->beginTransaction();
            $stdfns = new StandardFunctions();
            $user = $stdfns->get_user_id($request->user_email);
            $bond = $this->bk_db->table('statstable')->where('Id', $request->bond_id)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                    'data' => null
                ]);
            }

            if ($request->action == 'add') {
                // Check for existing bond in portfolio
                $existingBondInPortfolio = $this->bk_db->table('portfolio')
                    ->where('BondId', $bond_id)
                    ->where('User', $user->Id)
                    ->first();

                if ($existingBondInPortfolio) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bond already exists in portfolio.',
                        'data' => null
                    ]);
                }

                // Insert bond into portfolio
                $this->bk_db->table('portfolio')->insert([
                    'BondId' => $bond_id,
                    'User' => $user->Id,
                    'created_on' => Carbon::now(),
                    'created_by' => $user->Id
                ]);

                // Commit transaction
                $this->bk_db->commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Bond added to portfolio successfully.',
                    'data' => null
                ]);

            } elseif ($request->action == 'remove') {
                // Check for existing bond in portfolio
                $existingBondInPortfolio = $this->bk_db->table('portfolio')
                    ->where('BondId', $bond_id)
                    ->where('User', $user->Id)
                    ->first();

                if (!$existingBondInPortfolio) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bond does not exist in portfolio.',
                        'data' => null
                    ]);
                }

                // Delete bond from portfolio
                $this->bk_db->table('portfolio')->where('BondId', $bond_id)->where('User', $user->Id)->delete();

                // Commit transaction
                $this->bk_db->commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Bond removed from portfolio successfully.',
                    'data' => null
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid action.',
                    'data' => null
                ]);
            }

        } catch (\Throwable $th) {
            $this->bk_db->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'An error occurred adding bond to portfolio.',
                'data' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
            ]);
        }
    }

    public function getUserPortfolio(Request $request){
        $request->validate([
            'user_email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email'
        ]);

        $user_email = $request->user_email;

        try{
            $stdfns = new StandardFunctions();
            $user = $stdfns->get_user_id($request->user_email);
            $portfolio = $this->bk_db->table('portfolio')->where('User', $user->Id)->get()->toArray();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                    'data' => null
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Bonds in portfolio fetched successfully.',
                'data' => $portfolio
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred fetching bonds in portfolio.',
                'data' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
            ]);
        }
    }

    public function getUserPortfolios(Request $request)
    {
        // Log::info('Request: ' . json_encode($request->all()));
        $request->validate([
            'user_email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email'
        ]);

        try {
            $stdfns = new StandardFunctions();
            $user = $stdfns->get_user_id($request->user_email);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                    'data' => null
                ]);
            }

            // Get all portfolios for user
            $portfolios = $this->bk_db->table('portfolio')
                ->where('UserId', $user->Id)
                ->get();

            // For each portfolio, get its bonds
            foreach ($portfolios as $portfolio) {
                $portfolio->bonds = $this->bk_db->table('portfoliodata')
                    ->join('statstable', 'portfoliodata.BondId', '=', 'statstable.Id')
                    ->where('portfoliodata.PortfolioId', $portfolio->Id)
                    ->select(
                        'portfoliodata.*',
                        'statstable.BondIssueNo',
                        'statstable.IssueDate',
                        'statstable.MaturityDate',
                        'statstable.SpotYield',
                        'statstable.Coupon',
                        'statstable.Duration',
                        'statstable.MDuration',
                        'statstable.DV01',
                        'statstable.ExpectedShortfall',
                        'statstable.DirtyPrice'
                    )
                    ->get();
            }

            return response()->json([
                'success' => true,
                'message' => 'Portfolios fetched successfully.',
                'data' => $portfolios
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred fetching portfolios.',
                'data' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
            ]);
        }
    }

    public function updatePortfolio(Request $request)
    {
        $request->validate([
            'portfolio_id' => 'required|integer|exists:bk_db.portfolio,Id',
            'portfolio_name' => 'required|string|max:255',
            'value_date' => 'required|date',
            'description' => 'required|string',
            'user_email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
            'bonds' => 'required|array',
            'bonds.*.bond_id' => 'required|integer|exists:bk_db.statstable,Id',
            'bonds.*.type' => 'required|string|in:HFS,HTM,AFS',
            'bonds.*.buying_date' => 'required|date',
            'bonds.*.selling_date' => 'nullable|date',
            'bonds.*.buying_price' => 'required|numeric',
            'bonds.*.selling_price' => 'nullable|numeric',
            'bonds.*.buying_wap' => 'required|numeric',
            'bonds.*.selling_wap' => 'nullable|numeric',
            'bonds.*.face_value_buys' => 'required|integer',
            'bonds.*.face_value_sales' => 'nullable|integer',
            'bonds.*.face_value_bal' => 'required|integer',
            'bonds.*.closing_price' => 'required|numeric',
            'bonds.*.coupon_net' => 'required|numeric',
            'bonds.*.next_cpn_days' => 'required|string',
            'bonds.*.realized_pnl' => 'required|string',
            'bonds.*.unrealized_pnl' => 'required|string',
            'bonds.*.one_yr_total_return' => 'required|numeric',
            'bonds.*.portfolio_value' => 'required|string'
        ]);

        try {
            $this->bk_db->beginTransaction();
            $stdfns = new StandardFunctions();
            $user = $stdfns->get_user_id($request->user_email);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                    'data' => null
                ]);
            }

            // Update portfolio details
            $portfolio = $this->bk_db->table('portfolio')->where('Id', $request->portfolio_id)->first();
            if (!$portfolio) {
                return response()->json([
                    'success' => false,
                    'message' => 'Portfolio not found.',
                    'data' => null
                ]);
            }

            $this->bk_db->table('portfolio')->where('Id', $request->portfolio_id)->update([
                'Name' => $request->portfolio_name,
                'ValueDate' => $request->value_date,
                'Description' => $request->description,
                'dola' => Carbon::now(),
                'altered_by' => $user->Id
            ]);

            // Delete existing bonds in portfoliodata for this portfolio
            $this->bk_db->table('portfoliodata')->where('PortfolioId', $request->portfolio_id)->delete();

            // Add updated bonds to portfoliodata
            foreach ($request->bonds as $bond) {
                $bondData = $this->bk_db->table('statstable')->where('Id', $bond['bond_id'])->first();
                if (!$bondData) {
                    continue;
                }
                $this->bk_db->table('portfoliodata')->insert([
                    'PortfolioId' => $request->portfolio_id,
                    'BondId' => $bond['bond_id'],
                    'User' => $user->Id,
                    'Type' => $bond['type'],
                    'BuyingDate' => $bond['buying_date'],
                    'SellingDate' => $bond['selling_date'] ?? null,
                    'BuyingPrice' => $bond['buying_price'],
                    'SellingPrice' => $bond['selling_price'] ?? null,
                    'BuyingWAP' => $bond['buying_wap'],
                    'SellingWAP' => $bond['selling_wap'] ?? null,
                    'FaceValueBuys' => $bond['face_value_buys'],
                    'FaceValueSales' => $bond['face_value_sales'] ?? 0,
                    'FaceValueBAL' => $bond['face_value_bal'],
                    'ClosingPrice' => $bond['closing_price'],
                    'CouponNET' => $bond['coupon_net'],
                    'NextCpnDays' => $bond['next_cpn_days'],
                    'RealizedPNL' => $bond['realized_pnl'],
                    'UnrealizedPNL' => $bond['unrealized_pnl'],
                    'OneYrTotalReturn' => $bond['one_yr_total_return'],
                    'PortfolioValue' => $bond['portfolio_value'],
                    'IsActive' => true,
                    'SpotYTM' => $bondData->SpotYield,
                    'Coupon' => $bondData->Coupon,
                    'Duration' => $bondData->Duration,
                    'MDuration' => $bondData->MDuration,
                    'Dv01' => $bondData->Dv01,
                    'ExpectedShortfall' => $bondData->ExpectedShortfall,
                    'DirtyPrice' => $bondData->DirtyPrice,
                    'created_by' => $user->Id,
                    'created_on' => Carbon::now()
                    // 'dola' => Carbon::now(),
                    // 'altered_by' => $user->Id
                ]);
            }

            $this->bk_db->commit();

            return response()->json([
                'success' => true,
                'message' => 'Portfolio updated successfully.',
                'data' => [
                    'portfolio_id' => $request->portfolio_id
                ]
            ]);
        } catch (\Throwable $th) {
            $this->bk_db->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'An error occurred updating portfolio.',
                'data' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
            ]);
        }
    }

    public function exportPortfolioExcel(Request $request)
    {
        $request->validate([
            'portfolio_id' => 'required|integer|exists:bk_db.portfolio,Id'
        ]);
        try {
            $portfolioId = $request->portfolio_id;
            // if (!$portfolioId) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Portfolio ID is required.',
            //     ]);
            // }

            // Get portfolio details
            $portfolio = $this->bk_db->table('portfolio')
                ->where('Id', $portfolioId)
                ->first();

            if (!$portfolio) {
                return response()->json([
                    'success' => false,
                    'message' => 'Portfolio not found.',
                ]);
            }

            // Get all bonds in the portfolio
            $bonds = $this->bk_db->table('portfoliodata')
                ->where('PortfolioId', $portfolioId)
                ->get();

            if ($bonds->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No bonds found in this portfolio.',
                ]);
            }

            // Create Excel
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Portfolio');

            // Set header row
            $sheet->setCellValue('A1', 'Portfolio: ' . $portfolio->Name);
            $sheet->setCellValue('A2', 'Value Date: ' . $portfolio->ValueDate);
            $sheet->setCellValue('A3', 'Description: ' . $portfolio->Description);

            // Create header row for bonds table
            $headers = [
                'Bond Issue No', 'Type', 'Buying Date', 'Buying Price',
                'Buying WAP', 'Face Value Buys', 'Selling Date', 'Selling Price',
                'Selling WAP', 'Face Value Sales', 'Face Value Balance', 'Closing Price',
                'Coupon NET', 'Next Coupon Days', 'Realized P&L', 'Unrealized P&L',
                'One Year Total Return', 'Coupon', 'Duration', 'Modified Duration',
                'DV01', 'Expected Shortfall', 'Spot YTM', 'Dirty Price', 'Portfolio Value'
            ];

            foreach (array_values($headers) as $colIndex => $header) {
                $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
                $sheet->setCellValue($column . '5', $header);
            }

            // Add bond data
            $rowIndex = 6;
            foreach ($bonds as $bond) {
                $colIndex = 1;
                $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $bond->BondIssueNo);
                $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $bond->Type);
                $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $bond->BuyingDate);
                $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $bond->BuyingPrice);
                $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $bond->BuyingWAP);
                $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $bond->FaceValueBuys);
                $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $bond->SellingDate ?? '');
                $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $bond->SellingPrice ?? '');
                $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $bond->SellingWAP ?? '');
                $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $bond->FaceValueSales ?? '');
                $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $bond->FaceValueBAL);
                $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $bond->ClosingPrice);
                $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $bond->CouponNET);
                $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $bond->NextCpnDays);
                $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $bond->RealizedPNL);
                $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $bond->UnrealizedPNL);
                $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $bond->OneYrTotalReturn);
                $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $bond->Coupon);
                $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $bond->Duration);
                $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $bond->MDuration);
                $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $bond->Dv01);
                $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $bond->ExpectedShortfall);
                $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $bond->SpotYTM);
                $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $bond->DirtyPrice);
                $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $bond->PortfolioValue);
                $rowIndex++;
            }

            // Auto size columns
            foreach (range('A', 'Y') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Create writer
            $writer = new Xlsx($spreadsheet);
            $fileName = 'portfolio_' . $request->portfolio_id . '_' . date('Y-m-d') . '.xlsx';
            $tempFile = tempnam(sys_get_temp_dir(), 'portfolio_');
            $writer->save($tempFile);

            return response()->download($tempFile, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred exporting portfolio to Excel.',
                'data' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
            ]);
        }
    }

    //Quotes Management
    private function calculateIndicativeRange($bondId)
{
    // Get bond details from statstable
    $bond = $this->bk_db->table('statstable')->where('Id', $bondId)->first();

    if (!$bond || !isset($bond->spotYTM) || !isset($bond->Spread)) {
        return "N/A";
    }

    $spotYTM = floatval($bond->spotYTM);
    $spread = floatval($bond->Spread);

    $lowerBound = max(0, round(($spotYTM - $spread) * 100, 2));
    $upperBound = round(($spotYTM + $spread) * 100, 2);

    return number_format($lowerBound, 2) . '% - ' . number_format($upperBound, 2) . '%';
}

    public function createQuote(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bond_id' => 'required|integer|exists:bk_db.statstable,Id',
            'IsBid' => 'required|boolean',
            'IsOffer' => 'required|boolean',
            'bid_price' => 'required|numeric|required_if:IsBid,true',
            'offer_price' => 'required|numeric|required_if:IsOffer,true',
            'offer_yield' => 'required|numeric|required_if:IsOffer,true',
            'bid_yield' => 'required|numeric|required_if:IsBid,true',
            'offer_amount' => 'required|numeric|required_if:IsOffer,true',
            'bid_amount' => 'required|numeric|required_if:IsBid,true',
            // 'face_value' => 'required|numeric',
            // 'settlement_date' => 'required|date',
            //'indicative_range' => 'required|string',
                // 'indicative_lower' => 'required|numeric',
                // 'indicative_higher'
            'assigned_by' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
            // 'is_active' => 'required|boolean',
            // 'is_accepted' => 'required|boolean',
            // 'total_receivable' => 'required|numeric',
            // 'total_payable' => 'required|numeric',
            // 'other_levies' => 'required|numeric',
            // 'commission_nse' => 'required|numeric',
            // 'consideration' => 'required|numeric',
            // 'viewing_party' => 'nullable|email|exists:bk_db.portaluserlogoninfo,Email',


        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Start transaction
            $this->bk_db->beginTransaction();

            $stdfns = new StandardFunctions();
            $user = $stdfns->get_user_id($request->assigned_by);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                    'data' => null
                ]);
            }

            // Check if the bond exists
            $bond = $this->bk_db->table('statstable')->where('Id', $request->bond_id)->first();
            if (!$bond) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bond not found.',
                    'data' => null
                ]);
            }

            // Generate placement number
            $placement_no = $stdfns->generatePlacementNumber();

            // Get viewing party ID if provided
            // $viewing_party_id = null;
            // if ($request->viewing_party) {
            //     $viewing_party = $stdfns->get_user_id($request->viewing_party);
            //     if ($viewing_party) {
            //         $viewing_party_id = $viewing_party->Id;
            //     }
            //     else{
            //         return response()->json([
            //             'success' => false,
            //             'message' => 'Viewing party not found.',
            //             'data' => null
            //         ]);
            //     }
            // }

            // Insert into quotebook
            $quoteId = $this->bk_db->table('quotebook')->insertGetId([
                'BondIssueNo' => $request->bond_id,
                'PlacementNo' => $placement_no,
                'IsBid' => $request->IsBid,
                'IsOffer' => $request->IsOffer,
                'BidPrice' => $request->bid_price,
                'BidYield' => $request->bid_yield,
                'OfferPrice' => $request->offer_price,
                'OfferYield' => $request->offer_yield,
                'BidAmount' => $request->bid_amount,
                'OfferAmount' => $request->offer_amount,
                // 'FaceValue' => $request->face_value,
                'SettlementDate' => now()->addWeekdays(3),
                // 'IndicativeRange' => $request->indicative_range,
                'AssignedBy' => $user->Id,
                // 'ViewingParty' => $viewing_party_id,
                'IsActive' => true,
                // 'IsAccepted' => $request->is_accepted,
                // 'TotalReceivable' => $request->total_receivable,
                // 'TotalPayable' => $request->total_payable,
                // 'OtherLevies' => $request->other_levies,
                // 'CommissionNSE' => $request->commission_nse,
                // 'Consideration' => $request->consideration,
                'ExitDate' => now()->addWeekdays(3),
                'created_on' => Carbon::now(),
                'created_by' => $user->Id,
                ], 'Id');

            // If viewing party is set, send notification and email
            // if ($viewing_party_id) {
            //     // Send notification
            //     $notifs = new NotificationController();
            //     $notification_status = $notifs->createNotification(
            //         $viewing_party_id,
            //         9, // Quote on behalf notification type
            //         "Broker/Dealer/Agent {$user->CompanyName} has sent Quote on your behalf. PlacementNo- {$placement_no}",
            //         $quoteId,
            //         null
            //     );

            //     if ($notification_status['success'] == false) {
            //         $this->bk_db->rollBack();
            //         return response()->json($notification_status);
            //     }

            //     // Send email
            //     $communication_manager = new CommunicationManagement();
            //     $emailResponse = $communication_manager->composeMail(
            //         $request->viewing_party,
            //         $viewing_party->FirstName,
            //         false,
            //         false,
            //         false,
            //         true,
            //         false,
            //         false,
            //         false,
            //         "Quote Created On Your Behalf",
            //         "Broker/Dealer/Agent {$user->CompanyName} has sent Quote on your behalf. PlacementNo- {$placement_no}"
            //     );

            //     if ($emailResponse['success'] == false) {
            //         $this->bk_db->rollBack();
            //         return response()->json($emailResponse, 500);
            //     }
            // }

            //TODO: Add notification and email FOR THOSE WHO HAVE SUBMITTED BID AND HAVE SUBSCRIBED TO NOTIFICATIONS TOO
            // Commit transaction
            $this->bk_db->commit();

            return response()->json([
                'success' => true,
                'message' => 'Quote created successfully.',
                'data' => [
                    'quote_id' => $quoteId,
                    'placement_no' => $placement_no
                ]
            ]);

        } catch (\Throwable $th) {
            $this->bk_db->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'An error occurred creating quote.',
                'data' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
            ]);
        }
    }
    public function getUserQuotes(Request $request){

       $request->validate([
            'user_email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email'
        ]);

        $user_email = $request->user_email;

        try{
            $stdfns = new StandardFunctions();
            $user = $stdfns->get_user_id($request->user_email);
            $quotes = $this->bk_db->table('quotebook')
                ->join('statstable', 'quotebook.BondIssueNo', '=', 'statstable.Id')
                ->select('quotebook.*', 'statstable.BondIssueNo as BondIssueNo')
                ->where('quotebook.AssignedBy', $user->Id)
                ->where('quotebook.ExitDate', '>=', Carbon::now())
                ->orderBy('statstable.BondIssueNo')
                ->orderBy('quotebook.OfferPrice', 'DESC')
                ->orderBy('quotebook.BidPrice', 'DESC')
                ->get()
                ->toArray();

            return response()->json([
                'success' => true,
                'message' => 'User Quotes fetched successfully.',
                'data' => $quotes
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred fetching quotes.',
                'data' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
            ]);
        }

    }

    public function getQuotes(Request $request){
        try{
            $request->validate([
                'user_email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email'
            ]);

            $user_email = $request->user_email;
            $stdfns = new StandardFunctions();
            $user = $stdfns->get_user_id($user_email);

            // Get all quotes with bond details and transactions
            $allQuotes = $this->bk_db->table('quotebook')
                ->join('statstable', 'quotebook.BondIssueNo', '=', 'statstable.Id')
                ->join('portaluserlogoninfo', 'quotebook.AssignedBy', '=', 'portaluserlogoninfo.Id')
                ->select(
                    'quotebook.*',
                    'statstable.BondIssueNo',
                    'portaluserlogoninfo.Email as AssignedBy',
                    DB::raw('CASE WHEN quotebook.IsBid = 1 THEN "Bid" ELSE "Offer" END as QuoteType')
                )
                ->orderBy('statstable.BondIssueNo')
                ->orderBy('quotebook.OfferPrice', 'DESC')
                ->orderBy('quotebook.BidPrice', 'DESC')
                ->get()
                ->map(function($quote) use ($user) {
                    // Get transactions for this quote
                    $transactions = $this->bk_db->table('quotetransactions as qt')
                        ->leftJoin('portaluserlogoninfo as p_init', 'qt.InitiatedBy', '=', 'p_init.Id')
                        ->leftJoin('bondkonnect_db.users as u_init', 'p_init.Id', '=', 'u_init.portal_id')
                        ->leftJoin('portaluserlogoninfo as p_owner', 'qt.QuoteId', '=', 'p_owner.Id') // Wait, Quote owner is qb.AssignedBy
                        // Actually, I need to join with quotebook again to get AssignedBy if I want the owner
                        ->where('qt.QuoteId', $quote->Id)
                        ->select('qt.*', 'p_init.Email as initiator_email', 'u_init.id as initiator_id', DB::raw("CONCAT(p_init.FirstName, ' ', IFNULL(p_init.OtherNames, '')) as initiator_name"))
                        ->get()
                        ->map(function($transaction) use ($quote, $user) {
                            // If current user is the quote owner, the counterparty is the initiator
                            // If current user is the initiator, the counterparty is the quote owner (AssignedBy)
                            
                            $isQuoteOwner = $quote->AssignedBy === $user->Email;
                            
                            return [
                                'id' => $transaction->Id,
                                'transaction_no' => $transaction->TransactionNo,
                                'bid_amount' => $transaction->BidAmount,
                                'offer_amount' => $transaction->OfferAmount,
                                'bid_price' => $transaction->BidPrice,
                                'offer_price' => $transaction->OfferPrice,
                                'bid_yield' => $transaction->BidYield,
                                'offer_yield' => $transaction->OfferYield,
                                'is_pending' => $transaction->IsPending,
                                'is_accepted' => $transaction->IsAccepted,
                                'is_rejected' => $transaction->IsRejected,
                                'is_delegated' => $transaction->IsDelegated,
                                'created_on' => $transaction->created_on,
                                'counterparty_id' => $isQuoteOwner ? $transaction->initiator_id : null, // We need to fetch quote owner's ID if user is initiator
                                'counterparty_name' => $isQuoteOwner ? $transaction->initiator_name : 'Quote Owner',
                                'counterparty_email' => $isQuoteOwner ? $transaction->initiator_email : $quote->AssignedBy
                            ];
                        });

                    $quote->transactions = $transactions;
                    return $quote;
                });

            // Get user's submissions (including where they are viewing party)
            $userQuotes = $this->bk_db->table('quotebook')
                ->join('statstable', 'quotebook.BondIssueNo', '=', 'statstable.Id')
                ->join('portaluserlogoninfo', 'quotebook.AssignedBy', '=', 'portaluserlogoninfo.Id')
                ->select(
                    'quotebook.*',
                    'statstable.BondIssueNo',
                    'portaluserlogoninfo.Email as AssignedBy',
                    DB::raw('CASE WHEN quotebook.IsBid = 1 THEN "Bid" ELSE "Offer" END as QuoteType')
                )
                ->where(function($query) use ($user) {
                    $query->where('quotebook.AssignedBy', $user->Id)
                          ->orWhere('quotebook.ViewingParty', $user->Id);
                })
                ->orderBy('statstable.BondIssueNo')
                ->orderBy('quotebook.OfferPrice', 'DESC')
                ->orderBy('quotebook.BidPrice', 'DESC')
                ->get()
                ->map(function($quote) use ($user) {
                    // Get transactions for this quote
                    $transactions = $this->bk_db->table('quotetransactions as qt')
                        ->leftJoin('portaluserlogoninfo as p_init', 'qt.InitiatedBy', '=', 'p_init.Id')
                        ->leftJoin('bondkonnect_db.users as u_init', 'p_init.Id', '=', 'u_init.portal_id')
                        ->where('qt.QuoteId', $quote->Id)
                        ->select('qt.*', 'p_init.Email as initiator_email', 'u_init.id as initiator_id', DB::raw("CONCAT(p_init.FirstName, ' ', IFNULL(p_init.OtherNames, '')) as initiator_name"))
                        ->get()
                        ->map(function($transaction) use ($quote, $user) {
                            $isQuoteOwner = $quote->AssignedBy === $user->Email;
                            return [
                                'id' => $transaction->Id,
                                'transaction_no' => $transaction->TransactionNo,
                                'bid_amount' => $transaction->BidAmount,
                                'offer_amount' => $transaction->OfferAmount,
                                'bid_price' => $transaction->BidPrice,
                                'offer_price' => $transaction->OfferPrice,
                                'bid_yield' => $transaction->BidYield,
                                'offer_yield' => $transaction->OfferYield,
                                'is_pending' => $transaction->IsPending,
                                'is_accepted' => $transaction->IsAccepted,
                                'is_rejected' => $transaction->IsRejected,
                                'is_delegated' => $transaction->IsDelegated,
                                'created_on' => $transaction->created_on,
                                'counterparty_id' => $isQuoteOwner ? $transaction->initiator_id : null,
                                'counterparty_name' => $isQuoteOwner ? $transaction->initiator_name : 'Quote Owner',
                                'counterparty_email' => $isQuoteOwner ? $transaction->initiator_email : $quote->AssignedBy
                            ];
                        });

                    $quote->transactions = $transactions;
                    return $quote;
                });

            // Get viewing party quotes
            $viewingPartyQuotes = $this->bk_db->table('quotebook')
                ->join('statstable', 'quotebook.BondIssueNo', '=', 'statstable.Id')
                ->join('portaluserlogoninfo', 'quotebook.AssignedBy', '=', 'portaluserlogoninfo.Id')
                ->select(
                    'quotebook.*',
                    'statstable.BondIssueNo',
                    'portaluserlogoninfo.Email as AssignedBy',
                    DB::raw('CASE WHEN quotebook.IsBid = 1 THEN "Bid" ELSE "Offer" END as QuoteType')
                )
                ->where('ViewingParty', $user->Id)
                ->orderBy('statstable.BondIssueNo')
                ->orderBy('quotebook.OfferPrice', 'DESC')
                ->orderBy('quotebook.BidPrice', 'DESC')
                ->get()
                ->map(function($quote) use ($user) {
                    // Get transactions for this quote
                    $transactions = $this->bk_db->table('quotetransactions as qt')
                        ->leftJoin('portaluserlogoninfo as p_init', 'qt.InitiatedBy', '=', 'p_init.Id')
                        ->leftJoin('bondkonnect_db.users as u_init', 'p_init.Id', '=', 'u_init.portal_id')
                        ->where('qt.QuoteId', $quote->Id)
                        ->select('qt.*', 'p_init.Email as initiator_email', 'u_init.id as initiator_id', DB::raw("CONCAT(p_init.FirstName, ' ', IFNULL(p_init.OtherNames, '')) as initiator_name"))
                        ->get()
                        ->map(function($transaction) use ($quote, $user) {
                            $isQuoteOwner = $quote->AssignedBy === $user->Email;
                            return [
                                'id' => $transaction->Id,
                                'transaction_no' => $transaction->TransactionNo,
                                'bid_amount' => $transaction->BidAmount,
                                'offer_amount' => $transaction->OfferAmount,
                                'bid_price' => $transaction->BidPrice,
                                'offer_price' => $transaction->OfferPrice,
                                'bid_yield' => $transaction->BidYield,
                                'offer_yield' => $transaction->OfferYield,
                                'is_pending' => $transaction->IsPending,
                                'is_accepted' => $transaction->IsAccepted,
                                'is_rejected' => $transaction->IsRejected,
                                'is_delegated' => $transaction->IsDelegated,
                                'created_on' => $transaction->created_on,
                                'counterparty_id' => $isQuoteOwner ? $transaction->initiator_id : null,
                                'counterparty_name' => $isQuoteOwner ? $transaction->initiator_name : 'Quote Owner',
                                'counterparty_email' => $isQuoteOwner ? $transaction->initiator_email : $quote->AssignedBy
                            ];
                        });

                    $quote->transactions = $transactions;
                    return $quote;
                });

            // Get delegated quotes
            $isDelegate = $this->bk_db->table('leaveservice')
                ->where('Colleague', $user->Id)
                ->where('EndDate', '>=', Carbon::now())
                ->first();

            $delegatedQuotes = collect([]);
            if ($isDelegate) {
                // Get quotes assigned to the delegating user
                $delegatedQuotes = $this->bk_db->table('quotebook')
                    ->join('statstable', 'quotebook.BondIssueNo', '=', 'statstable.Id')
                    ->join('portaluserlogoninfo', 'quotebook.AssignedBy', '=', 'portaluserlogoninfo.Id')
                    ->select(
                        'quotebook.*',
                        'statstable.BondIssueNo',
                        'portaluserlogoninfo.Email as AssignedBy',
                        DB::raw('CASE WHEN quotebook.IsBid = 1 THEN "Bid" ELSE "Offer" END as QuoteType')
                    )
                    ->where('AssignedBy', $isDelegate->AssignedBy)
                    ->where('ExitDate', '>=', Carbon::now())
                    ->get()
                    ->map(function($quote) use ($user) {
                        // Get transactions for this quote
                        $transactions = $this->bk_db->table('quotetransactions as qt')
                            ->leftJoin('portaluserlogoninfo as p_init', 'qt.InitiatedBy', '=', 'p_init.Id')
                            ->leftJoin('bondkonnect_db.users as u_init', 'p_init.Id', '=', 'u_init.portal_id')
                            ->where('qt.QuoteId', $quote->Id)
                            ->select('qt.*', 'p_init.Email as initiator_email', 'u_init.id as initiator_id', DB::raw("CONCAT(p_init.FirstName, ' ', IFNULL(p_init.OtherNames, '')) as initiator_name"))
                            ->get()
                            ->map(function($transaction) use ($quote, $user) {
                                $isQuoteOwner = $quote->AssignedBy === $user->Email;
                                return [
                                    'id' => $transaction->Id,
                                    'transaction_no' => $transaction->TransactionNo,
                                    'bid_amount' => $transaction->BidAmount,
                                    'offer_amount' => $transaction->OfferAmount,
                                    'bid_price' => $transaction->BidPrice,
                                    'offer_price' => $transaction->OfferPrice,
                                    'bid_yield' => $transaction->BidYield,
                                    'offer_yield' => $transaction->OfferYield,
                                    'is_pending' => $transaction->IsPending,
                                    'is_accepted' => $transaction->IsAccepted,
                                    'is_rejected' => $transaction->IsRejected,
                                    'is_delegated' => $transaction->IsDelegated,
                                    'created_on' => $transaction->created_on,
                                    'counterparty_id' => $isQuoteOwner ? $transaction->initiator_id : null,
                                    'counterparty_name' => $isQuoteOwner ? $transaction->initiator_name : 'Quote Owner',
                                    'counterparty_email' => $isQuoteOwner ? $transaction->initiator_email : $quote->AssignedBy
                                ];
                            });

                        $quote->transactions = $transactions;
                        return $quote;
                    });

                // Get quotes where delegating user is viewing party
                $delegatedViewingPartyQuotes = $this->bk_db->table('quotebook')
                    ->join('statstable', 'quotebook.BondIssueNo', '=', 'statstable.Id')
                    ->join('portaluserlogoninfo', 'quotebook.AssignedBy', '=', 'portaluserlogoninfo.Id')
                    ->select(
                        'quotebook.*',
                        'statstable.BondIssueNo',
                        'portaluserlogoninfo.Email as AssignedBy',
                        DB::raw('CASE WHEN quotebook.IsBid = 1 THEN "Bid" ELSE "Offer" END as QuoteType')
                    )
                    ->where('ViewingParty', $isDelegate->AssignedBy)
                    ->where('ExitDate', '>=', Carbon::now())
                    ->get()
                    ->map(function($quote) use ($user) {
                        // Get transactions for this quote
                        $transactions = $this->bk_db->table('quotetransactions as qt')
                            ->leftJoin('portaluserlogoninfo as p_init', 'qt.InitiatedBy', '=', 'p_init.Id')
                            ->leftJoin('bondkonnect_db.users as u_init', 'p_init.Id', '=', 'u_init.portal_id')
                            ->where('qt.QuoteId', $quote->Id)
                            ->select('qt.*', 'p_init.Email as initiator_email', 'u_init.id as initiator_id', DB::raw("CONCAT(p_init.FirstName, ' ', IFNULL(p_init.OtherNames, '')) as initiator_name"))
                            ->get()
                            ->map(function($transaction) use ($quote, $user) {
                                $isQuoteOwner = $quote->AssignedBy === $user->Email;
                                return [
                                    'id' => $transaction->Id,
                                    'transaction_no' => $transaction->TransactionNo,
                                    'bid_amount' => $transaction->BidAmount,
                                    'offer_amount' => $transaction->OfferAmount,
                                    'bid_price' => $transaction->BidPrice,
                                    'offer_price' => $transaction->OfferPrice,
                                    'bid_yield' => $transaction->BidYield,
                                    'offer_yield' => $transaction->OfferYield,
                                    'is_pending' => $transaction->IsPending,
                                    'is_accepted' => $transaction->IsAccepted,
                                    'is_rejected' => $transaction->IsRejected,
                                    'is_delegated' => $transaction->IsDelegated,
                                    'created_on' => $transaction->created_on,
                                    'counterparty_id' => $isQuoteOwner ? $transaction->initiator_id : null,
                                    'counterparty_name' => $isQuoteOwner ? $transaction->initiator_name : 'Quote Owner',
                                    'counterparty_email' => $isQuoteOwner ? $transaction->initiator_email : $quote->AssignedBy
                                ];
                            });

                        $quote->transactions = $transactions;
                        return $quote;
                    });

                // Get quotes delegated to the delegating user
                $delegatedToUserQuotes = $this->bk_db->table('quotebook')
                    ->join('statstable', 'quotebook.BondIssueNo', '=', 'statstable.Id')
                    ->join('portaluserlogoninfo', 'quotebook.AssignedBy', '=', 'portaluserlogoninfo.Id')
                    ->select(
                        'quotebook.*',
                        'statstable.BondIssueNo',
                        'portaluserlogoninfo.Email as AssignedBy',
                        DB::raw('CASE WHEN quotebook.IsBid = 1 THEN "Bid" ELSE "Offer" END as QuoteType')
                    )
                    ->where('AssignedBy', $user->Id)
                    ->where('ExitDate', '>=', Carbon::now())
                    ->get()
                    ->map(function($quote) use ($user) {
                        // Get transactions for this quote
                        $transactions = $this->bk_db->table('quotetransactions as qt')
                            ->leftJoin('portaluserlogoninfo as p_init', 'qt.InitiatedBy', '=', 'p_init.Id')
                            ->leftJoin('bondkonnect_db.users as u_init', 'p_init.Id', '=', 'u_init.portal_id')
                            ->where('qt.QuoteId', $quote->Id)
                            ->select('qt.*', 'p_init.Email as initiator_email', 'u_init.id as initiator_id', DB::raw("CONCAT(p_init.FirstName, ' ', IFNULL(p_init.OtherNames, '')) as initiator_name"))
                            ->get()
                            ->map(function($transaction) use ($quote, $user) {
                                $isQuoteOwner = $quote->AssignedBy === $user->Email;
                                return [
                                    'id' => $transaction->Id,
                                    'transaction_no' => $transaction->TransactionNo,
                                    'bid_amount' => $transaction->BidAmount,
                                    'offer_amount' => $transaction->OfferAmount,
                                    'bid_price' => $transaction->BidPrice,
                                    'offer_price' => $transaction->OfferPrice,
                                    'bid_yield' => $transaction->BidYield,
                                    'offer_yield' => $transaction->OfferYield,
                                    'is_pending' => $transaction->IsPending,
                                    'is_accepted' => $transaction->IsAccepted,
                                    'is_rejected' => $transaction->IsRejected,
                                    'is_delegated' => $transaction->IsDelegated,
                                    'created_on' => $transaction->created_on,
                                    'counterparty_id' => $isQuoteOwner ? $transaction->initiator_id : null,
                                    'counterparty_name' => $isQuoteOwner ? $transaction->initiator_name : 'Quote Owner',
                                    'counterparty_email' => $isQuoteOwner ? $transaction->initiator_email : $quote->AssignedBy
                                ];
                            });

                        $quote->transactions = $transactions;
                        return $quote;
                    });

                // Combine all delegated quotes
                $delegatedQuotes = $delegatedQuotes->concat($delegatedViewingPartyQuotes)->concat($delegatedToUserQuotes);
            }

            return response()->json([
                'success' => true,
                'message' => 'All Quotes fetched successfully.',
                'data' => [
                    'all_quotes' => $allQuotes,
                    'user_quotes' => $userQuotes,
                    'viewing_party_quotes' => $viewingPartyQuotes,
                    'delegated_quotes' => $delegatedQuotes
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch quotes',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getActiveQuotes(Request $request){
       try{

        $quotes = $this->bk_db->table('quotebook')
            ->join('statstable', 'quotebook.BondIssueNo', '=', 'statstable.Id')
            ->select('quotebook.*', 'statstable.BondIssueNo as BondIssueNo')
            ->where('quotebook.ExitDate', '>=',Carbon::now())
            ->get()
            ->toArray();

        return response()->json([
            'success' => true,
            'message' => 'Active Quotes fetched successfully.',
            'data' => $quotes
        ]);

       } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch active quotes', 'message' => $e->getMessage()], 404);
       }


    }

    public function getViewingPartyQuotes(Request $request){
        $request->validate([
            'user_email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email'
        ]);
        $email = $request->user_email;

        try {

            $this->bk_db->beginTransaction();
            $stdfns = new StandardFunctions();
            $user = $stdfns->get_user_id($email);

            $quotes = $this->bk_db->table('quotebook')
                ->join('statstable', 'quotebook.BondIssueNo', '=', 'statstable.Id')
                ->select('quotebook.*', 'statstable.BondIssueNo as BondIssueNo')
                ->where('ViewingParty', $user->Id)
                ->where('IsActive', true)
                ->orderBy('statstable.BondIssueNo')
                ->orderBy('quotebook.OfferPrice', 'DESC')
                ->orderBy('quotebook.BidPrice', 'DESC')
                ->get();

            $this->bk_db->commit();

            return response()->json([
                'success' => true,
                'message' => 'Viewing party quotes fetched successfully.',
                'data' => $quotes
            ]);
        } catch (\Exception $e) {
            $this->bk_db->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch viewing party quotes: ' . $e->getMessage()
            ], 500);
        }
    }

    public function delegatedQuotes(Request $request){
        $request->validate([
            'delegate_email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
        ]);

        $delegate_email = $request->delegate_email;

        try{
            // Start transaction
            $this->bk_db->beginTransaction();
            $stdfns = new StandardFunctions();

            $delegate = $stdfns->get_user_id($delegate_email);

            if (!$delegate) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                    'data' => null
                ]);
            }

            //get to see if the user is a delegate or not from leaveservice
            $isDelegate = $this->bk_db->table('leaveservice')->where('Colleague', $delegate->Id)->where('EndDate', '>=' ,Carbon::now())->first();
            if (!$isDelegate) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not a delegate.',
                    'data' => null
                ]);
            }

            // Fetch delegated quotes
            $delegatedQuotes = $this->bk_db->table('quotebook')->where('AssignedBy', $isDelegate->AssignedBy)->where('ExitDate', '>=',Carbon::now())->get()->toArray();

            return response()->json([
                'success' => true,
                'message' => ' delegated quotes fetched successfully.',
                'data' => $delegatedQuotes
            ]);

        } catch (\Throwable $th) {
            $this->bk_db->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'An error occurred fetching delegated quotes.',
                'data' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
            ]);
        }
    }

    public function activateQuote(Request $request){
        $request->validate([
            'quote_id' => 'required|integer|exists:bk_db.quotebook,Id',
            'user_email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email'
        ]);
        $quoteId = $request->quote_id;
        $userEmail = $request->user_email;

        try {

            $stdfns = new StandardFunctions();
            $user = $stdfns->get_user_id(     $userEmail);
            // Set new exit date to 5 days from now
            $newExitDate = now()->addWeekdays(3);

            //check if actually exit date has arrived before activating
            $currentQuote = $this->bk_db->table('quotebook')
                ->where('Id', $quoteId)
                // ->where('created_by', $user->Id)
                ->where('AssignedBy', $user->Id)
                ->first();

            if (!$currentQuote) {
                // throw new \Exception('Quote not found or unauthorized');
                return response()->json([
                    'success' => false,
                    'message' => 'Quote not found or unauthorized'
                ], 404);

            }

            if ($currentQuote->ExitDate && now()->lt($currentQuote->ExitDate)) {
                // throw new \Exception('Cannot activate quote before exit date has arrived');
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot activate quote before exit date'
                ], 404);
            }

            $this->bk_db->beginTransaction();
            $this->bk_db->table('quotebook')
                ->where('Id', $quoteId)
                ->where('created_by', $userEmail)
                ->update([
                    'IsActive' => true,
                    'ExitDate' => $newExitDate
                ]);

            $this->bk_db->commit();

            return response()->json([
                'success' => true,
                'message' => 'Quote activated successfully.'
            ]);
        } catch (\Exception $e) {
            $this->bk_db->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to activate quote: ' . $e->getMessage()

            ], 500);
        }
    }

    public function suspendQuote(Request $request){
        $request->validate([
            'quote_id' => 'required|integer|exists:bk_db.quotebook,Id',
            'user_email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email'
        ]);
        $quoteId = $request->quote_id;
        $userEmail = $request->user_email;

        try {
            $stdfns = new StandardFunctions();
            $user = $stdfns->get_user_id($userEmail);
            //check if actually exit date is valid before suspending
            $currentQuote = $this->bk_db->table('quotebook')
            ->where('Id', $quoteId)
            // ->where('created_by', $user->Id)
            ->where('AssignedBy', $user->Id)
            ->first();

            if (!$currentQuote) {
                // throw new \Exception('Quote not found or unauthorized');
                return response()->json([
                    'success' => false,
                    'message' => 'Quote not found or unauthorized'
                ], 404);

            }

            if ($currentQuote->ExitDate && now()->isAfter($currentQuote->ExitDate)) {

                return response()->json([
                    'success' => false,
                    'message' => 'Cannot suspend quote after exit date'
                ], 404);
            }

            $this->bk_db->beginTransaction();
            $this->bk_db->table('quotebook')
                ->where('Id', $quoteId)
                ->where('created_by', $user->Id)
                ->update([
                    'IsActive' => false,
                    'ExitDate' => now()
                ]);
           $this->bk_db->commit();

            return response()->json([
                'success' => true,
                'message' => 'Quote suspended successfully.'
            ]);

        } catch (\Exception $e) {
            $this->bk_db->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to suspend quote: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateQuote(Request $request){
        $request->validate([
            'quote_id' => 'required|integer|exists:bk_db.quotebook,Id',
            'bid_price' => 'required|numeric',
            'offer_price' => 'required|numeric',
            'face_value' => 'required|numeric',
            'settlement_date' => 'required|date',
            'indicative_range' => 'required|string',
            'assigned_by' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
            'is_active' => 'required|boolean',
            'is_accepted' => 'required|boolean',
            'total_receivable' => 'required|numeric',
            'total_payable' => 'required|numeric',
            'other_levies' => 'required|numeric',
            'commission_nse' => 'required|numeric',
            'consideration' => 'required|numeric'
        ]);

        $quote_id = $request->quote_id;
        $bid_price = $request->bid_price;
        $offer_price = $request->offer_price;
        $face_value = $request->face_value;
        $settlement_date = $request->settlement_date;
        $indicative_range = $request->indicative_range;
        $assigned_by = $request->assigned_by;
        $is_active = $request->is_active;
        $is_accepted = $request->is_accepted;
        $total_receivable = $request->total_receivable;
        $total_payable = $request->total_payable;
        $other_levies = $request->other_levies;
        $commission_nse = $request->commission_nse;
        $consideration = $request->consideration;

        try{
            // Start transaction
            $this->bk_db->beginTransaction();

            $stdfns = new StandardFunctions();
            $user = $stdfns->get_user_id($request->assigned_by);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                    'data' => null
                ]);
            }

            // Check if the bond exists
            $quote = $this->bk_db->table('quotebook')->where('Id', $quote_id)->first();
            if (!$quote) {
                return response()->json([
                    'success' => false,
                    'message' => 'Quote not found.',
                    'data' => null
                ]);
            }
            // Update the quote with the new details
            $quote->update([
                'BidPrice' => $bid_price,
                'OfferPrice' => $offer_price,
                'FaceValue' => $face_value,
                'SettlementDate' => $settlement_date,
                'IndicativeRange' => $indicative_range,
                'AssignedBy' => $assigned_by,
                'IsActive' => $is_active,
                'IsAccepted' => $is_accepted,
                'TotalReceivable' => $total_receivable,
                'TotalPayable' => $total_payable,
                'OtherLevies' => $other_levies,
                'CommissionNSE' => $commission_nse,
                'Consideration' => $consideration
            ]);

            // Commit the transaction
            $this->bk_db->commit();

            return response()->json([
                'success' => true,
                'message' => 'Quote updated successfully.',
                'data' => $quote
            ]);
        } catch (\Throwable $th) {
            $this->bk_db->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'An error occurred updating quote.',
                'data' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
            ]);
        }
    }
    public function createTransaction(Request $request){
        $request->validate([
            'quote_id' => 'required|integer|exists:bk_db.quotebook,Id',
            'user_email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
            'bid_amount' => 'required|numeric',
            'bid_price'  => 'required|numeric',
            'bid_yield' => 'required|numeric',
            'offer_amount' => 'required|numeric',
            'offer_price' => 'required|numeric',
            'offer_yield' => 'required|numeric',
            // 'face_value' => 'required|numeric',
            // 'viewing_party' => 'required|email|exists:bk_db.portaluserlogoninfo,Id',
        ]);

        try {
            $this->bk_db->beginTransaction();

            $quote_id = $request->input('quote_id');
            $user_email = $request->input('user_email');
            $bid_amount = $request->input('bid_amount');
            $bid_price = $request->input('bid_price');
            $bid_yield = $request->input('bid_yield');
            $offer_amount = $request->input('offer_amount');
            $offer_price = $request->input('offer_price');
            $offer_yield = $request->input('offer_yield');
            // $viewing_party = $request->input('viewing_party');
            // $face_value =  $request->input('face_value');

            // Get the quote details first
            $quote = $this->bk_db->table('quotebook')
                ->where('Id', $quote_id)
                ->first();

            if (!$quote) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Quote not found'
                    ], 404);
            }

            $stdfns = new StandardFunctions();
            $user = $stdfns->get_user_id($user_email);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }
            //check if us can not create transaction if he "AssignedBy " is the same user
            if ($quote->AssignedBy == $user->Id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot create a transaction for your own quote'
                ], 404);
            }

            $transactionType = '';
            //check if it's IsBid or IsOffer and mark accordingy
            if ($quote->IsBid == 1) {
                $transactionType = '<span class="text-green-600"><i class="fas fa-arrow-up"></i> Offer</span>';
            } elseif ($quote->IsOffer == 1) {
                $transactionType = '<span class="text-blue-600"><i class="fas fa-arrow-down"></i> Bid</span>';
            }


            // Generate a unique transaction number
            $transactionNo = 'BL-TXN-' . strtoupper(substr(uniqid(), 0, 4)) . '-' . substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 4);


            // select IsBid from quotebook where Id is $quote_id
            $isBid = $this->bk_db->table('quotebook')
                ->where('Id', $quote_id)
                ->first();
            Log::info("Here now");

            if($isBid->IsBid == 1 && $offer_amount > $bid_amount){
                $newofferAmount = $bid_amount;
                $newBidAmount = $offer_amount;
            } elseif ($isBid->IsOffer == 1 && $bid_amount > $offer_amount) {
                $newBidAmount = $offer_amount;
                $newofferAmount = $bid_amount - $offer_amount;
            }

//if isbid is 1 , then , check if offer_amount is > than bid amount ,  then create a new quote with additional amount i.e $offer_amount - $bid_amount, also if isoffer is 1 , then create a new quote with additional amount i.e $bid_amount - $offer_amount
        if($isBid->IsBid == 1 && $offer_amount > $bid_amount){
            $additional_amount = $offer_amount - $bid_amount;
      //use the

            // Create a new quote for the additional amount
            $placement_no = $stdfns->generatePlacementNumber();

            $newQuoteId = $this->bk_db->table('quotebook')->insertGetId([
                'BondIssueNo' => $quote->BondIssueNo,
                'PlacementNo' => $placement_no,
                'IsBid' => false, // Since original was bid, new one should be offer
                'IsOffer' => true,
                'BidPrice' => $bid_price,
                'BidYield' => $bid_yield,
                'OfferPrice' => $offer_price,
                'OfferYield' => $offer_yield,
                'BidAmount' => false,
                'OfferAmount' => $additional_amount,
                'SettlementDate' => now()->addWeekdays(3),
                'AssignedBy' => $user->Id,
                'IsActive' => true,
                'ExitDate' => now()->addWeekdays(3),
                'created_on' => Carbon::now(),
                'created_by' => $user->Id,
                ], 'Id');
        } elseif ($isBid->IsOffer == 1 && $bid_amount > $offer_amount) {
            $additional_amount = $bid_amount - $offer_amount;

            // Create a new quote for the additional amount
            $placement_no = $stdfns->generatePlacementNumber();

            $newQuoteId = $this->bk_db->table('quotebook')->insertGetId([
                'BondIssueNo' => $quote->BondIssueNo,
                'PlacementNo' => $placement_no,
                'IsBid' => true, // Since original was offer, new one should be bid
                'IsOffer' => false,
                'BidPrice' => $bid_price,
                'BidYield' => $bid_yield,
                'OfferPrice' => $offer_price,
                'OfferYield' => $offer_yield,
                'BidAmount' => $additional_amount,
                'OfferAmount' => $newofferAmount,
                'SettlementDate' => now()->addWeekdays(3),
                'AssignedBy' => $user->Id,
                'IsActive' => true,
                'ExitDate' => now()->addWeekdays(3),
                'created_on' => Carbon::now(),
                'created_by' => $user->Id,
                ], 'Id');
        }


                    // Create the transaction
                    $transaction_id = $this->bk_db->table('quotetransactions')->insertGetId([
                        'QuoteId' => $quote_id,
                        'TransactionNo' => $transactionNo,
                        'BidAmount' => $newBidAmount,
                        'OfferAmount' => $newofferAmount,
                        'BidPrice' => $bid_price,
                        'OfferPrice' => $offer_price,
                        'BidYield' => $bid_yield,
                        'OfferYield' => $offer_yield,
                        'InitiatedBy' => $user->Id,
                        'created_by' => $user->Id,
                        'created_on' => now(),
                        'IsPending' => true,
                        'IsAccepted' => false,
                        'IsRejected' => false,
                        'IsDelegated' => false
                        // 'IsActive' => true
                        ], 'Id');

            // Get user details for notification
            // $stdfns = new StandardFunctions();
            // $user = $stdfns->get_user_id($user_email);

            // Send notification to viewing party if exists
            // if ($viewing_party) {
            //     $notifs = new NotificationController();
            //     $notification_status = $notifs->createNotification(
            //         $viewing_party,
            //         3, // Quote bid notification type
            //         $user->FirstName . ' ' . $user->OtherNames . ' has submitted Quote Bid on your behalf. TransactionNo- ' . $transactionNo,
            //         null,
            //         null
            //     );

            //     if ($notification_status['success'] == false) {
            //         $this->bk_db->rollBack();
            //         return response()->json($notification_status);
            //     }

            //     // Send email to viewing party
            //     $communication_manager = new CommunicationManagement();
            //     $emailResponse = $communication_manager->composeMail(
            //         $viewing_party,
            //         false,
            //         false,
            //         false,
            //         false,
            //         true,
            //         false,
            //         false,
            //         false,
            //         "Quote Bid Submitted",
            //         $user->FirstName . ' ' . $user->OtherNames . ' has submitted Quote Bid on your behalf. TransactionNo- ' . $transactionNo
            //     );

            //     if ($emailResponse['success'] == false) {
            //         $this->bk_db->rollBack();
            //         return response()->json([
            //             'success' => false,
            //             'message' => $emailResponse
            //         ], 500);
            //     }
            // }


            //get placement number from quote
            // $placementNumber = "<span class='text-blue-600'><i class='fas fa-file-invoice'></i> " . $quote->PlacementNo . "</span>";
            $placementNumber = $quote->PlacementNo;
            //send notification to $user

            $notifs = new NotificationController();
            $notification_status = $notifs->createNotification(
                $user->Id,
                9, // Quote bid notification type
                'Your Quote Transaction for PlacementNumber ' . $placementNumber . ' has been submitted. Login to check',
                null,
                null
            );

            if ($notification_status['success'] == false) {
                $this->bk_db->rollBack();
                return response()->json($notification_status);
            }

            //send email to $user
            $communication_manager = new CommunicationManagement();
            $emailResponse = $communication_manager->composeMail(
                $user->Email,
                $user->FirstName,
                false,
                false,
                false,
                true,
                false,
                false,
                false,
                "Quote Transaction Submitted",
                "Your Quote Transaction for PlacementNumber " . $placementNumber . " has been submitted. Login to check"
            );

            if ($emailResponse['success'] == false) {
                $this->bk_db->rollBack();
                return response()->json($emailResponse);
            }
            //log email
            $stdfns = new StandardFunctions();
            $logEmailResponse =  $stdfns->logEmail(
                $user->Id,
                "Quote Transaction Submitted",
                "Your Quote Transaction for PlacementNumber " . $placementNumber . " has been submitted. Login to check",
                false, // allRecipientsEmails
                false, // cc
                false, // bcc
                false, // scheduleDate
                false, // roleGroupSendingTo
                false, // isDraft
                1, // isFromSystem
                false // isBulkEmail
            );
            // Send notification to quote owner
            $notifs = new NotificationController();
            $notification_status = $notifs->createNotification(
                $quote->AssignedBy,
                9, // Quote bid notification type
                'You have received  ' . $transactionType . ' for your Quote, TransactionNo - ' . $transactionNo . '. Login to check',
                null,
                null
            );

            if ($notification_status['success'] == false) {
                $this->bk_db->rollBack();
                return response()->json($notification_status);
            }
            //select Email where user is $quote->AssignedBy
            $quoteOwner = $this->bk_db->table('portaluserlogoninfo')
                ->where('Id', $quote->AssignedBy)
                ->first();

            // Send email to quote owner
            $communication_manager = new CommunicationManagement();
            $emailResponse = $communication_manager->composeMail(
                $quoteOwner->Email,
                $quoteOwner->FirstName,
                false,
                false,
                false,
                true,
                false,
                false,
                false,
                "New " . $transactionType . " Received",
                'You have received a ' . $transactionType . ' for your Quote, TransactionNo - ' . $transactionNo . '. Login to check'
            );

            if ($emailResponse['success'] == false) {
                $this->bk_db->rollBack();
                return response()->json([
                    'success' => false,
                    'message' => $emailResponse
                ], 500);
            }
            //log email
            $stdfns = new StandardFunctions();
            $logEmailResponse =  $stdfns->logEmail(
                $quoteOwner->Id,
                "New " . $transactionType . " Received",
                'You have received a ' . $transactionType . ' for your Quote, TransactionNo - ' . $transactionNo . '. Login to check',
                false, // allRecipientsEmails
                false, // cc
                false, // bcc
                false, // scheduleDate
                false, // roleGroupSendingTo
                false, // isDraft
                1, // isFromSystem
                false // isBulkEmail
            );
            $this->bk_db->commit();

            return response()->json([
                'success' => true,
                'message' => 'Transaction created successfully',
                'data' => [
                    // 'transaction_id' => $transaction_id,
                    'transaction_no' => $transactionNo
                ]
            ]);

        } catch (\Throwable $th) {
            $this->bk_db->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create transaction: ' . $th->getMessage(),
                'data' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
            ], 500);
        }
    }

    public function markTransactionStatus(Request $request){
        $request->validate([
            'trans_id' => 'required|integer|exists:bk_db.quotetransactions,Id',
            'user_email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
            'is_delegated' => 'required|boolean',
            'is_accepted' => 'required|boolean',
            'is_rejected' => 'required|boolean',
            'is_pending' => 'required|boolean',

        ]);

        $trans_id = $request->trans_id;
        $user_email = $request->user_email;
        $is_delegated = $request->is_delegated;
        $is_accepted = $request->is_accepted;
        $is_rejected = $request->is_rejected;
        $is_pending = $request->is_pending;

        try {
            // Start transaction
            $this->bk_db->beginTransaction();

            $stdfns = new StandardFunctions();
            $user = $stdfns->get_user_id($user_email);
            if(!$user){
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                    'data' => null
                ]);
            }

            $quoteTrans = $this->bk_db->table('quotetransactions')
                ->where('Id', $trans_id)
                ->first();

            if (!$quoteTrans) {
                return response()->json([
                    'success' => false,
                    'message' => 'Quote Transaction not found.',
                    'data' => null
                ]);
            }

            //check if quoteTrans is either rejected/accepted depending on the status & is_accepted is true
            if(($quoteTrans->IsAccepted == 1 && $is_accepted == true) || ($quoteTrans->IsRejected == 1 && $is_rejected == true)){
                return response()->json([
                    'success' => false,
                    'message' => 'Quote Transaction already rejected/accepted.',
                    'data' => null
                ]);
            }
            //get original Quote details
            $originalQuote = $this->bk_db->table('quotebook')
            ->where('Id', $quoteTrans->QuoteId)
            ->first();

            if (!$originalQuote) {
                return response()->json([
                    'success' => false,
                    'message' => 'Original quote not found.',
                    'data' => null
                ]);
            }

            // Get the quote owner's details
            $quoteOwner = $this->bk_db->table('portaluserlogoninfo')
                ->where('Id', $originalQuote->AssignedBy)
                ->first();

            if (!$quoteOwner) {
                return response()->json([
                    'success' => false,
                    'message' => 'Quote owner not found.',
                    'data' => null
                ]);
            }

            //Only the original quote person can mark transaction status
            if($quoteOwner->Id !== $user->Id){
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to mark this transaction status.',
                    'data' => null
                ]);
            }

            $statusText = "";
            if($is_accepted){
                $statusText = "<span class='text-green-600'><i class='fas fa-check-circle'></i> Accepted</span>";
            } elseif($is_rejected){
                $statusText = "<span class='text-red-600'><i class='fas fa-times-circle'></i> Rejected</span>";
            } elseif($is_pending){
                $statusText = "<span class='text-yellow-600'><i class='fas fa-clock'></i> Pending</span>";
            } elseif($is_delegated){
                $statusText = "<span class='text-blue-600'><i class='fas fa-user-shield'></i> Delegated</span>";
            }
            // Update quote status
            $this->bk_db->table('quotetransactions')
                ->where('Id', $trans_id)
                ->update([
                    'IsAccepted' => $is_accepted,
                    'IsRejected' => $is_rejected,
                    'IsPending' => $is_pending,
                    'IsDelegated' => $is_delegated,
                    'dola' => Carbon::now(),
                    'altered_by' => $user->Id
                ]);

                //get PlacementNumber from Quotes where Id is $trans_id

              $placementNumber = $originalQuote->PlacementNo;

              $initiator = $this->bk_db->table('portaluserlogoninfo')
                ->where('Id', $quoteTrans->InitiatedBy)
                ->first();
            // Send email notification to initiator
            $communication_manager = new CommunicationManagement();
            $emailResponse = $communication_manager->composeMail(
                $initiator->Email,
                $initiator->FirstName,
                false,
                false,
                false,
                true,
                false,
                false,
                false,
                "Quote Status ". $statusText ,
                "Your Quote Transaction for PlacementNumber " . $placementNumber . " has been " . $statusText . " by " . $user->Email
            );

            if ($emailResponse['success'] == false) {
                $this->bk_db->rollBack();
                return response()->json($emailResponse, 500);
            }

            //log email
            $stdfns = new StandardFunctions();
            $logEmailResponse =  $stdfns->logEmail(
                $initiator->Id,
                "Quote Status " . $statusText,
                "Your Quote Transaction for PlacementNumber " . $placementNumber . " has been " . $statusText . " by " . $user->Email,
                false, // allRecipientsEmails
                false, // cc
                false, // bcc
                false, // scheduleDate
                false, // roleGroupSendingTo
                false, // isDraft
                1,    // isFromSystem
                false  // isBulkEmail
            );
            if (!$logEmailResponse || (is_array($logEmailResponse) && $logEmailResponse['success'] === false)) {
                $this->bk_db->rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Error logging email notification',
                    'data' => $logEmailResponse,

                ]);
            }

            // Create notification for initiator
            $notifs = new NotificationController();
            $notification_status = $notifs->createNotification(
                $initiator->Id,
                9, // Bid status update notification type
                "Your Quote Transaction for PlacementNumber"  . $placementNumber .  " has been " . $statusText . " by " . $user->Email,
                $trans_id,
                null
            );

            if ($notification_status['success'] == false) {
                $this->bk_db->rollBack();
                return response()->json($notification_status);
            }


              // Send email notification and Notification to $user
            //   $communication_manager = new CommunicationManagement();
              $emailResponse1 = $communication_manager->composeMail(
                $user->Email,
                $user->FirstName,
                false,
                false,
                false,
                true,
                false,
                false,
                false,
                "Quote Status Submitted",
                "You have " . $statusText . " a Quote Transaction for PlacementNumber " . $placementNumber . " with ". env('APP_NAME') . ". Your feedback has been sent to the other party."
              );
              if ($emailResponse1['success'] == false) {
                $this->bk_db->rollBack();
                return response()->json($emailResponse1);
            }
                  //log email

                  $logEmailResponse1 = $stdfns->logEmail(
                    $user->Id,
                    "Quote Status Submitted",
                    "You have " . $statusText . " a Quote Transaction for PlacementNumber " . $placementNumber . " with ". env('APP_NAME') . ". Your feedback has been sent to the other party.",
                    false, // allRecipientsEmails
                    false, // cc
                    false, // bcc
                    false, // scheduleDate
                    false, // roleGroupSendingTo
                    false, // isDraft
                    1,    // isFromSystem
                    false  // isBulkEmail
                  );

                  if (!$logEmailResponse || (is_array($logEmailResponse) && $logEmailResponse['success'] === false)) {
                    $this->bk_db->rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Error logging email notification',
                        'data' => $logEmailResponse
                    ]);
                }

            //send notification to $user

            // Commit transaction
            $this->bk_db->commit();

            return response()->json([
                'success' => true,
                'message' => 'Quote status updated successfully.',
                'data' => null
            ]);

        } catch (\Throwable $th) {
            $this->bk_db->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'An error occurred updating quote status.',
                'data' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
            ]);
        }
    }

//function getUserTransactions
public function getUserTransactions(Request $request){
    $request->validate([
        'user_email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
    ]);

    $user_email = $request->user_email;
    $user  = $this->bk_db->table('portaluserlogoninfo')
        ->where('Email', $user_email)
        ->first();

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'User not found.',
            'data' => null
        ]);
    }

    // Sent: InitiatedBy = user
    $sentTransactions = $this->bk_db->table('quotetransactions as qt')
        ->join('quotebook as qb', 'qt.QuoteId', '=', 'qb.Id')
        ->join('portaluserlogoninfo as p', 'qb.AssignedBy', '=', 'p.Id')
        ->leftJoin('bondkonnect_db.users as u', 'p.Id', '=', 'u.portal_id')
        ->where('qt.InitiatedBy', $user->Id)
        ->select('qt.*', 'qb.PlacementNo', 'p.Email as ratee_email', 'u.id as ratee_id', DB::raw("CONCAT(p.FirstName, ' ', IFNULL(p.OtherNames, '')) as ratee_name"))
        ->get()
        ->map(function($item) {
            $item->transaction_type = 'Sent';
            return $item;
        });

    // Received: QuoteId belongs to a quote where user is the owner (AssignedBy)
    $receivedTransactions = $this->bk_db->table('quotetransactions as qt')
        ->join('quotebook as qb', 'qt.QuoteId', '=', 'qb.Id')
        ->join('portaluserlogoninfo as p', 'qt.InitiatedBy', '=', 'p.Id')
        ->leftJoin('bondkonnect_db.users as u', 'p.Id', '=', 'u.portal_id')
        ->where('qb.AssignedBy', $user->Id)
        ->select('qt.*', 'qb.PlacementNo', 'p.Email as ratee_email', 'u.id as ratee_id', DB::raw("CONCAT(p.FirstName, ' ', IFNULL(p.OtherNames, '')) as ratee_name"))
        ->get()
        ->map(function($item) {
            $item->transaction_type = 'Received';
            return $item;
        });

    // Delegated: Use leaveservice to find if user is a delegate, then get transactions where ReceivedBy = delegate's Id
    $delegatedTransactions = collect();
    $isDelegate = $this->bk_db->table('leaveservice')
        ->where('Colleague', $user->Id)
        ->where('EndDate', '>=', Carbon::now())
        ->first();

    if ($isDelegate) {
        // Get transactions assigned to the delegating user
        $delegatedTransactions = $this->bk_db->table('quotetransactions as qt')
            ->join('quotebook as qb', 'qt.QuoteId', '=', 'qb.Id')
            ->join('portaluserlogoninfo as p', 'qt.InitiatedBy', '=', 'p.Id')
            ->leftJoin('bondkonnect_db.users as u', 'p.Id', '=', 'u.portal_id')
            ->where('qb.AssignedBy', $isDelegate->AssignedBy)
            ->select('qt.*', 'qb.PlacementNo', 'p.Email as ratee_email', 'u.id as ratee_id', DB::raw("CONCAT(p.FirstName, ' ', IFNULL(p.OtherNames, '')) as ratee_name"))
            ->get()
            ->map(function($item) {
                $item->transaction_type = 'Delegated';
                return $item;
            });
    }

    // Merge all and remove duplicates (if any)
    $allTransactions = collect()
        ->merge($sentTransactions)
        ->merge($receivedTransactions)
        ->merge($delegatedTransactions)
        ->unique('Id')
        ->values();

    return response()->json([
        'success' => true,
        'message' => 'User transactions fetched successfully.',
        'data' => $allTransactions,
        'sent' => $sentTransactions,
        'received' => $receivedTransactions,
        'delegated' => $delegatedTransactions
    ], 200);
}


    // //getSentTransactions
    // public function getSentTransactions(Request $request){
    //     $request->validate([
    //         'user_email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email'
    //     ]);

    //     $user_email = $request->user_email;

    //     try{
    //         $stdfns = new StandardFunctions();
    //         $user = $stdfns->get_user_id($request->user_email);
    //         $transactions = $this->bk_db->table('quotetransactions')->where('InitiatedBy', $user->Id)->where('IsPending', true)->get()->toArray();

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Sent Transactions fetched successfully.',
    //             'data' => $transactions
    //         ]);
    //     } catch (\Throwable $th) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'An error occurred fetching sent transactions.',
    //             'data' => $th->getMessage(),
    //             'file' => $th->getFile(),
    //             'line' => $th->getLine(),
    //         ]);
    //     }
    // }

    // public function getDelegatedTransactions(Request $request){
    //     $request->validate([
    //         'delegate_email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
    //     ]);

    //     $delegate_email = $request->delegate_email;

    //     try{
    //         // Start transaction
    //         $this->bk_db->beginTransaction();
    //         $stdfns = new StandardFunctions();

    //         $delegate = $stdfns->get_user_id($delegate_email);

    //         if (!$delegate) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'User not found.',
    //                 'data' => null
    //             ]);
    //         }

    //         //get to see if the user is a delegate or not from leaveservice
    //         $isDelegate = $this->bk_db->table('leaveservice')->where('Colleague', $delegate->Id)->where('EndDate', '<' ,Carbon::now())->first();
    //         if (!$isDelegate) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'User is not a delegate or delegation period has not ended.',
    //                 'data' => null
    //             ]);
    //         }

    //         // Fetch delegated transactions
    //         $delegatedTransactions = $this->bk_db->table('quotetransactions')->where('ReceivedBy', $isDelegate->Id)->get()->toArray();

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Delegated transactions fetched successfully.',
    //             'data' => $delegatedTransactions
    //         ]);

    //     } catch (\Throwable $th) {
    //         $this->bk_db->rollBack();
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'An error occurred fetching delegated transactions.',
    //             'data' => $th->getMessage(),
    //             'file' => $th->getFile(),
    //             'line' => $th->getLine(),
    //         ]);
    //     }
    // }


    //Graph Data
    //get details in bond calculator

    public function getBondCalculatorDetails()
    {
        try {
            $bondDetails = $this->bk_db->table('tableparams')
                ->select('ValueDate', 'DailyBasis', 'PercentOverTenYrs', 'PercentUnderTenYrs', 'IfbFiveYrs', 'NseCommission', 'NseMinCommission', 'CmaLevies')
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Bond calculator details fetched successfully.',
                'data' => $bondDetails
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch bond calculator details', 'message' => $e->getMessage()], 404);
        }
    }
    //get tableparams
    public function getTableParams()
    {
        try {
            $tableParams = $this->bk_db->table('tableparams')->first();
            return response()->json($tableParams, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch table params', 'message' => $e->getMessage()], 404);
        }
    }
    //get secondary market bonds
    public function getSecondaryMarketBonds()
    {
        try {
            $bonds = $this->bk_db->table('statstable')
                                 ->get();

            return response()->json($bonds, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch secondary market bonds', 'message' => $e->getMessage()], 404);
        }
    }
    // get primary market bonds
    public function getPrimaryMarketBonds()
    {
        try {
            $bonds = $this->bk_db->table('primarymarkettable')
                ->get();

            return response()->json($bonds, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch primary market bonds', 'message' => $e->getMessage()], 404);
        }
    }
    // bondmarketPerformance vs Economic Indicators
    public function getBondMarketPerformance()
    {
        try {
            // Fetch data from OBI Table where OBITR is not null or empty
            $obiData = $this->bk_db->table('obitable')
                ->select('Date', 'OBITR')
                ->whereNotNull('OBITR')
                ->where('OBITR', '!=', '')
                ->where('OBITR', '!=', 0) // Also exclude zero values if needed
                ->orderBy('Date', 'asc')
                ->get()
                ->toArray();

            // Get date range from OBI data
            $firstDate = null;
            $lastDate = null;
            if (!empty($obiData)) {
                $firstDate = $obiData[0]->Date;
                $lastDate = end($obiData)->Date;
            }

            // Fetch data from YTM Table using the same date range as OBI data
            $ytmData = [];
            if ($firstDate && $lastDate) {
                $ytmData = $this->bk_db->table('ytmtable')
                    ->select('Date', 'CBR', 'Inflation')
                    ->whereBetween('Date', [$firstDate, $lastDate])
                    ->orderBy('Date', 'asc')
                    ->get()
                    ->toArray();
            }

            // Prepare data for the response
            $response = [
                'oneYr' => array_map(function($row) {
                    return [
                        'x' => $row->Date,
                        'y' => $row->OBITR
                    ];
                }, $obiData),
                'cbrRate' => array_map(function($row) {
                    return [
                        'x' => $row->Date,
                        'y' => $row->CBR
                    ];
                }, $ytmData),
                'inflation' => array_map(function($row) {
                    return [
                        'x' => $row->Date,
                        'y' => $row->Inflation
                    ];
                }, $ytmData)
            ];

            return response()->json([
                'success' => true,
                'message' => 'Bond market performance data fetched successfully.',
                'data' => $response
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch bond market performance data', 'message' => $e->getMessage()], 404);
        }
    }
    //Spot Yield Curve (Kenya)
    public function getSpotYieldCurve()
    {
        try {

            //fetch valuedate from tableparams
            $valuedate = $this->bk_db->table('TableParams')
                ->select('ValueDate')
                ->first();

            // Fetch data from Graph Table for the specified date
            $spotYieldData = $this->bk_db->table('graphtable')
                ->select('Year', 'SpotRate')
                ->where('Date', $valuedate->ValueDate)
                ->get()
                ->toArray();

            // Prepare data for the response
            $response = array_map(function($row) {
                return [
                    'x' => $row->Year,
                    'y' => $row->SpotRate * 100 // Express SpotRate as a percentage
                ];
            }, $spotYieldData);

            return response()->json([
                'success' => true,
                'message' => 'Spot yield curve data fetched successfully.',
                'data' => $response
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch spot yield curve data', 'message' => $e->getMessage()], 404);
        }
    }

    public function projectionBands()
    {
        try {
            // Fetch data from Graph Table for the specified date
            $projectionData = $this->bk_db->table('graphtable')
                ->select('Year', 'UpperBand', 'LowerBand')
                ->where('Date', '2021-04-01')
                ->get()
                ->toArray();

            // Prepare data for the response
            $response = [
                'UpperBand' => array_map(function($row) {
                    return [
                        'x' => $row->Year,
                        'y' => $row->UpperBand * 100 // Express UpperBand as a percentage
                    ];
                }, $projectionData),
                'LowerBand' => array_map(function($row) {
                    return [
                        'x' => $row->Year,
                        'y' => $row->LowerBand * 100 // Express LowerBand as a percentage
                    ];
                }, $projectionData)
            ];

            return response()->json([
                'success' => true,
                'message' => 'Projection bands data fetched successfully.',
                'data' => $response
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch projection bands data', 'message' => $e->getMessage()], 404);
        }
    }

    // Historical Bands
    public function historicalBands()
    {
        try {
            // Fetch data for 1 week ago
            $oneWeekAgoData = $this->bk_db->table('graphtable')
                ->select('Year', 'SpotRate')
                ->where('Date', '2021-03-25')
                ->get()
                ->toArray();

            // Fetch data for 1 month ago
            $oneMonthAgoData = $this->bk_db->table('graphtable')
                ->select('Year', 'SpotRate')
                ->where('Date', '2020-12-31')
                ->get()
                ->toArray();

            // Fetch data for 1 year ago
            $oneYearAgoData = $this->bk_db->table('graphtable')
                ->select('Year', 'SpotRate')
                ->where('Date', '2020-10-01')
                ->get()
                ->toArray();

            // Prepare data for the response
            $response = [
                'oneWeekAgo' => array_map(function($row) {
                    return [
                        'x' => $row->Year,
                        'y' => $row->SpotRate * 100 // Express SpotRate as a percentage
                    ];
                }, $oneWeekAgoData),
                'oneMonthAgo' => array_map(function($row) {
                    return [
                        'x' => $row->Year,
                        'y' => $row->SpotRate * 100 // Express SpotRate as a percentage
                    ];
                }, $oneMonthAgoData),
                'oneYearAgo' => array_map(function($row) {
                    return [
                        'x' => $row->Year,
                        'y' => $row->SpotRate * 100 // Express SpotRate as a percentage
                    ];
                }, $oneYearAgoData)
            ];

            return response()->json([
                'success' => true,
                'message' => 'Historical bands data fetched successfully.',
                'data' => $response
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch historical bands data', 'message' => $e->getMessage()], 404);
        }
    }


}
