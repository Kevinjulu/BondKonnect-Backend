<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Disable transaction for this migration to reveal the underlying error.
     */
    public $withinTransaction = false;

    /**
     * Run the migrations.
     * These tables are typically in 'bk_db' and are needed for the seeders and legacy logic.
     */
    public function up(): void
    {
        // Table: roles
        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->increments('Id');
                $table->string('Name');
                $table->string('Description')->nullable();
                $table->timestamp('created_on')->useCurrent();
            });

            // Insert default roles if needed
            DB::table('roles')->insert([
                ['Id' => 1, 'Name' => 'Admin', 'created_on' => now()],
                ['Id' => 2, 'Name' => 'Individual', 'created_on' => now()],
                ['Id' => 4, 'Name' => 'Issuer', 'created_on' => now()],
                ['Id' => 5, 'Name' => 'Broker', 'created_on' => now()],
            ]);
        }

        // Table: portaluserlogoninfo
        if (!Schema::hasTable('portaluserlogoninfo')) {
            Schema::create('portaluserlogoninfo', function (Blueprint $table) {
                $table->increments('Id');
                $table->string('AccountId', 50)->unique();
                $table->string('FirstName');
                $table->string('OtherNames')->nullable();
                $table->string('CompanyName')->nullable();
                $table->string('Email', 191)->unique();
                $table->string('PhoneNumber')->nullable();
                $table->string('CdsNo')->nullable();
                $table->boolean('IsLocal')->default(true);
                $table->boolean('IsForeign')->default(false);
                $table->boolean('IsActive')->default(true);
                $table->timestamp('created_on')->useCurrent();
            });
        }

        // Table: userroles
        if (!Schema::hasTable('userroles')) {
            Schema::create('userroles', function (Blueprint $table) {
                $table->increments('Id');
                $table->unsignedBigInteger('User');
                $table->unsignedBigInteger('Role');
                $table->timestamp('created_on')->useCurrent();

                // Foreign keys (optional, but good for integrity if in same DB)
                // $table->foreign('User')->references('id')->on('portaluserlogoninfo');
                // $table->foreign('Role')->references('id')->on('roles');
            });
        }

        // Table: portaluserpasswordshistory
        if (!Schema::hasTable('portaluserpasswordshistory')) {
            Schema::create('portaluserpasswordshistory', function (Blueprint $table) {
                $table->increments('Id');
                $table->unsignedBigInteger('User');
                $table->string('Password');
                $table->boolean('IsActive')->default(true);
                $table->timestamp('created_on')->useCurrent();

                // $table->foreign('User')->references('id')->on('portaluserlogoninfo');
            });
        }

        // Table: quotebook (mentioned in StandardFunctions)
        if (!Schema::hasTable('quotebook')) {
            Schema::create('quotebook', function (Blueprint $table) {
                $table->increments('Id');
                $table->timestamp('created_on')->useCurrent();
                // Add other columns as needed based on usage
            });
        }

        // Table: emaillogs (mentioned in StandardFunctions)
        if (!Schema::hasTable('emaillogs')) {
            Schema::create('emaillogs', function (Blueprint $table) {
                $table->increments('Id');
                $table->unsignedBigInteger('Recipient')->nullable();
                $table->text('AllRecipientsEmails')->nullable();
                $table->string('Subject')->nullable();
                $table->text('Body')->nullable();
                $table->text('CC')->nullable();
                $table->text('BCC')->nullable();
                $table->timestamp('ScheduleDate')->nullable();
                $table->integer('RoleGroupSendingTo')->nullable();
                $table->boolean('IsDraft')->default(false);
                $table->boolean('IsSent')->default(false);
                $table->boolean('IsFromSystem')->default(false);
                $table->boolean('IsBulkEmail')->default(false);
                $table->timestamp('created_on')->useCurrent();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emaillogs');
        Schema::dropIfExists('quotebook');
        Schema::dropIfExists('portaluserpasswordshistory');
        Schema::dropIfExists('userroles');
        Schema::dropIfExists('portaluserlogoninfo');
        Schema::dropIfExists('roles');
    }
};
