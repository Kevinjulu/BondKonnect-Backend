<?php

/**
 * Real-time Concurrent User Monitor
 * Run this script to monitor concurrent users in real-time
 */

require_once 'vendor/autoload.php';

class ConcurrentUserMonitor
{
    private $baseUrl;
    private $refreshInterval;

    public function __construct($baseUrl, $refreshInterval = 5)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->refreshInterval = $refreshInterval;
    }

    /**
     * Get concurrent users from API
     */
    public function getConcurrentUsers()
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->baseUrl . '/api/V1/auth/concurrent-users',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpCode === 200) {
            return json_decode($response, true);
        }

        return null;
    }

    /**
     * Format user data for display
     */
    private function formatUserData($users)
    {
        $formatted = [];
        foreach ($users as $user) {
            $name = $user['FirstName'] ?
                $user['FirstName'] . ' ' . ($user['OtherNames'] ?? '') :
                $user['CompanyName'];

            $formatted[] = [
                'Name' => $name ?: 'N/A',
                'Email' => $user['Email'],
                'Last Login' => date('Y-m-d H:i:s', strtotime($user['LastLogOn'])),
                'IP' => $user['IpAddress'],
                'Browser' => $this->getBrowserName($user['UserAgent'])
            ];
        }
        return $formatted;
    }

    /**
     * Extract browser name from user agent
     */
    private function getBrowserName($userAgent)
    {
        if (strpos($userAgent, 'Chrome') !== false) return 'Chrome';
        if (strpos($userAgent, 'Firefox') !== false) return 'Firefox';
        if (strpos($userAgent, 'Safari') !== false) return 'Safari';
        if (strpos($userAgent, 'Edge') !== false) return 'Edge';
        return 'Other';
    }

    /**
     * Display concurrent users table
     */
    private function displayUsersTable($users)
    {
        if (empty($users)) {
            echo "No active users found.\n";
            return;
        }

        $formatted = $this->formatUserData($users);

        // Calculate column widths
        $nameWidth = max(20, max(array_map('strlen', array_column($formatted, 'Name'))));
        $emailWidth = max(25, max(array_map('strlen', array_column($formatted, 'Email'))));
        $ipWidth = 15;
        $browserWidth = 10;
        $loginWidth = 19;

        // Header
        echo str_repeat('-', $nameWidth + $emailWidth + $ipWidth + $browserWidth + $loginWidth + 16) . "\n";
        printf("| %-{$nameWidth}s | %-{$emailWidth}s | %-{$ipWidth}s | %-{$browserWidth}s | %-{$loginWidth}s |\n",
               'Name', 'Email', 'IP Address', 'Browser', 'Last Login');
        echo str_repeat('-', $nameWidth + $emailWidth + $ipWidth + $browserWidth + $loginWidth + 16) . "\n";

        // Data rows
        foreach ($formatted as $user) {
            printf("| %-{$nameWidth}s | %-{$emailWidth}s | %-{$ipWidth}s | %-{$browserWidth}s | %-{$loginWidth}s |\n",
                   substr($user['Name'], 0, $nameWidth),
                   substr($user['Email'], 0, $emailWidth),
                   $user['IP'],
                   $user['Browser'],
                   $user['Last Login']);
        }

        echo str_repeat('-', $nameWidth + $emailWidth + $ipWidth + $browserWidth + $loginWidth + 16) . "\n";
    }

    /**
     * Monitor concurrent users
     */
    public function monitor()
    {
        echo "Concurrent User Monitor\n";
        echo "======================\n";
        echo "Refresh interval: {$this->refreshInterval} seconds\n";
        echo "Press Ctrl+C to stop monitoring\n\n";

        $iteration = 0;
        while (true) {
            $iteration++;

            // Clear screen (works on Unix-like systems)
            system('clear');

            echo "Concurrent User Monitor - Iteration {$iteration}\n";
            echo "Time: " . date('Y-m-d H:i:s') . "\n";
            echo "==============================================\n\n";

            $data = $this->getConcurrentUsers();

            if ($data && $data['success']) {
                $totalUsers = $data['data']['total_active_users'];
                $activeUsers = $data['data']['active_users'];

                echo "Total Active Users: {$totalUsers}\n\n";

                if ($totalUsers > 0) {
                    $this->displayUsersTable($activeUsers);

                    // Show warning if getting close to expected limits
                    if ($totalUsers >= 8) {
                        echo "\n⚠️  WARNING: High concurrent user count detected!\n";
                    } elseif ($totalUsers >= 5) {
                        echo "\n✅ Good: System handling multiple concurrent users well.\n";
                    } elseif ($totalUsers >= 3) {
                        echo "\n✅ Target met: 3+ concurrent users active.\n";
                    } else {
                        echo "\n📊 Info: Currently {$totalUsers} concurrent user(s) active.\n";
                    }
                } else {
                    echo "No users currently active.\n";
                }
            } else {
                echo "❌ Error: Could not retrieve concurrent user data.\n";
                if ($data && isset($data['message'])) {
                    echo "Message: " . $data['message'] . "\n";
                }
            }

            echo "\nNext update in {$this->refreshInterval} seconds...\n";
            sleep($this->refreshInterval);
        }
    }
}

// Usage
if (isset($argv[1])) {
    $baseUrl = $argv[1];
} else {
    echo "Usage: php monitor_concurrent_users.php <base_url> [refresh_interval]\n";
    echo "Example: php monitor_concurrent_users.php http://localhost:8000 5\n";
    exit(1);
}

$refreshInterval = isset($argv[2]) ? (int)$argv[2] : 5;

$monitor = new ConcurrentUserMonitor($baseUrl, $refreshInterval);
$monitor->monitor();
