<?php

namespace App\Http\Controllers\V1\Logs;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\V1\Defaults\StandardFunctions;
use App\Http\Controllers\V1\Notifications\NotificationController;

class ActivityLogging extends Controller
{
    // Activity types with descriptions for documentation
    const ACTIVITIES = [
        'AUTH' => [
            'code' => 'authentication',
            'description' => 'User authentication activities'
        ],
        'REPORTS' => [
            'code' => 'reporting',
            'description' => 'Report related activites'
        ],
        'DATA' => [
            'code' => 'data_access',
            'description' => 'Data access and retrieval'
        ],

        'MODIFY' => [
            'code' => 'modification',
            'description' => 'Data modification activities'
        ],
        'EXPORT' => [
            'code' => 'export',
            'description' => 'Data export operations'
        ],
        'UPLOAD' => [
            'code' => 'upload',
            'description' => 'File upload activities'
        ],
        'APPROVE' => [
            'code' => 'approval',
            'description' => 'Approval workflow activities'
        ]
    ];

    // Severity levels with descriptions
    const SEVERITIES = [
        'INFO' => [
            'level' => 'info',
            'description' => 'Normal operation information'
        ],
        'WARNING' => [
            'level' => 'warning',
            'description' => 'Warning conditions'
        ],
        'ERROR' => [
            'level' => 'error',
            'description' => 'Error conditions'
        ],
        'CRITICAL' => [
            'level' => 'critical',
            'description' => 'Critical conditions'
        ]
    ];

    // Rate limiting configuration
    private const RATE_LIMIT = 1000; // max logs per minute
    private const CACHE_TTL = 60; // 1 minute

    private function validateActivityType($type): bool
    {
        return in_array($type, array_column(self::ACTIVITIES, 'code'));
    }

    private function validateSeverity($severity): bool
    {
        return in_array($severity, array_column(self::SEVERITIES, 'level'));
    }

    private function sanitizeInput($input)
    {
        if (is_array($input)) {
            return array_map([$this, 'sanitizeInput'], $input);
        }
        return is_string($input) ? htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8') : $input;
    }

    private function checkRateLimit($user_id): bool
    {
        $key = "log_rate_{$user_id}";
        $count = Cache::get($key, 0);

        if ($count >= self::RATE_LIMIT) {
            Log::warning("Rate limit exceeded for user {$user_id}");
            return false;
        }

        Cache::add($key, 1, self::CACHE_TTL);
        Cache::increment($key);
        return true;
    }

    private function notifyAdministrators($log_id)
    {
        try {
            // Get log details
            $log = $this->bk_db->table('activitylogs')
                ->where('Id', $log_id)
                ->first();

            if (!$log) {
                return false;
            }

            // Get all admin users
            $admins = $this->bk_db->table('portaluserlogoninfo')
                ->where('Role', 1) // Admin role
                ->where('IsActive', true)
                ->get();

            $notifications = new NotificationController();

            foreach ($admins as $admin) {
                // Create notification message
                $message = "Critical Activity Alert: {$log->Action} by User ID {$log->UserId}";

                // Create notification link - points to activity logs view
                $link = "/admin/activity-logs?log_id=" . $log_id;

                // Send notification to each admin
                $notifications->sendNotification(
                    $admin->Id, // recipient
                    1, // notification type for critical alerts
                    $message,
                    $link,
                    new Request(['email' => $admin->Email])
                );
            }

            return true;

        } catch (\Throwable $th) {
            Log::error('Error notifying administrators: ' . $th->getMessage());
            return false;
        }
    }

    public function logUserActivity($user_id, $activity_type, $action, $details = null, $severity = 'info')
    {
        try {
            // Input validation
            $validator = Validator::make([
                'user_id' => $user_id,
                'activity_type' => $activity_type,
                'action' => $action,
                'severity' => $severity
            ], [
                'user_id' => 'required|integer',
                'activity_type' => 'required|string',
                'action' => 'required|string|max:255',
                'severity' => 'required|string'
            ]);

            if ($validator->fails()) {
                return [
                    'success' => false,
                    'message' => 'Invalid input parameters',
                    'data' => $validator->errors()
                ];
            }

            // Validate activity type and severity
            if (!$this->validateActivityType($activity_type) || !$this->validateSeverity($severity)) {
                return [
                    'success' => false,
                    'message' => 'Invalid activity type or severity level',
                    'data' => null
                ];
            }
            if ($user_id !== 0) {
                // $user_id = null;

            // Check rate limiting
            if (!$this->checkRateLimit($user_id)) {
                return [
                    'success' => false,
                    'message' => 'Rate limit exceeded',
                    'data' => null
                ];
            }



            // Get user details with caching
            $user = Cache::remember("user_{$user_id}", 300, function () use ($user_id) {
                return $this->bk_db->table('portaluserlogoninfo')
                    ->where('Id', $user_id)
                    ->first();
            });

            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found',
                    'data' => null
                ];
            }

            $role = $this->bk_db->table('userroles')
                            ->where('User', $user_id)
                            ->first();

            $role_id = $role->Role;
            Log::info('Role selected');
        }
                    // Sanitize inputs
                    $action = $this->sanitizeInput($action);
                    $details = $this->sanitizeInput($details);

            // Begin transaction
            $this->bk_db->beginTransaction();

            try {
                if($severity === 'info'){
                    $statuscode = 200;
                }else{
                    $statuscode = 500;
                }
                // Insert log entry
                $log_id = $this->bk_db->table('activitylogs')->insertGetId([
                    'User' => $user_id === 0 ? null : $user_id,
                    'Role' => $role_id ?? null,
                    // 'OrganisationNumber' => $user->OrganisationNumber ?? null,
                    // 'CostCenter' => $user->CostCenter ?? null,
                    // 'SchemeName' => $user->SchemeName ?? null,
                    'ActivityType' => $activity_type,
                    'Action' => $action,
                    'Description' => is_array($details) ? json_encode($details) : $details,
                    // 'Details' => is_array($details) ? json_encode($details) : $details,
                    'SeverityLevel' => $severity,
                    'UserAgent' => request()->userAgent(),
                    'StatusCode' => $statuscode,
                    'IPAddress' => request()->ip(),
                    'created_on' => Carbon::now(),
                    'RequestMethod' => request()->method(),
                    'RequestUrl' => request()->fullUrl(),
                    'RequestHeaders' => json_encode(request()->headers->all())
                ]);

                // Log critical events
                if ($severity === self::SEVERITIES['CRITICAL']['level']) {
                    Log::critical("Critical user activity: {$action}", [
                        'log_id' => $log_id,
                        'user_id' => $user_id,
                        'email' => $user->Email,
                        'details' => $details
                    ]);

                    // Notify administrators
                    $this->notifyAdministrators($log_id);
                }

                $this->bk_db->commit();

                return [
                    'success' => true,
                    'message' => 'Activity logged successfully',
                    'data' => $log_id
                ];

            } catch (\Exception $e) {
                $this->bk_db->rollBack();
                throw $e;
            }

        } catch (\Throwable $th) {
            Log::error('Error logging user activity: ' . $th->getMessage());
            return [
                'success' => false,
                'message' => 'Error logging activity',
                'data' => $th->getMessage()
            ];
        }
    }

    public function logReportsActivity(Request $request)
    {


        try {
            $request->validate([
                'user_email' => 'required|string',
                'action' => 'required|string|max:255',
                'details' => 'required|string',
                'severity' => 'required|string'

            ]);


            $severity =  $request->severity;
            $action = $request->action;
            $details = [
                'email' =>  $request->user_email,
                'narration' => $request->details,
            ];
            Log::info('Email, '.$request->user_email);
            Log::info('Severity, '.$request->details);

            $standardfns = new StandardFunctions();
            $user = $standardfns->get_user_id($request->user_email);
            $user_id = $user->Id;
            // Log::info('User id' . $user_id);
            if (!$user_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User does not exist!',
                    'data' => null,
                ], 400);
            }
            // Log::info('User id' . $user_id);

            $role_id = $this->bk_db->table('userroles')
                            ->where('User', $user_id)
                            ->value('Role');

            Log::info('Role selected: ' . $role_id);
            // Log::info('Role selected');

            // Check rate limiting
            if (!$this->checkRateLimit($user_id)) {
                return [
                    'success' => false,
                    'message' => 'Rate limit exceeded',
                    'data' => null
                ];
            }

            // Sanitize inputs
            $action = $this->sanitizeInput($action);
            $details = $this->sanitizeInput($details);

            // Get user details with caching
            $user = Cache::remember("user_{$user_id}", 300, function () use ($user_id) {
                return $this->bk_db->table('portaluserlogoninfo')
                    ->where('Id', $user_id)
                    ->first();
            });

            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found',
                    'data' => null
                ];
            }

            // Begin transaction
            $this->bk_db->beginTransaction();

            try {
                // Insert log entry
                $log_id = $this->bk_db->table('activitylogs')->insertGetId([
                    'UserId' => $user_id === 0 ? null : $user_id,
                    'Role' => $role_id ?? null,
                    // 'OrganisationNumber' => $user->OrganisationNumber ?? null,
                    // 'CostCenter' => $user->CostCenter ?? null,
                    // 'SchemeName' => $user->SchemeName ?? null,
                    'ActivityType' => ActivityLogging::ACTIVITIES['REPORTS']['code'],
                    'Action' => $action,
                    'Description' => is_array($details) ? json_encode($details) : $details,
                    // 'Details' => is_array($details) ? json_encode($details) : $details,
                    'Severity' => ActivityLogging::SEVERITIES[$severity]['level'],
                    'UserAgent' => request()->userAgent(),
                    'IPAddress' => request()->ip(),
                    'StatusCode' => request()->status(),
                    'created_on' => Carbon::now(),
                    'RequestMethod' => request()->method(),
                    'RequestUrl' => request()->fullUrl(),
                    'RequestHeaders' => json_encode(request()->headers->all())
                ]);

                // Log critical events
                if ($severity === self::SEVERITIES['CRITICAL']['level']) {
                    Log::critical("Critical user activity: {$action}", [
                        'log_id' => $log_id,
                        'user_id' => $user_id,
                        'email' => $user->Email,
                        'details' => $details
                    ]);

                    // Notify administrators
                    $this->notifyAdministrators($log_id);
                }

                $this->bk_db->commit();

                return [
                    'success' => true,
                    'message' => 'Activity logged successfully',
                    'data' => $log_id
                ];

            } catch (\Exception $e) {
                $this->bk_db->rollBack();
                throw $e;
            }

        } catch (\Throwable $th) {
            Log::error('Error logging Report activity: ' . $th->getMessage());
            return [
                'success' => false,
                'message' => 'Error logging report activity',
                'data' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
            ];
        }
    }


    public function getActivityLogs($filters = [])
    {
        try {
            $query = $this->bk_db->table('activitylogs');

            // Apply filters with updated field names
            if (isset($filters['user_id'])) {
                $query->where('UserId', $filters['user_id']);
            }
            if (isset($filters['activity_type'])) {
                $query->where('ActivityType', $filters['activity_type']);
            }
            if (isset($filters['severity'])) {
                $query->where('Severity', $filters['severity']);
            }
            if (isset($filters['date_from'])) {
                $query->where('created_on', '>=', $filters['date_from']);
            }
            if (isset($filters['date_to'])) {
                $query->where('created_on', '<=', $filters['date_to']);
            }

            // Get results with pagination
            $results = $query->orderBy('created_on', 'desc')
                ->paginate(isset($filters['per_page']) ? $filters['per_page'] : 50);

            return [
                'success' => true,
                'message' => 'Activity logs retrieved successfully',
                'data' => $results
            ];

        } catch (\Throwable $th) {
            Log::error('Error retrieving activity logs: ' . $th->getMessage());
            return [
                'success' => false,
                'message' => 'Error retrieving activity logs',
                'data' => $th->getMessage()
            ];
        }
    }

    public function getUserStats($user_id)
    {
        try {
            $stats = $this->bk_db->table('activitylogs')
                ->where('UserId', $user_id)
                ->select(
                    'ActivityType',
                    $this->bk_db->raw('COUNT(*) as count'),
                    $this->bk_db->raw('MAX(created_on) as last_activity')
                )
                ->groupBy('ActivityType')
                ->get();

            return [
                'success' => true,
                'message' => 'User statistics retrieved successfully',
                'data' => $stats
            ];

        } catch (\Throwable $th) {
            Log::error('Error retrieving user statistics: ' . $th->getMessage());
            return [
                'success' => false,
                'message' => 'Error retrieving user statistics',
                'data' => $th->getMessage()
            ];
        }
    }

    public function cleanOldLogs($days = 90)
    {
        try {
            $cutoff_date = now()->subDays($days);

            // Archive logs with updated field references
            $old_logs = $this->bk_db->table('activitylogs')
                ->where('created_on', '<', $cutoff_date)
                ->get();

            // Insert into archive table maintaining field names
            foreach ($old_logs->chunk(1000) as $chunk) {
                $this->bk_db->table('useractivitylogsarchive')
                    ->insert($chunk->toArray());
            }

            // Delete old logs
            $deleted = $this->bk_db->table('activitylogs')
                ->where('created_on', '<', $cutoff_date)
                ->delete();

            return [
                'success' => true,
                'message' => 'Old logs archived and cleaned successfully',
                'data' => [
                    'records_archived' => $old_logs->count(),
                    'records_deleted' => $deleted
                ]
            ];

        } catch (\Throwable $th) {
            Log::error('Error cleaning old logs: ' . $th->getMessage());
            return [
                'success' => false,
                'message' => 'Error cleaning old logs',
                'data' => $th->getMessage()
            ];
        }
    }

    public function exportActivityLogs($filters = [], $format = 'csv')
    {
        try {
            $logs = $this->getActivityLogs($filters)['data'];

            switch ($format) {
                case 'csv':
                    return $this->exportToCSV($logs);
                case 'json':
                    return $this->exportToJSON($logs);
                default:
                    throw new \InvalidArgumentException('Unsupported export format');
            }
        } catch (\Throwable $th) {
            Log::error('Error exporting logs: ' . $th->getMessage());
            return [
                'success' => false,
                'message' => 'Error exporting logs',
                'data' => $th->getMessage()
            ];
        }
    }

    private function exportToCSV($logs)
    {
        try {
            $filename = 'activity_logs_' . date('Y-m-d_His') . '.csv';
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"$filename\"",
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => false
            ];

            // Create temp file handle
            $handle = fopen('php://temp', 'r+');

            // Add UTF-8 BOM for Excel compatibility
            fputs($handle, "\xEF\xBB\xBF");

            // Write headers
            fputcsv($handle, [
                'ID',
                'User ID',
                'Activity Type',
                'Action',
                'Details',
                'Severity',
                'Created On',
                'Request Method',
                'Request URL',
                'Request Headers'
            ]);

            // Write data rows with updated field names
            foreach ($logs->chunk(1000) as $chunk) {
                foreach ($chunk as $log) {
                    fputcsv($handle, [
                        $log->Id,
                        $log->UserId,
                        $log->ActivityType,
                        $log->Action,
                        is_string($log->Details) ? $log->Details : json_encode($log->Details),
                        $log->Severity,
                        $log->created_on,
                        $log->RequestMethod,
                        $log->RequestUrl,
                        $log->RequestHeaders
                    ]);
                }
            }

            // Reset pointer to beginning
            rewind($handle);

            // Get content
            $content = stream_get_contents($handle);
            fclose($handle);

            return [
                'success' => true,
                'message' => 'CSV exported successfully',
                'data' => [
                    'content' => $content,
                    'filename' => $filename,
                    'headers' => $headers
                ]
            ];

        } catch (\Throwable $th) {
            Log::error('Error exporting CSV: ' . $th->getMessage());
            return [
                'success' => false,
                'message' => 'Error exporting to CSV',
                'data' => $th->getMessage()
            ];
        }
    }

    private function exportToJSON($logs)
    {
        try {
            $filename = 'activity_logs_' . date('Y-m-d_His') . '.json';
            $headers = [
                'Content-Type' => 'application/json',
                'Content-Disposition' => "attachment; filename=\"$filename\"",
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => false
            ];

            // Transform logs data with updated field names
            $exportData = $logs->map(function ($log) {
                return [
                    'id' => $log->Id,
                    'user' => [
                        'id' => $log->UserId
                    ],
                    'activity' => [
                        'type' => $log->ActivityType,
                        'action' => $log->Action,
                        'details' => is_string($log->Details) ?
                            json_decode($log->Details, true) ?? $log->Details :
                            $log->Details,
                        'severity' => $log->Severity
                    ],
                    'request' => [
                        'method' => $log->RequestMethod,
                        'url' => $log->RequestUrl,
                        'headers' => json_decode($log->RequestHeaders, true)
                    ],
                    'timestamp' => $log->created_on,
                    'metadata' => [
                        'exported_at' => now()->toIso8601String(),
                        'environment' => config('app.env')
                    ]
                ];
            });

            // Format JSON with proper encoding and pretty print
            $content = json_encode([
                'total_records' => $logs->count(),
                'exported_at' => now()->toIso8601String(),
                'logs' => $exportData
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('JSON encoding error: ' . json_last_error_msg());
            }

            return [
                'success' => true,
                'message' => 'JSON exported successfully',
                'data' => [
                    'content' => $content,
                    'filename' => $filename,
                    'headers' => $headers
                ]
            ];

        } catch (\Throwable $th) {
            Log::error('Error exporting JSON: ' . $th->getMessage());
            return [
                'success' => false,
                'message' => 'Error exporting to JSON',
                'data' => $th->getMessage()
            ];
        }
    }

}
