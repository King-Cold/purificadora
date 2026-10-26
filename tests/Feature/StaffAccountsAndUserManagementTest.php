<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffAccountsAndUserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $repartidorUser;
    protected User $llenadorUser;
    protected User $clientUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        // 1. Cuenta única de Administrador
        $this->adminUser = User::factory()->create([
            'name' => 'Administrador',
            'email' => 'admin@purificadora.com',
            'status' => 'active',
        ]);
        $this->adminUser->assignRole('Administrador');

        // 2. Cuenta única de Repartidor (identificada solo por su puesto)
        $this->repartidorUser = User::factory()->create([
            'name' => 'Repartidor',
            'email' => 'repartidor@purificadora.com',
            'status' => 'active',
        ]);
        $this->repartidorUser->assignRole('Repartidor');

        // 3. Cuenta única de Operador Planta (identificada como Llenador)
        $this->llenadorUser = User::factory()->create([
            'name' => 'Llenador',
            'email' => 'planta@purificadora.com',
            'status' => 'active',
        ]);
        $this->llenadorUser->assignRole('Operador Planta');

        // 4. Cuenta de Cliente
        $this->clientUser = User::factory()->create([
            'name' => 'María Pech',
            'email' => 'maria@example.com',
            'status' => 'active',
        ]);
        $this->clientUser->assignRole('Cliente');
    }

    /**
     * 1. La creación de usuarios desde el módulo administrativo está deshabilitada (GET y POST bloqueados con 403).
     */
    public function test_user_creation_is_disabled_and_forbidden(): void
    {
        // GET create form returns 403
        $responseGet = $this->actingAs($this->adminUser)
            ->get(route('admin.users.create'));
        $responseGet->assertForbidden();

        // POST store returns 403
        $responsePost = $this->actingAs($this->adminUser)
            ->post(route('admin.users.store'), [
                'name' => 'Otro Repartidor',
                'email' => 'otro@purificadora.com',
                'role' => 'Repartidor',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);
        $responsePost->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'otro@purificadora.com']);
    }

    /**
     * 2. No se permite modificar roles mediante peticiones HTTP; los roles son permanentes.
     */
    public function test_roles_cannot_be_modified_via_http_requests(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->put(route('admin.users.update', $this->repartidorUser->id), [
                'email' => 'repartidor@purificadora.com',
                'role' => 'Administrador', // Intento de cambiar rol
            ]);

        $response->assertForbidden();

        $this->repartidorUser->refresh();
        $this->assertTrue($this->repartidorUser->hasRole('Repartidor'));
        $this->assertFalse($this->repartidorUser->hasRole('Administrador'));
    }

    /**
     * 3. Las cuentas de Repartidor y Operador Planta conservan fijamente sus nombres de puesto: "Repartidor" y "Llenador".
     */
    public function test_staff_accounts_retain_fixed_position_titles_without_personal_names(): void
    {
        // Intento de asignar nombre personal al Repartidor
        $responseRepartidor = $this->actingAs($this->adminUser)
            ->put(route('admin.users.update', $this->repartidorUser->id), [
                'name' => 'Carlos Conductor Gómez',
                'email' => 'nuevo.repartidor@purificadora.com',
            ]);

        $responseRepartidor->assertRedirect(route('admin.users.show', $this->repartidorUser->id));
        $this->repartidorUser->refresh();
        $this->assertEquals('Repartidor', $this->repartidorUser->name);
        $this->assertEquals('nuevo.repartidor@purificadora.com', $this->repartidorUser->email);

        // Intento de asignar nombre personal al Llenador
        $responseLlenador = $this->actingAs($this->adminUser)
            ->put(route('admin.users.update', $this->llenadorUser->id), [
                'name' => 'Ignacio Operador Ruiz',
                'email' => 'nuevo.planta@purificadora.com',
            ]);

        $responseLlenador->assertRedirect(route('admin.users.show', $this->llenadorUser->id));
        $this->llenadorUser->refresh();
        $this->assertEquals('Llenador', $this->llenadorUser->name);
        $this->assertEquals('nuevo.planta@purificadora.com', $this->llenadorUser->email);
    }

    /**
     * 4. Los trabajadores no pueden eliminar sus propias cuentas.
     */
    public function test_workers_cannot_delete_their_own_accounts(): void
    {
        // 1. Admin no puede eliminar su cuenta
        $responseAdmin = $this->actingAs($this->adminUser)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);
        $responseAdmin->assertSessionHas('error', 'Las cuentas de personal no pueden ser eliminadas.');
        $this->assertDatabaseHas('users', ['id' => $this->adminUser->id]);

        // 2. Repartidor no puede eliminar su cuenta
        $responseRepartidor = $this->actingAs($this->repartidorUser)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);
        $responseRepartidor->assertSessionHas('error', 'Las cuentas de personal no pueden ser eliminadas.');
        $this->assertDatabaseHas('users', ['id' => $this->repartidorUser->id]);

        // 3. Llenador no puede eliminar su cuenta
        $responseLlenador = $this->actingAs($this->llenadorUser)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);
        $responseLlenador->assertSessionHas('error', 'Las cuentas de personal no pueden ser eliminadas.');
        $this->assertDatabaseHas('users', ['id' => $this->llenadorUser->id]);
    }

    /**
     * 5. El formulario de edición de usuarios no expone selectores ni asignación de roles.
     */
    public function test_user_edit_view_does_not_contain_role_assignment_selectors(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.users.edit', $this->repartidorUser->id));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Users/Edit')
            ->where('user.role', 'Repartidor')
            ->where('user.is_position_fixed', true)
            ->missing('assignableRoles')
            ->missing('canChangeRole')
            ->missing('roleLockReason')
        );
    }

    /**
     * 6. El módulo Administrador -> Clientes conserva la creación de clientes independiente.
     */
    public function test_customer_creation_remains_fully_functional(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.customers.store'), [
                'name' => 'Cliente Nuevo Independiente',
                'nickname' => 'Don Chencho',
                'fixed_bottles_count' => 5,
                'exchange_bottles_count' => 2,
            ]);

        $createdCustomer = Customer::where('nickname', 'Don Chencho')->first();
        $this->assertNotNull($createdCustomer);
        $this->assertEquals('Cliente Nuevo Independiente', $createdCustomer->name);
        $response->assertRedirect(route('admin.customers.show', $createdCustomer->id));
    }

    /**
     * 7. El registro público de clientes sigue funcionando y asigna el rol Cliente automáticamente.
     */
    public function test_public_client_registration_assigns_cliente_role(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Nuevo Cliente Web',
            'email' => 'cliente.web@example.com',
            'phone' => '9991234567',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $registeredUser = User::where('email', 'cliente.web@example.com')->first();
        $this->assertNotNull($registeredUser);
        $this->assertTrue($registeredUser->hasRole('Cliente'));
    }

    /**
     * 8. El administrador puede actualizar los botellones de 20L de un usuario con perfil de cliente.
     */
    public function test_admin_can_update_bottles_for_client_user(): void
    {
        $customer = Customer::create([
            'user_id' => $this->clientUser->id,
            'name' => $this->clientUser->name,
            'phone' => '9991234567',
            'email' => $this->clientUser->email,
            'customer_type' => 'particular',
            'price_tier' => 'general',
            'fixed_bottles_count' => 1,
            'exchange_bottles_count' => 1,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->put(route('admin.users.update', $this->clientUser->id), [
                'name' => 'María Pech Actualizada',
                'email' => 'maria.actualizada@example.com',
                'fixed_bottles_count' => 7,
                'exchange_bottles_count' => 3,
            ]);

        $response->assertRedirect(route('admin.users.show', $this->clientUser->id));
        $customer->refresh();
        $this->assertEquals(7, $customer->fixed_bottles_count);
        $this->assertEquals(3, $customer->exchange_bottles_count);
        $this->assertEquals(10, $customer->total_bottles);
    }
}

