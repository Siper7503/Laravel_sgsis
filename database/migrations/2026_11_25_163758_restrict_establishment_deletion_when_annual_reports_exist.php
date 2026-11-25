<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('annual_reports', function (Blueprint $table): void {
            $table->dropForeign(['etablissement_id']);
            $table->foreign('etablissement_id')
                ->references('id')
                ->on('etablissements')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('annual_reports', function (Blueprint $table): void {
            $table->dropForeign(['etablissement_id']);
            $table->foreign('etablissement_id')
                ->references('id')
                ->on('etablissements')
                ->cascadeOnDelete();
        });
    }
};
