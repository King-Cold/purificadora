<?php

namespace Tests\Feature;

use App\Models\BranchSchedule;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReview;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrderReviewFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $clientUser;
    protected Customer $customer;
    protected User $otherClientUser;
    protected Customer $otherCustomer;
    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        BranchSchedule::create([
            'opening_time' => '08:00:00',
            'break_start_time' => '13:00:00',
            'break_end_time' => '15:00:00',
            'closing_time' => '19:00:00',
            'order_cutoff_advance_minutes' => 60,
        ]);

        $this->admin = User::factory()->create(['name' => 'Admin Puritronic', 'status' => 'active']);
        $this->admin->assignRole('Administrador');

        $this->clientUser = User::factory()->create(['name' => 'María Elena', 'status' => 'active']);
        $this->clientUser->assignRole('Cliente');

        $this->customer = Customer::create([
            'user_id' => $this->clientUser->id,
            'code' => 'CLI-1001',
            'name' => 'María Elena Pech',
            'nickname' => 'Doña Mari',
            'phone' => '9991112233',
            'fixed_bottles_count' => 0,
            'exchange_bottles_count' => 2,
        ]);

        CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'street' => 'Calle 21',
            'exterior_number' => '100',
            'neighborhood' => 'Centro',
            'city' => 'Hocabá',
            'delivery_zone' => CustomerAddress::ZONE_NORTH,
            'is_default' => true,
        ]);

        $this->otherClientUser = User::factory()->create(['name' => 'Otro Cliente', 'status' => 'active']);
        $this->otherClientUser->assignRole('Cliente');

        $this->otherCustomer = Customer::create([
            'user_id' => $this->otherClientUser->id,
            'code' => 'CLI-1002',
            'name' => 'José Ku',
            'nickname' => 'Don Pepe',
            'phone' => '9992223344',
            'fixed_bottles_count' => 0,
            'exchange_bottles_count' => 1,
        ]);

        $this->order = Order::create([
            'folio' => 'PED-REV-001',
            'customer_id' => $this->customer->id,
            'created_by_user_id' => $this->clientUser->id,
            'recipient_name' => 'Doña Mari',
            'delivery_street' => 'Calle 21',
            'delivery_exterior_number' => '100',
            'delivery_zone' => CustomerAddress::ZONE_NORTH,
            'address_snapshot' => [
                'recipient_name' => 'Doña Mari',
                'delivery_street' => 'Calle 21',
                'delivery_exterior_number' => '100',
                'delivery_zone' => CustomerAddress::ZONE_NORTH,
            ],
            'status' => Order::STATUS_DELIVERED,
        ]);

        OrderItem::create([
            'order_id' => $this->order->id,
            'type' => OrderItem::TYPE_EXCHANGE,
            'bottle_type' => 'cambio',
            'quantity' => 2,
            'delivered_quantity' => 2,
            'unit_price' => 15.00,
            'subtotal' => 30.00,
            'status' => OrderItem::STATUS_DELIVERED,
        ]);
    }

    public function test_client_can_rate_order_and_comment_starts_hidden_from_landing(): void
    {
        $response = $this->actingAs($this->clientUser)
            ->post(route('cliente.orders.review.store', $this->order->id), [
                'rating' => 5,
                'comment' => 'Excelente servicio, llegó muy rápido y el agua sabe muy limpia.',
            ]);

        $response->assertRedirect(route('cliente.orders.show', $this->order->id));
        $response->assertSessionHas('success');

        $review = OrderReview::where('order_id', $this->order->id)->first();
        $this->assertNotNull($review);
        $this->assertEquals(5, $review->rating);
        $this->assertFalse($review->show_on_landing);

        // Landing page todavía no lo muestra hasta que el Administrador lo apruebe
        $welcomeResponse = $this->get('/');
        $welcomeResponse->assertOk();
        $welcomeResponse->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->has('reviews', 0)
            ->where('reviewsMeta.count', 0)
        );
    }

    public function test_prevents_other_customer_duplicate_or_cancelled_order_reviews(): void
    {
        // Otro cliente no puede calificar un pedido ajeno
        $this->actingAs($this->otherClientUser)
            ->post(route('cliente.orders.review.store', $this->order->id), [
                'rating' => 1,
                'comment' => 'Intento no autorizado.',
            ])
            ->assertForbidden();

        // Cliente dueño califica una vez
        $this->actingAs($this->clientUser)
            ->post(route('cliente.orders.review.store', $this->order->id), [
                'rating' => 4,
                'comment' => 'Muy buen trato del repartidor.',
            ])
            ->assertRedirect(route('cliente.orders.show', $this->order->id));

        // Segundo intento duplicado es rechazado
        $this->actingAs($this->clientUser)
            ->post(route('cliente.orders.review.store', $this->order->id), [
                'rating' => 5,
                'comment' => 'Intento duplicado.',
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('order_reviews', 1);

        // Pedido cancelado no permite reseña
        $cancelledOrder = Order::create([
            'folio' => 'PED-REV-CANC',
            'customer_id' => $this->customer->id,
            'created_by_user_id' => $this->clientUser->id,
            'recipient_name' => 'Doña Mari',
            'delivery_street' => 'Calle 21',
            'delivery_exterior_number' => '100',
            'delivery_zone' => CustomerAddress::ZONE_NORTH,
            'address_snapshot' => ['recipient_name' => 'Doña Mari'],
            'status' => Order::STATUS_CANCELLED,
        ]);

        $this->actingAs($this->clientUser)
            ->post(route('cliente.orders.review.store', $cancelledOrder->id), [
                'rating' => 5,
                'comment' => 'No debería poder calificarse.',
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('order_reviews', 1);
    }

    public function test_admin_can_moderate_and_feature_real_reviews_on_landing_page(): void
    {
        $review = OrderReview::create([
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->clientUser->id,
            'rating' => 5,
            'comment' => 'Puntualidad y garrafones bien sellados.',
            'show_on_landing' => false,
        ]);

        // Un cliente no puede entrar al panel de moderación de comentarios
        $this->actingAs($this->clientUser)
            ->get(route('admin.reviews.index'))
            ->assertForbidden();

        // Admin visualiza el apartado de comentarios
        $this->actingAs($this->admin)
            ->get(route('admin.reviews.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Reviews/Index')
                ->has('reviews.data', 1)
                ->where('reviews.data.0.comment', 'Puntualidad y garrafones bien sellados.')
                ->where('reviews.data.0.show_on_landing', false)
                ->where('stats.total', 1)
                ->where('stats.featured', 0)
            );

        // Admin activa "Mostrar en landing"
        $this->actingAs($this->admin)
            ->patch(route('admin.reviews.toggle-landing', $review->id))
            ->assertRedirect();

        $this->assertTrue($review->fresh()->show_on_landing);

        // Landing page ahora muestra el comentario real aprobado
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->has('reviews', 1)
                ->where('reviews.0.name', 'Doña Mari')
                ->where('reviews.0.rating', 5)
                ->where('reviews.0.comment', 'Puntualidad y garrafones bien sellados.')
                ->where('reviewsMeta.count', 1)
                ->where('reviewsMeta.average', 5)
            );

        // Admin lo vuelve a ocultar del landing
        $this->actingAs($this->admin)
            ->patch(route('admin.reviews.toggle-landing', $review->id))
            ->assertRedirect();

        $this->assertFalse($review->fresh()->show_on_landing);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->has('reviews', 0)
            );
    }
}
