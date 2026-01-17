<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
abstract class Controller
{

    protected $bk_api_db;
    protected $bk_db;
    public function __construct()
    {

     // Set up database connections
     $this->bk_api_db = DB::connection('bk_api_db');  // Uses 'dbmysql' configuration from config/database.php
     $this->bk_db = DB::connection('bk_db');  // Uses the default 'mysql' connection

     // Optionally print out the selected database for further debugging
    //  print_r(DB::connection('dbmysql')->getDatabaseName());
    //  print_r(DB::connection('mysql')->getDatabaseName());
    }
}
