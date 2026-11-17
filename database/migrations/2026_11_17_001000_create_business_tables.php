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
        // 1. Products
        if (!Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('supplier_id', 64)->index();
                $table->string('slug', 191)->unique();
                $table->string('name', 255);
                $table->string('display_name', 255)->nullable();
                $table->string('category', 100)->default('');
                $table->string('shop_category', 100)->nullable();
                $table->string('image_url', 1024)->nullable();
                $table->string('delivery_kind', 64)->default('');
                $table->integer('cost_usd_cents')->default(0);
                $table->integer('list_usd_cents')->default(0);
                $table->integer('stock')->nullable();
                $table->boolean('in_stock')->default(false);
                $table->boolean('preorder')->default(false);
                $table->integer('expire_days')->default(0);
                $table->string('warranty', 100)->default('');
                $table->boolean('requires_email')->default(false);
                $table->boolean('supplier_purchasable')->default(false);
                $table->boolean('supplier_active')->default(true);
                $table->integer('unavailable_readings')->default(0);

                // Shop settings
                $table->boolean('is_published')->default(false)->index();
                $table->boolean('is_featured')->default(false)->index();
                $table->integer('featured_rank')->nullable();
                $table->text('description')->nullable();
                $table->text('description_en')->nullable();
                $table->text('instructions')->nullable();
                $table->text('instructions_en')->nullable();
                $table->integer('price_xof_override')->nullable();
                $table->float('margin_percent_override')->nullable();
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();
            });
        }

        // 2. Orders
        if (!Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('reference', 24)->unique();
                $table->string('client_token', 36)->nullable()->index();
                $table->uuid('user_id')->index();
                $table->uuid('product_id')->nullable()->index();
                $table->string('product_name', 255);
                $table->string('supplier_product_id', 64)->nullable();
                $table->integer('quantity')->default(1);
                $table->integer('unit_price_xof')->default(0);
                $table->integer('total_xof')->default(0);
                $table->integer('unit_cost_usd_cents')->default(0);
                $table->integer('usd_rate')->default(620);
                $table->string('warranty', 100)->default('');
                $table->integer('expire_days')->default(0);

                $table->string('customer_first_name', 100)->nullable();
                $table->string('customer_last_name', 100)->nullable();
                $table->string('customer_email', 191)->nullable()->index();
                $table->string('customer_phone', 32)->nullable();
                $table->string('customer_country', 8)->nullable();
                $table->json('activation_emails')->nullable();
                $table->json('custom_fields')->nullable();

                $table->string('status', 32)->default('pending_payment')->index();
                $table->string('payment_checkout_id', 191)->nullable()->index();
                $table->string('payment_intent_id', 191)->nullable()->index();
                $table->text('payment_url')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->string('paid_with', 16)->default('pawapay');

                $table->integer('reminder_count')->default(0);
                $table->timestamp('last_reminder_at')->nullable();

                $table->string('supplier_idem_key', 64)->nullable();
                $table->string('supplier_order_id', 64)->nullable()->index();
                $table->string('supplier_reference', 64)->nullable();
                $table->string('supplier_status', 64)->nullable();
                $table->integer('fulfil_attempts')->default(0);
                $table->timestamp('fulfil_locked_at')->nullable();
                $table->text('delivery_encrypted')->nullable();
                $table->timestamp('delivered_at')->nullable();

                $table->string('failure_code', 64)->nullable();
                $table->string('failure_message', 500)->nullable();
                $table->timestamp('refund_requested_at')->nullable();
                $table->timestamp('refunded_at')->nullable();
                $table->string('refund_method', 16)->nullable();
                $table->text('admin_note')->nullable();

                $table->timestamps();
            });
        }

        // 3. Order Events
        if (!Schema::hasTable('order_events')) {
            Schema::create('order_events', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('order_id')->index();
                $table->string('type', 64)->nullable();
                $table->string('event', 64)->nullable();
                $table->string('message', 500);
                $table->string('actor', 191)->default('system');
                $table->timestamps();
            });
        }

        // 4. Wallet Entries
        if (!Schema::hasTable('wallet_entries')) {
            Schema::create('wallet_entries', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->index();
                $table->integer('amount_xof');
                $table->integer('balance_after_xof');
                $table->string('kind', 32)->default('purchase');
                $table->string('type', 32)->nullable();
                $table->uuid('order_id')->nullable()->index();
                $table->string('label', 255)->nullable();
                $table->string('description', 255)->nullable();
                $table->string('reference_type', 64)->nullable();
                $table->string('reference_id', 64)->nullable();
                $table->string('actor', 191)->default('system');
                $table->timestamps();
            });
        }

        // 5. Wallet Deposits
        if (!Schema::hasTable('wallet_deposits')) {
            Schema::create('wallet_deposits', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('reference', 32)->unique();
                $table->uuid('user_id')->index();
                $table->integer('amount_xof');
                $table->string('customer_phone', 32)->nullable();
                $table->string('customer_country', 8)->default('BF');
                $table->string('customer_name', 150)->nullable();
                $table->string('status', 24)->default('pending')->index();
                $table->string('payment_checkout_id', 191)->nullable()->index();
                $table->string('payment_intent_id', 191)->nullable()->index();
                $table->text('payment_url')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('credited_at')->nullable();
                $table->string('failure_message', 255)->nullable();
                $table->timestamps();
            });
        }

        // 6. Tickets
        if (!Schema::hasTable('tickets')) {
            Schema::create('tickets', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('reference', 24)->unique();
                $table->uuid('user_id')->index();
                $table->uuid('order_id')->nullable()->index();
                $table->string('subject', 200);
                $table->string('status', 24)->default('open')->index();
                $table->timestamps();
            });
        }

        // 7. Ticket Messages
        if (!Schema::hasTable('ticket_messages')) {
            Schema::create('ticket_messages', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('ticket_id')->index();
                $table->uuid('author_id')->index();
                $table->boolean('is_staff')->default(false);
                $table->text('body');
                $table->timestamps();
            });
        }

        // 8. Stock Alerts
        if (!Schema::hasTable('stock_alerts')) {
            Schema::create('stock_alerts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->index();
                $table->uuid('product_id')->index();
                $table->timestamp('notified_at')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'product_id']);
            });
        }

        // 9. Settings
        if (!Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->string('key', 64)->primary();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        // 10. Audit Logs
        if (!Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('admin_id')->index();
                $table->string('admin_email', 191);
                $table->string('action', 100);
                $table->string('target', 191)->default('');
                $table->string('details', 1000)->default('');
                $table->string('ip', 64)->nullable();
                $table->timestamps();
            });
        }

        // 11. Login Codes
        if (!Schema::hasTable('login_codes')) {
            Schema::create('login_codes', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('email', 191)->index();
                $table->string('code_hash', 64);
                $table->integer('attempts')->default(0);
                $table->timestamp('expires_at');
                $table->timestamp('consumed_at')->nullable();
                $table->string('ip', 64)->nullable();
                $table->timestamps();
            });
        }

        // 12. Rate Limits
        if (!Schema::hasTable('rate_limits')) {
            Schema::create('rate_limits', function (Blueprint $table) {
                $table->string('key', 191)->primary();
                $table->integer('count')->default(0);
                $table->timestamp('reset_at');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rate_limits');
        Schema::dropIfExists('login_codes');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('stock_alerts');
        Schema::dropIfExists('ticket_messages');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('wallet_deposits');
        Schema::dropIfExists('wallet_entries');
        Schema::dropIfExists('order_events');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('products');
    }
};
