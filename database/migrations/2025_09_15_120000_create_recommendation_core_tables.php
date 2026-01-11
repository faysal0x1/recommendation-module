<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('session_id')->nullable();
            $table->unsignedBigInteger('cart_id')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->json('meta')->nullable();
            $table->index(['event_type', 'product_id']);
            $table->index(['user_id', 'event_type']);
        });

        Schema::create('product_popularity', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->primary();
            $table->unsignedBigInteger('category_id')->index();
            $table->unsignedBigInteger('view_count')->default(0);
            $table->unsignedBigInteger('purchase_count')->default(0);
            $table->double('view_score')->default(0);
            $table->double('purchase_score')->default(0);
            $table->timestamp('computed_at')->nullable();
        });

        Schema::create('product_copurchase', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('copurchased_product_id');
            $table->unsignedInteger('count')->default(0);
            $table->double('score')->default(0);
            $table->unique(['product_id', 'copurchased_product_id']);
            $table->index(['product_id', 'score']);
        });

        Schema::create('recommendation_cache', function (Blueprint $table) {
            $table->id();
            $table->string('algorithm');
            $table->string('context');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('session_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->json('results');
            $table->timestamp('cached_at');
            $table->index(['algorithm', 'context']);
            $table->index(['user_id']);
            $table->index(['session_id']);
            $table->index(['product_id']);
        });

        Schema::create('recommendation_impressions', function (Blueprint $table) {
            $table->id();
            $table->string('recommendation_id');
            $table->string('algorithm');
            $table->string('variant')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('session_id')->nullable();
            $table->json('items');
            $table->timestamp('shown_at')->useCurrent();
            $table->index(['algorithm', 'shown_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendation_impressions');
        Schema::dropIfExists('recommendation_cache');
        Schema::dropIfExists('product_copurchase');
        Schema::dropIfExists('product_popularity');
        Schema::dropIfExists('product_events');
    }
};
