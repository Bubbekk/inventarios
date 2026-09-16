<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('una cuenta desactivada no puede iniciar sesión con credenciales válidas', function () {
    $usuario = User::factory()->create([
        'password' => Hash::make('clave-de-prueba'),
        'activo' => false,
    ]);

    $respuesta = $this->post(route('login.store'), [
        'email' => $usuario->email,
        'password' => 'clave-de-prueba',
    ]);

    $respuesta->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('una cuenta activa inicia sesión con las mismas credenciales', function () {
    $usuario = User::factory()->create([
        'password' => Hash::make('clave-de-prueba'),
        'activo' => true,
    ]);

    $this->post(route('login.store'), [
        'email' => $usuario->email,
        'password' => 'clave-de-prueba',
    ]);

    $this->assertAuthenticatedAs($usuario);
});

test('el mensaje de una cuenta desactivada no revela que el correo existe', function () {
    $usuario = User::factory()->create([
        'password' => Hash::make('clave-de-prueba'),
        'activo' => false,
    ]);

    $this->post(route('login.store'), [
        'email' => $usuario->email,
        'password' => 'clave-de-prueba',
    ])->assertSessionHasErrors(['email' => __('auth.failed')]);

    $this->flushSession();

    $this->post(route('login.store'), [
        'email' => 'nadie@dsp.cl',
        'password' => 'clave-de-prueba',
    ])->assertSessionHasErrors(['email' => __('auth.failed')]);
});

test('la sesión abierta se cierra cuando la cuenta pasa a desactivada', function () {
    $usuario = User::factory()->create(['activo' => true]);

    $this->actingAs($usuario);
    $this->get(route('inicio'))->assertOk();

    $usuario->forceFill(['activo' => false])->save();

    $this->get(route('inicio'))->assertRedirect(route('login'));
    $this->assertGuest();
});
