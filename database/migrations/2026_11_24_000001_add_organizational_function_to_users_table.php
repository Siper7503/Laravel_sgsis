<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('organizational_function', [
                'directeur_dse',
                'directeur_dgss',
                'proviseur',
                'directeur_ecole',
            ])->nullable();
        });

        DB::table('users')
            ->where('role', 'dse')
            ->update([
                'role' => 'admin',
                'organizational_function' => 'directeur_dse',
            ]);

        DB::table('users')
            ->where('role', 'dgss')
            ->update([
                'role' => 'admin',
                'organizational_function' => 'directeur_dgss',
            ]);

        $agentFunctions = DB::table('etablissement_agent')
            ->join('etablissements', 'etablissements.id', '=', 'etablissement_agent.etablissement_id')
            ->select('etablissement_agent.agent_id')
            ->selectRaw('MIN(etablissements.type) as establishment_type')
            ->selectRaw('COUNT(DISTINCT etablissements.type) as establishment_type_count')
            ->groupBy('etablissement_agent.agent_id')
            ->get();

        foreach ($agentFunctions as $agentFunction) {
            if ((int) $agentFunction->establishment_type_count !== 1) {
                continue;
            }

            $organizationalFunction = match ($agentFunction->establishment_type) {
                'primaire' => 'directeur_ecole',
                'secondaire' => 'proviseur',
                default => null,
            };

            if ($organizationalFunction !== null) {
                DB::table('users')
                    ->where('id', $agentFunction->agent_id)
                    ->where('role', 'agent')
                    ->update(['organizational_function' => $organizationalFunction]);
            }
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

    public function down(): void
    {
        if (DB::table('users')->whereNotNull('organizational_function')->exists()) {
            throw new RuntimeException(
                'Users with an organizational function must be reassigned before rolling back.',
            );
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
            DB::statement(
                "ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('superadmin', 'admin', 'agent', 'usager', 'dse', 'dgss'))",
            );
        } else {
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

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('organizational_function');
        });
    }
};
