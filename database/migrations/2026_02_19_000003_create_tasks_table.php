<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->enum('type', ['call', 'follow_up', 'site_visit', 'whatsapp'])->default('call');
            $table->enum('status', ['pending', 'completed', 'missed'])->default('pending');
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('due_date');
            $table->timestamp('completed_at')->nullable();
            $table->boolean('reminder_sent')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
