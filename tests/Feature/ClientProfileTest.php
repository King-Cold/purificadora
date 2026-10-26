<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClientProfileTest extends TestCase
{
    use RefreshDatabase;

    protected User $clientUser;
    protected Customer $customer;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        // 1. Cliente con cuenta y perfil Customer vinculado
        $this->clientUser = User::factory()->create([
            'name' => 'José Antonio Pérez López',
            'email' => 'jose.perez@example.com',
            'status' => 'active',
        ]);
        $this->clientUser->assignRole('Cliente');

        $this->customer = Customer::create([
            'user_id' => $this->clientUser->id,
            'name' => $this->clientUser->name,
            'nickname' => null,
            'phone' => '9991234567',
            'email' => $this->clientUser->email,
            'customer_type' => 'particular',
            'price_tier' => 'general',
            'fixed_bottles_count' => 3,
            'exchange_bottles_count' => 2,
            'status' => 'active',
        ]);

        // 2. Administrador para pruebas de compatibilidad
        $this->adminUser = User::factory()->create([
            'name' => 'Administrador General',
            'email' => 'admin@puritronic.com',
            'status' => 'active',
        ]);
        $this->adminUser->assignRole('Administrador');
    }

    /**
     * 1. Cliente autenticado puede consultar su perfil y ver código CLI-XXXX y botellones.
     */
    public function test_authenticated_client_can_view_profile_with_code_and_bottles(): void
    {
        $response = $this->actingAs($this->clientUser)
            ->get(route('profile.edit'));

        $response->assertOk();
        $response->assertInertia(fn ($page) =>
            $page->component('Profile/Edit')
                ->where('isClient', true)
                ->where('clientData.customer.code', $this->customer->code)
                ->where('clientData.customer.name', 'José Antonio Pérez López')
                ->where('clientData.customer.phone', '9991234567')
                ->where('clientData.customer.fixed_bottles_count', 3)
                ->where('clientData.customer.exchange_bottles_count', 2)
                ->where('clientData.customer.total_bottles', 5)
        );
    }

    /**
     * 2. El cliente puede agregar un apodo comunitario opcional.
     */
    public function test_client_can_add_nickname(): void
    {
        $response = $this->actingAs($this->clientUser)
            ->patch(route('profile.update'), [
                'name' => 'José Antonio Pérez López',
                'email' => 'jose.perez@example.com',
                'phone' => '9991234567',
                'nickname' => 'Don Pepe',
            ]);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('success');

        $this->assertEquals('Don Pepe', $this->customer->fresh()->nickname);
    }

    /**
     * 3. El cliente puede editar su apodo existente.
     */
    public function test_client_can_edit_existing_nickname(): void
    {
        $this->customer->update(['nickname' => 'Don Pepe']);

        $response = $this->actingAs($this->clientUser)
            ->patch(route('profile.update'), [
                'name' => 'José Antonio Pérez López',
                'email' => 'jose.perez@example.com',
                'phone' => '9991234567',
                'nickname' => 'Pepito',
            ]);

        $response->assertRedirect(route('profile.edit'));
        $this->assertEquals('Pepito', $this->customer->fresh()->nickname);
    }

    /**
     * 4. El cliente puede eliminar su apodo dejándolo vacío (se guarda como null).
     */
    public function test_client_can_remove_nickname_leaving_it_null(): void
    {
        $this->customer->update(['nickname' => 'Don Pepe']);

        $response = $this->actingAs($this->clientUser)
            ->patch(route('profile.update'), [
                'name' => 'José Antonio Pérez López',
                'email' => 'jose.perez@example.com',
                'phone' => '9991234567',
                'nickname' => '', // vacío
            ]);

        $response->assertRedirect(route('profile.edit'));
        $this->assertNull($this->customer->fresh()->nickname);
    }

    /**
     * 5. El apodo no necesita ser único (dos clientes pueden compartir el mismo apodo).
     */
    public function test_nickname_does_not_need_to_be_unique(): void
    {
        // Otro cliente con apodo "Don Pepe"
        $otherUser = User::factory()->create();
        $otherUser->assignRole('Cliente');
        Customer::create([
            'user_id' => $otherUser->id,
            'name' => 'José Morales',
            'nickname' => 'Don Pepe',
            'phone' => '9990001122',
            'email' => $otherUser->email,
            'customer_type' => 'particular',
            'price_tier' => 'general',
            'fixed_bottles_count' => 0,
            'exchange_bottles_count' => 0,
            'status' => 'active',
        ]);

        // Nuestro cliente también usa "Don Pepe"
        $response = $this->actingAs($this->clientUser)
            ->patch(route('profile.update'), [
                'name' => 'José Antonio Pérez López',
                'email' => 'jose.perez@example.com',
                'phone' => '9991234567',
                'nickname' => 'Don Pepe',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertEquals('Don Pepe', $this->customer->fresh()->nickname);
    }

    /**
     * 6. El cliente puede actualizar su nombre y teléfono, sincronizándose en users y customers.
     */
    public function test_client_can_update_name_and_phone_synced_in_both_tables(): void
    {
        $response = $this->actingAs($this->clientUser)
            ->patch(route('profile.update'), [
                'name' => 'José Pérez Modificado',
                'email' => 'jose.perez@example.com',
                'phone' => '9999998888',
                'nickname' => 'Don Pepe',
            ]);

        $response->assertRedirect(route('profile.edit'));

        // Verifica users
        $this->clientUser->refresh();
        $this->assertEquals('José Pérez Modificado', $this->clientUser->name);

        // Verifica customers
        $this->customer->refresh();
        $this->assertEquals('José Pérez Modificado', $this->customer->name);
        $this->assertEquals('9999998888', $this->customer->phone);
        $this->assertEquals('Don Pepe', $this->customer->nickname);
    }

    /**
     * 7. No se duplican registros Customer al editar el perfil.
     */
    public function test_customer_records_are_not_duplicated_on_edit(): void
    {
        $initialCount = Customer::count();

        $this->actingAs($this->clientUser)
            ->patch(route('profile.update'), [
                'name' => 'José Actualizado',
                'email' => 'jose.perez@example.com',
                'phone' => '9991112233',
            ]);

        $this->assertEquals($initialCount, Customer::count());
        $this->assertEquals(1, Customer::where('user_id', $this->clientUser->id)->count());
    }

    /**
     * 8. No se modifica customer_code, botellones, estado ni roles si se intentan enviar campos sensibles.
     */
    public function test_sensitive_fields_cannot_be_manipulated_via_profile_update(): void
    {
        $originalCode = $this->customer->code;

        $response = $this->actingAs($this->clientUser)
            ->patch(route('profile.update'), [
                'name' => 'José Pérez',
                'email' => 'jose.perez@example.com',
                'phone' => '9991234567',
                'code' => 'CLI-HACK',
                'customer_code' => 'CLI-HACK',
                'fixed_bottles_count' => 999,
                'exchange_bottles_count' => 999,
                'status' => 'inactive',
                'role' => 'Administrador',
                'role_id' => 1,
                'credit_limit' => 50000,
            ]);

        $response->assertRedirect(route('profile.edit'));

        $this->customer->refresh();
        $this->assertEquals($originalCode, $this->customer->code);
        $this->assertEquals(3, $this->customer->fixed_bottles_count);
        $this->assertEquals(2, $this->customer->exchange_bottles_count);
        $this->assertEquals('active', $this->clientUser->fresh()->status);
        $this->assertTrue($this->clientUser->fresh()->hasRole('Cliente'));
        $this->assertFalse($this->clientUser->fresh()->hasRole('Administrador'));
    }

    /**
     * 9. Aislamiento: El cliente no puede modificar perfiles ajenos mediante HTTP manipulado.
     */
    public function test_client_cannot_modify_other_profiles(): void
    {
        $victimUser = User::factory()->create(['name' => 'Víctima']);
        $victimCustomer = Customer::create([
            'user_id' => $victimUser->id,
            'name' => 'Víctima',
            'phone' => '9995554433',
            'email' => $victimUser->email,
            'customer_type' => 'particular',
            'price_tier' => 'general',
            'fixed_bottles_count' => 0,
            'exchange_bottles_count' => 0,
            'status' => 'active',
        ]);

        // Atacante envía user_id o customer_id en la petición
        $this->actingAs($this->clientUser)
            ->patch(route('profile.update'), [
                'user_id' => $victimUser->id,
                'customer_id' => $victimCustomer->id,
                'name' => 'Nombre Hackeado',
                'email' => $this->clientUser->email,
                'phone' => '9990000000',
            ]);

        // La víctima no fue alterada
        $this->assertEquals('Víctima', $victimUser->fresh()->name);
        $this->assertEquals('Víctima', $victimCustomer->fresh()->name);

        // Solo se modificó el usuario autenticado
        $this->assertEquals('Nombre Hackeado', $this->clientUser->fresh()->name);
    }

    /**
     * 10. Se muestra el domicilio único y zona de reparto cuando el cliente tiene dirección.
     */
    public function test_profile_displays_single_address_and_delivery_zone(): void
    {
        app(\App\Services\CustomerAddressService::class)->createAddress($this->customer, [
            'municipality_type' => 'hocaba',
            'delivery_zone' => 'north',
            'street_and_number' => 'Calle 20 #105',
            'reference' => 'Casa azul frente al parque',
        ]);

        $response = $this->actingAs($this->clientUser)
            ->get(route('profile.edit'));

        $response->assertOk();
        $response->assertInertia(fn ($page) =>
            $page->component('Profile/Edit')
                ->where('clientData.address.has_address', true)
                ->where('clientData.address.street_and_number', 'Calle 20 #105')
                ->where('clientData.address.municipality', 'Hocabá')
                ->where('clientData.address.delivery_zone', 'north')
                ->where('clientData.address.zone_label', 'Zona Norte')
                ->where('clientData.address.reference', 'Casa azul frente al parque')
        );
    }

    /**
     * 11. El perfil funciona correctamente cuando el cliente NO tiene domicilio registrado.
     */
    public function test_profile_works_when_client_has_no_address(): void
    {
        $response = $this->actingAs($this->clientUser)
            ->get(route('profile.edit'));

        $response->assertOk();
        $response->assertInertia(fn ($page) =>
            $page->component('Profile/Edit')
                ->where('clientData.address.has_address', false)
        );
    }

    /**
     * 12. Se conserva la actualización segura del correo electrónico (desmarcando email_verified_at).
     */
    public function test_secure_email_update_unverifies_email(): void
    {
        $this->clientUser->forceFill(['email_verified_at' => now()])->save();

        $response = $this->actingAs($this->clientUser)
            ->patch(route('profile.update'), [
                'name' => 'José Pérez',
                'email' => 'nuevo.correo@example.com',
                'phone' => '9991234567',
            ]);

        $response->assertRedirect(route('profile.edit'));
        $this->clientUser->refresh();
        $this->assertEquals('nuevo.correo@example.com', $this->clientUser->email);
        $this->assertNull($this->clientUser->email_verified_at);
        $this->assertEquals('nuevo.correo@example.com', $this->customer->fresh()->email);
    }

    /**
     * 13. Se conserva la actualización de contraseña mediante mecanismo existente.
     */
    public function test_client_can_update_password(): void
    {
        $this->clientUser->update([
            'password' => Hash::make('password_antiguo'),
        ]);

        $response = $this->actingAs($this->clientUser)
            ->put(route('password.update'), [
                'current_password' => 'password_antiguo',
                'password' => 'NuevoPasswordSeguro123!',
                'password_confirmation' => 'NuevoPasswordSeguro123!',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('NuevoPasswordSeguro123!', $this->clientUser->fresh()->password));
    }

    /**
     * 14. Los usuarios con roles administrativos conservan su perfil estándar.
     */
    public function test_admin_user_preserves_standard_profile_experience(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('profile.edit'));

        $response->assertOk();
        $response->assertInertia(fn ($page) =>
            $page->component('Profile/Edit')
                ->where('isClient', false)
                ->where('clientData', null)
        );

        // Admin puede actualizar su nombre y correo sin enviar campos de cliente
        $responseUpdate = $this->actingAs($this->adminUser)
            ->patch(route('profile.update'), [
                'name' => 'Admin Actualizado',
                'email' => 'admin.nuevo@puritronic.com',
            ]);

        $responseUpdate->assertRedirect(route('profile.edit'));
        $this->assertEquals('Admin Actualizado', $this->adminUser->fresh()->name);
    }

    /**
     * 15. Manejo seguro de usuario con rol Cliente sin registro Customer vinculado previamente.
     */
    public function test_client_user_without_customer_record_is_handled_gracefully(): void
    {
        $orphanUser = User::factory()->create([
            'name' => 'Cliente Huérfano',
            'email' => 'huerfano@example.com',
        ]);
        $orphanUser->assignRole('Cliente');

        // Confirmamos que no tiene Customer
        $this->assertNull($orphanUser->customer);

        // Al acceder a su perfil, no arroja error 500 y resuelve/crea el perfil
        $response = $this->actingAs($orphanUser)
            ->get(route('profile.edit'));

        $response->assertOk();
        $this->assertNotNull($orphanUser->fresh()->customer);
        $this->assertEquals('CLI-', substr($orphanUser->fresh()->customer->code, 0, 4));
    }

    /**
     * 16. El cliente autenticado puede actualizar la cantidad de botellones que tiene.
     */
    public function test_client_can_update_their_own_bottle_counts(): void
    {
        $response = $this->actingAs($this->clientUser)
            ->put(route('cliente.bottles.update'), [
                'fixed_bottles_count' => 6,
                'exchange_bottles_count' => 4,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->customer->refresh();
        $this->assertEquals(6, $this->customer->fixed_bottles_count);
        $this->assertEquals(4, $this->customer->exchange_bottles_count);
        $this->assertEquals(10, $this->customer->total_bottles);
    }

    /**
     * 17. La actualización de botellones valida que sean enteros no negativos.
     */
    public function test_client_bottle_update_validates_non_negative_integers(): void
    {
        $response = $this->actingAs($this->clientUser)
            ->put(route('cliente.bottles.update'), [
                'fixed_bottles_count' => -2,
                'exchange_bottles_count' => 'invalido',
            ]);

        $response->assertSessionHasErrors(['fixed_bottles_count', 'exchange_bottles_count']);
    }

    /**
     * 18. Usuario no autenticado no puede actualizar botellones.
     */
    public function test_unauthenticated_user_cannot_update_bottles(): void
    {
        $response = $this->put(route('cliente.bottles.update'), [
            'fixed_bottles_count' => 3,
            'exchange_bottles_count' => 2,
        ]);

        $response->assertRedirect(route('login'));
    }
}

