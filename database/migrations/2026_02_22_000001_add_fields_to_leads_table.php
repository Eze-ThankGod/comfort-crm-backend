<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->timestamp('inspection_at')->nullable()->after('last_contacted_at');
            $table->decimal('budget', 15, 2)->nullable()->after('budget_max');
            $table->string('preferred_location', 150)->nullable()->after('location');
            $table->enum('intent', ['invest', 'move_in'])->nullable()->after('preferred_location');
            $table->string('finishing_type', 100)->nullable()->after('property_type');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['inspection_at', 'budget', 'preferred_location', 'intent', 'finishing_type']);
        });
    }
};
