<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseConnectionTest extends TestCase
{
    /**
     * Ensure the database connection can resolve and return a PDO instance.
     */
    public function test_database_connection_gets_pdo_successfully()
    {
        $mockConnection = \Mockery::mock(\Illuminate\Database\Connection::class);
        $mockConnection->shouldReceive('getPdo')->once()->andReturn((object) ['connected' => true]);

        DB::shouldReceive('connection')->once()->withNoArgs()->andReturn($mockConnection);

        $status = 'unknown';

        try {
            DB::connection()->getPdo();
            $status = 'ok';
        } catch (\Exception $e) {
            $status = 'error';
        }

        $this->assertSame('ok', $status);
    }

    /**
     * Ensure a failed PDO fetch is handled as a connection failure.
     */
    public function test_database_connection_handles_pdo_failure()
    {
        $mockConnection = \Mockery::mock(\Illuminate\Database\Connection::class);
        $mockConnection->shouldReceive('getPdo')->once()->andThrow(new \Exception('Could not connect to database'));

        DB::shouldReceive('connection')->once()->withNoArgs()->andReturn($mockConnection);

        $status = 'unknown';
        $message = null;

        try {
            DB::connection()->getPdo();
            $status = 'ok';
        } catch (\Exception $e) {
            $status = 'error';
            $message = $e->getMessage();
        }

        $this->assertSame('error', $status);
        $this->assertSame('Could not connect to database', $message);
    }
}
