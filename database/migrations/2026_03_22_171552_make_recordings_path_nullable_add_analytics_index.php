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
        Schema::table('recordings', function (Blueprint $table) {
            // path is nulled after audio cleanup (DATA-01 Option C)
            $table->string('path')->nullable()->change();

            // Composite index for UI-02 analytics aggregations
            $table->index(['user_id', 'status', 'created_at'], 'recordings_analytics_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recordings', function (Blueprint $table) {
            $table->dropIndex('recordings_analytics_index');
            $table->string('path')->nullable(false)->change();
        });
    }
};
