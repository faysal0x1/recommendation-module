<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_viewed_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('brand_id')->nullable();

            // View tracking
            $table->timestamp('first_viewed_at')->useCurrent();
            $table->timestamp('last_viewed_at')->useCurrent();
            $table->integer('view_count')->default(1);
            $table->integer('total_view_duration')->default(0)->comment('Total seconds spent viewing');

            // Engagement metrics
            $table->integer('max_scroll_depth')->default(0);
            $table->boolean('image_viewed')->default(false);
            $table->boolean('specs_viewed')->default(false);
            $table->boolean('reviews_viewed')->default(false);

            // Interaction flags
            $table->boolean('added_to_cart')->default(false);
            $table->boolean('added_to_wishlist')->default(false);
            $table->timestamp('added_to_cart_at')->nullable();
            $table->timestamp('added_to_wishlist_at')->nullable();

            // Conversion tracking
            $table->boolean('purchased')->default(false);
            $table->timestamp('purchased_at')->nullable();
            $table->decimal('purchase_amount', 10, 2)->nullable();

            // Device info from first view
            $table->string('device_type')->nullable();
            $table->string('browser')->nullable();
            $table->string('os')->nullable();

            // Metadata
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->unique(['user_id', 'product_id'], 'user_product_unique');
            $table->index(['user_id', 'last_viewed_at']);
            $table->index(['user_id', 'view_count']);
            $table->index(['product_id', 'user_id']);
            $table->index(['category_id', 'user_id']);
            $table->index(['purchased', 'purchased_at']);

            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_viewed_products');
    }
};
