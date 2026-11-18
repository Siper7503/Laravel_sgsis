<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('etablissement_agent', function (Blueprint $table) {
            $table->foreignId('etablissement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['etablissement_id', 'agent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etablissement_agent');
    }
};
