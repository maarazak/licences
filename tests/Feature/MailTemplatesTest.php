<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\MailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class MailTemplatesTest extends TestCase
{
    use RefreshDatabase;

    protected MailService $mailService;
    protected User $user;
    protected Product $product;
    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->mailService = app(MailService::class);

        $this->user = User::create([
            'id' => (string) Str::uuid(),
            'email' => 'client@test.ci',
            'role' => 'client',
            'lang' => 'fr',
            'balance_xof' => 5000,
        ]);

        $this->product = Product::create([
            'id' => 'prod-mail-test',
            'supplier_id' => 101,
            'name' => 'Canva Pro 1 An',
            'slug' => 'canva-pro-1-an',
            'category' => 'design',
            'description' => 'Abonnement Canva Pro',
            'price_xof' => 5000,
            'cost_usd_cents' => 450,
            'is_published' => true,
            'in_stock' => true,
            'supplier_active' => true,
            'supplier_purchasable' => true,
        ]);

        $this->order = Order::create([
            'id' => (string) Str::uuid(),
            'reference' => 'CMD-MAIL-001',
            'user_id' => $this->user->id,
            'customer_email' => $this->user->email,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'quantity' => 1,
            'unit_price_xof' => 5000,
            'total_xof' => 5000,
            'status' => 'pending_payment',
            'paid_with' => 'pawapay',
        ]);
    }

    public function test_send_login_code_fr_and_en(): void
    {
        $this->assertTrue($this->mailService->sendLoginCode('test@test.ci', '123456', 'fr'));
        $this->assertTrue($this->mailService->sendLoginCode('test@test.ci', '654321', 'en'));
        Mail::assertSentCount(2);
    }

    public function test_send_admin_2fa_code(): void
    {
        $this->assertTrue($this->mailService->sendAdmin2faCode('admin@licences.ci', '999888'));
        Mail::assertSentCount(1);
    }

    public function test_send_order_lifecycle_emails(): void
    {
        // 1. Order paid
        $this->assertTrue($this->mailService->sendOrderPaid($this->order, 'fr'));
        $this->assertTrue($this->mailService->sendOrderPaid($this->order, 'en'));

        // 2. Order delivered
        $this->assertTrue($this->mailService->sendOrderDelivered($this->order, 'fr'));
        $this->assertTrue($this->mailService->sendOrderDelivered($this->order, 'en'));

        // 3. Order activation
        $this->assertTrue($this->mailService->sendOrderActivation($this->order, 'fr'));

        // 4. Order delayed
        $this->assertTrue($this->mailService->sendOrderDelayed($this->order, 'fr'));

        // 5. Order refunded
        $this->assertTrue($this->mailService->sendOrderRefunded($this->order, 'fr'));

        // 6. Order credited
        $this->assertTrue($this->mailService->sendOrderCredited($this->order, 'fr'));

        Mail::assertSentCount(8);
    }

    public function test_send_payment_reminder_stage1_and_stage2(): void
    {
        $this->assertTrue($this->mailService->sendPaymentReminder($this->order, 1, $this->product->slug, 'fr'));
        $this->assertTrue($this->mailService->sendPaymentReminder($this->order, 2, $this->product->slug, 'en'));
        Mail::assertSentCount(2);
    }

    public function test_send_stock_alert_and_ticket_reply(): void
    {
        $this->assertTrue($this->mailService->sendStockAlert('client@test.ci', $this->product, 'fr'));
        $this->assertTrue($this->mailService->sendStockAlert('client@test.ci', $this->product, 'en'));

        $ticket = Ticket::create([
            'id' => (string) Str::uuid(),
            'reference' => 'TCK-001',
            'user_id' => $this->user->id,
            'subject' => 'Problème de connexion',
            'status' => 'open',
        ]);

        $message = TicketMessage::create([
            'id' => (string) Str::uuid(),
            'ticket_id' => $ticket->id,
            'author_id' => $this->user->id,
            'is_staff' => true,
            'body' => 'Voici vos nouveaux identifiants...',
        ]);

        $this->assertTrue($this->mailService->sendTicketReply('client@test.ci', $ticket, $message, 'fr'));

        $this->assertTrue($this->mailService->sendAdminAlert('admin@licences.ci', 'Stock épuisé', ['Le stock est à zéro']));

        Mail::assertSentCount(4);
    }
}
