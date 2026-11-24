<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
            DB::statement(
                "ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('superadmin', 'admin', 'agent', 'usager', 'dse', 'dgss'))",
            );

            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', [
                'superadmin',
                'admin',
                'agent',
                'usager',
                'dse',
                'dgss',
            ])->default('usager')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('users')->whereIn('role', ['dse', 'dgss'])->exists()) {
            throw new RuntimeException(
                'DSE/DGSS users must be reassigned before rolling back their roles.',
            );
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
            DB::statement(
                "ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('superadmin', 'admin', 'agent', 'usager'))",
            );

            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['superadmin', 'admin', 'agent', 'usager'])
                ->default('usager')
                ->change();
        });
    }
};
