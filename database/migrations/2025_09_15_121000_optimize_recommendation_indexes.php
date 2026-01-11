<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_events', function (Blueprint $table) {
            $table->index(['session_id', 'event_type']);
            $table->index(['event_type', 'occurred_at']);
        });

        Schema::table('recommendation_impressions', function (Blueprint $table) {
            $table->index(['session_id', 'shown_at']);
        });
    }

    public function down(): void
    {
        Schema::table('product_events', function (Blueprint $table) {
            $table->dropIndex(['session_id', 'event_type']);
            $table->dropIndex(['event_type', 'occurred_at']);
        });

        Schema::table('recommendation_impressions', function (Blueprint $table) {
            $table->dropIndex(['session_id', 'shown_at']);
        });
    }
};
