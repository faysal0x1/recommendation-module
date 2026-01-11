<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_events', function (Blueprint $table) {
            // User tracking fields
            $table->string('ip_address', 45)->nullable()->after('session_id');
            $table->text('user_agent')->nullable()->after('ip_address');
            $table->string('referrer')->nullable()->after('user_agent');
            $table->string('utm_source')->nullable()->after('referrer');
            $table->string('utm_medium')->nullable()->after('utm_source');
            $table->string('utm_campaign')->nullable()->after('utm_medium');

            // Product context
            $table->unsignedBigInteger('category_id')->nullable()->after('product_id');
            $table->unsignedBigInteger('brand_id')->nullable()->after('category_id');
            $table->decimal('product_price', 10, 2)->nullable()->after('brand_id');

            // Interaction details
            $table->integer('view_duration')->nullable()->after('meta')->comment('Time spent viewing in seconds');
            $table->integer('scroll_depth')->nullable()->after('view_duration')->comment('Percentage of page scrolled');
            $table->boolean('image_viewed')->default(false)->after('scroll_depth');
            $table->boolean('specs_viewed')->default(false)->after('image_viewed');
            $table->boolean('reviews_viewed')->default(false)->after('specs_viewed');
            $table->string('device_type')->nullable()->after('reviews_viewed')->comment('mobile, tablet, desktop');
            $table->string('browser')->nullable()->after('device_type');
            $table->string('os')->nullable()->after('browser');

            // Add indexes for better query performance
            $table->index(['ip_address', 'occurred_at']);
            $table->index(['category_id', 'event_type']);
            $table->index(['brand_id', 'event_type']);
            $table->index(['event_type', 'occurred_at', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('product_events', function (Blueprint $table) {
            $table->dropIndex(['ip_address', 'occurred_at']);
            $table->dropIndex(['category_id', 'event_type']);
            $table->dropIndex(['brand_id', 'event_type']);
            $table->dropIndex(['event_type', 'occurred_at', 'user_id']);

            $table->dropColumn([
                'ip_address',
                'user_agent',
                'referrer',
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'category_id',
                'brand_id',
                'product_price',
                'view_duration',
                'scroll_depth',
                'image_viewed',
                'specs_viewed',
                'reviews_viewed',
                'device_type',
                'browser',
                'os',
            ]);
        });
    }
};
