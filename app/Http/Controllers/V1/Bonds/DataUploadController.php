<?php

namespace App\Http\Controllers\V1\Bonds;

use App\Http\Controllers\Controller;
use App\Http\Controllers\V1\Logs\ActivityLogging;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DataUploadController extends Controller
{
    private $tableStructure = [
        'graphtable' => [
            'Date' => 'nullable|date',
            'Year' => 'nullable|string|max:100',
            'SpotRate' => 'nullable|string|max:100',
            'NseRate' => 'nullable|string|max:100',
            'UpperBand' => 'nullable|string|max:100',
            'LowerBand' => 'nullable|string|max:100',
        ],
        'statstable' => [
            'Otr' => 'nullable|string|max:100',
            'Filter1' => 'nullable|string|max:100',
            'Filter2' => 'nullable|string|max:100',
            'Id_' => 'nullable|string|max:100',
            'BondIssueNo' => 'nullable|string|max:400', // This line specifies the validation rules for the 'BondIssueNo' field. It allows the field to be nullable (i.e., it can be empty), it must be a string, and its maximum length is 200 characters.
            'IssueDate' => 'nullable|date',
            'MaturityDate' => 'nullable|date',
            'ValueDate' => 'nullable|date',
            'QuotedYield' => 'nullable|string|max:100',
            'SpotYield' => 'nullable|string|max:100',
            'DirtyPrice' => 'nullable|string|max:100',
            'Coupon' => 'nullable|string|max:100',
            'NextCpnDays' => 'nullable|string|max:100',
            'DtmYrs' => 'nullable|string|max:100',
            'Dtc' => 'nullable|string|max:100',
            'Duration' => 'nullable|string|max:100',
            'MDuration' => 'nullable|string|max:100',
            'Convexity' => 'nullable|string|max:100',
            'ExpectedReturn' => 'nullable|string|max:100',
            'ExpectedShortfall' => 'nullable|string|max:100',
            'Dv01' => 'nullable|string|max:100',
            'Last91Days' => 'nullable|string|max:100',
            'Last364Days' => 'nullable|string|max:100',
            'LqdRank' => 'nullable|string|max:100',
            'Spread' => 'nullable|string|max:100',
            'CreditRiskPremium' => 'nullable|string|max:100',
            'MdRank' => 'nullable|string|max:100',
            'ErRank' => 'nullable|string|max:100',
            'Basis' => 'nullable|string|max:100',
        ],
        'obitable' => [
            'Date' => 'nullable|date',
            'QuotedYield' => 'nullable|string|max:100',
            'SpotYield' => 'nullable|string|max:100',
            'DirtyPrice' => 'nullable|string|max:100',
            'ObiKIndex' => 'nullable|string|max:100',
            'Coupon' => 'nullable|string|max:100',
            'Duration' => 'nullable|string|max:100',
            'ExpectedReturn' => 'nullable|string|max:100',
            'Dv01' => 'nullable|string|max:100',
            'ExpectedShortfall' => 'nullable|string|max:100',
            'ObiTr' => 'nullable|string|max:100',
        ],
        'ytmtable' => [
            'TaylorRule' => 'nullable|string|max:100',
            'Ceiling' => 'nullable|string|max:100',
            'Floor' => 'nullable|string|max:100',
            'Lamda1' => 'nullable|string|max:100',
            'Lamda2' => 'nullable|string|max:100',
            'Alpha' => 'nullable|string|max:100',
            'Beta1' => 'nullable|string|max:100',
            'Beta2' => 'nullable|string|max:100',
            'Beta3' => 'nullable|string|max:100',
            'Cbr' => 'nullable|string|max:100',
            'RateProjection' => 'nullable|string|max:100',
            'Inflation' => 'nullable|string|max:100',
            'Level' => 'nullable|string|max:100',
            'Slope' => 'nullable|string|max:100',
            'Carvature' => 'nullable|string|max:100',
            'Date' => 'nullable|date',
        ],
        'primarymarkettable' => [
            'BondIssueNo' => 'nullable|string|max:400',
            'IssueDate' => 'nullable|date',
            'MaturityDate' => 'nullable|date',
            'ValueDate' => 'nullable|date',
            'FirstCallDate' => 'nullable|date',
            'SecondCallDate' => 'nullable|date',
            'ParCall1Percent' => 'nullable|string|max:100',
            'ParCall2Percent' => 'nullable|string|max:100',
            'PricingMethod' => 'nullable|string|max:100',
            'DtmOrWal' => 'nullable|string|max:100',
            'DayCount' => 'nullable|integer',
            'FirstCouponDate' => 'nullable|date',
            'SecondCouponDate' => 'nullable|date',
            'SpotRate' => 'nullable|string|max:100',
            'ParYield' => 'nullable|string|max:100',
        ],
    ];

    public function upload(Request $request)
    {
        $logger = new ActivityLogging;
        // Validate basic request structure
        $baseValidator = Validator::make($request->all(), [
            'table' => 'required|string|in:'.implode(',', array_keys($this->tableStructure)),
            'data' => 'required|array',
        ]);

        if ($baseValidator->fails()) {
            $logger->logUserActivity(
                0,
                ActivityLogging::ACTIVITIES['UPLOAD']['code'],
                'upload_failed',
                ['narration' => 'Data upload error '.$baseValidator->errors()],
                ActivityLogging::SEVERITIES['ERROR']['level']
            );

            return response()->json([
                'success' => false,
                'errors' => $baseValidator->errors(),
            ], 400);
        }

        $tableName = $request->input('table');
        $data = $request->input('data');

        // Validate each record against table structure
        foreach ($data as $index => $record) {
            $validator = Validator::make($record, $this->tableStructure[$tableName]);

            if ($validator->fails()) {

                $logger->logUserActivity(
                    0,
                    ActivityLogging::ACTIVITIES['UPLOAD']['code'],
                    'upload_failed',
                    ['Table' => $tableName, 'data_columns' => array_keys($data[0] ?? []),  'narration' => 'Data upload error Validation Errors:-'.$validator->errors()],
                    ActivityLogging::SEVERITIES['ERROR']['level']
                );

                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                    'row_index' => $index,
                ], 400);
            }

            // Format datetime fields if they exist
            $data[$index] = $this->formatDateFields($tableName, $record);
        }

        try {
            $logger = new ActivityLogging;
            // Begin transaction
            $this->bk_db->beginTransaction();

            // Get the table columns
            $tableColumns = $this->bk_db->getSchemaBuilder()->getColumnListing($tableName);
            Log::info('Table columns:', ['columns' => $tableColumns]);

            // Insert records in chunks to handle large datasets efficiently
            foreach (array_chunk($data, 1000) as $chunkIndex => $chunk) {
                // Log the first row of each chunk for debugging
                Log::info('Processing chunk '.$chunkIndex, [
                    'first_row_keys' => array_keys($chunk[0]),
                    'first_row_values' => array_values($chunk[0]),
                ]);

                // Ensure each record has all required columns
                $sanitizedChunk = array_map(function ($record) use ($tableColumns) {
                    // Only keep columns that exist in the table
                    $sanitizedRecord = array_intersect_key($record, array_flip($tableColumns));

                    // Ensure all table columns are present (with null if missing)
                    foreach ($tableColumns as $column) {
                        if (! array_key_exists($column, $sanitizedRecord)) {
                            $sanitizedRecord[$column] = null;
                        }
                    }

                    return $sanitizedRecord;
                }, $chunk);

                $this->bk_db->table($tableName)->insert($sanitizedChunk);
            }

            // Commit transaction
            $this->bk_db->commit();
            $logger->logUserActivity(
                0,
                ActivityLogging::ACTIVITIES['UPLOAD']['code'],
                'upload_success',
                ['Table' => $tableName, 'Records count' => count($data),  'narration' => 'Data upload success'],
                ActivityLogging::SEVERITIES['INFO']['level']
            );

            return response()->json([
                'success' => true,
                'message' => count($data).' records uploaded successfully to '.$tableName,
                'record_count' => count($data),
            ], 200);

        } catch (\Exception $e) {
            // Rollback transaction on error
            $this->bk_db->rollBack();

            Log::error('Data upload error', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'table' => $tableName,
                'sample_data' => array_slice($data, 0, 5), // Log first 5 records for debugging
            ]);
            $logger->logUserActivity(
                0,
                ActivityLogging::ACTIVITIES['UPLOAD']['code'],
                'upload_failed',
                ['Table' => $tableName, 'table_columns' => $tableColumns ?? [], 'data_columns' => array_keys($data[0] ?? []), 'line' => $e->getLine(), 'file' => $e->getFile(), 'narration' => 'Data upload error. Column count mismatch:-'.$e->getMessage()],
                ActivityLogging::SEVERITIES['ERROR']['level']
            );

            return response()->json([
                'success' => false,
                'message' => 'Error uploading data: Column count mismatch',
                'error' => $e->getMessage(),
                'table_columns' => $tableColumns ?? [],
                'data_columns' => array_keys($data[0] ?? []),
            ], 500);
        }
    }

    private function formatDateFields($tableName, $record)
    {
        $dateFields = [
            'graphtable' => ['Date'],
            'statstable' => ['IssueDate', 'MaturityDate', 'ValueDate'],
            'obitable' => ['Date'],
            'ytmtable' => ['Date'],
            'primarymarkettable' => ['IssueDate', 'MaturityDate', 'FirstCallDate', 'SecondCallDate', 'FirstCouponDate', 'SecondCouponDate'],
        ];

        foreach ($dateFields[$tableName] as $field) {
            if (isset($record[$field]) && $record[$field]) {
                $record[$field] = date('Y-m-d H:i:s', strtotime($record[$field]));
            }
        }

        return $record;
    }
}
