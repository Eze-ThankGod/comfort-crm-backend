<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->enum('outcome', [
                'no_answer', 'interested', 'not_interested', 'callback',
                'voicemail', 'wrong_number', 'busy'
            ]);
            $table->integer('duration')->default(0)->comment('Duration in seconds');
            $table->text('notes')->nullable();
            $table->timestamp('called_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_logs');
    }
};
