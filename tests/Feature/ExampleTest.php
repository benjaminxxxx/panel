<?php

use App\Models\User;

test('la raíz lleva al login o al panel', function () {
    $this->get('/')->assertRedirect('/login');
    $this->actingAs(User::factory()->create())->get('/')->assertRedirect('/dashboard');
});

test('solo el proveedor ve los locales, versiones y catálogo', function () {
    $duena = User::factory()->create();
    $this->actingAs($duena)->get(route('clientes.index'))->assertForbidden();
    $this->actingAs($duena)->get(route('dashboard'))->assertOk()->assertDontSee('Locales (proveedor)');

    $proveedor = User::factory()->admin()->create();
    $this->actingAs($proveedor)->get(route('clientes.index'))->assertOk();
    $this->actingAs($proveedor)->get(route('dashboard'))->assertOk()->assertSee('Locales (proveedor)');
});
