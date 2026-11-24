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
        Schema::create('annual_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('school_year', 9);
            $table->enum('report_type', ['rentree', 'sortie'])->default('rentree');
            $table->unsignedSmallInteger('form_version')->default(1);
            $table->enum('status', [
                'draft',
                'submitted_to_dse',
                'returned_to_agent',
                'submitted_to_dgss',
                'returned_to_dse',
                'approved',
            ])->default('draft');
            $table->json('data');
            $table->timestamps();

            $table->unique(
                ['etablissement_id', 'school_year', 'report_type'],
                'annual_reports_establishment_year_type_unique',
            );
            $table->index(['status', 'school_year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('annual_reports');
    }
};
