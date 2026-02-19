<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->enum('source', [
                'website', 'csv_import', 'portal', 'referral',
                'whatsapp', 'social_media', 'cold_call', 'other'
            ])->default('other');
            $table->enum('status', [
                'new', 'contacted', 'interested', 'viewing', 'won', 'lost'
            ])->default('new');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->string('property_type')->nullable();
            $table->decimal('budget_min', 15, 2)->nullable();
            $table->decimal('budget_max', 15, 2)->nullable();
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->json('tags')->nullable();
            $table->string('portal_id')->nullable();
            $table->timestamp('last_contacted_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
