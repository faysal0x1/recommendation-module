<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_behavior_analytics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('session_id')->nullable();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('category_id')->nullable();

            // Session tracking
            $table->timestamp('session_started_at')->nullable();
            $table->timestamp('first_view_at')->useCurrent();
            $table->timestamp('last_view_at')->useCurrent();
            $table->integer('view_count')->default(1);
            $table->integer('total_view_duration')->default(0)->comment('Total seconds spent viewing');

            // Engagement metrics
            $table->integer('max_scroll_depth')->default(0);
            $table->integer('image_views')->default(0);
            $table->integer('specs_views')->default(0);
            $table->integer('reviews_views')->default(0);
            $table->boolean('added_to_cart')->default(false);
            $table->boolean('added_to_wishlist')->default(false);
            $table->boolean('shared')->default(false);
            $table->integer('return_visits')->default(0);

            // Conversion tracking
            $table->boolean('purchased')->default(false);
            $table->timestamp('purchased_at')->nullable();
            $table->decimal('purchase_amount', 10, 2)->nullable();

            // Device and location
            $table->string('device_type')->nullable();
            $table->string('browser')->nullable();
            $table->string('os')->nullable();
            $table->string('ip_address', 45)->nullable();

            // Metadata
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'product_id']);
            $table->index(['session_id', 'product_id']);
            $table->index(['product_id', 'last_view_at']);
            $table->index(['category_id', 'last_view_at']);
            $table->index(['purchased', 'purchased_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_behavior_analytics');
    }
};
