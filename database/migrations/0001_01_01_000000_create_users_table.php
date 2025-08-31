<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\SoftDeletes;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create ENUM type for user roles if it doesn't exist
        DB::statement("DO $$ BEGIN
            CREATE TYPE user_role AS ENUM ('owner', 'employee');
        EXCEPTION
            WHEN duplicate_object THEN null;
        END $$;");

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('business_name')->nullable();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->enum('role', ['owner', 'employee', 'admin'])->default('employee');
            $table->boolean('active')->default(true);
            $table->rememberToken();
            $table->softDeletes();
            $table->timestampsTz();
            // Indexes
            $table->index('role');
            $table->index('active');
            $table->index(['active', 'role']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');

        // Drop enum type
        DB::statement('DROP TYPE IF EXISTS user_role');
    }
};
