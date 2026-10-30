<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use App\Models\Product;

uses(RefreshDatabase::class);

test('staff cannot create products', function () {
    $user = User::factory()->create([
        'role' => 'staff',
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/products', [
        'sku' => 'STAFF-001',
        'name' => 'Staff Pen',
        'price' => 15,
        'stock_quantity' => 10,
    ]);

    $response->assertForbidden();

    $this->assertDatabaseMissing('products', [
        'sku' => 'STAFF-001',
    ]);
});

test('staff can view products', function () {
    $product = Product::create([
        'sku' => 'VIEW-001',
        'name' => 'Notebook',
        'price' => 99,
        'stock_quantity' => 10,
    ]);

    $user = User::factory()->create([
        'role' => 'staff',
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/products')
        ->assertOk()
        ->assertJsonPath('data.0.id', $product->id);

    $this->getJson("/api/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $product->id);
});

test('staff cannot update or delete products', function () {
    $product = Product::create([
        'sku' => 'PROTECTED-001',
        'name' => 'Notebook',
        'price' => 99,
        'stock_quantity' => 10,
    ]);

    $user = User::factory()->create([
        'role' => 'staff',
    ]);

    Sanctum::actingAs($user);

    $this->patchJson("/api/products/{$product->id}", [
        'price' => 1,
    ])->assertForbidden();

    $this->deleteJson("/api/products/{$product->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'sku' => 'PROTECTED-001',
        'name' => 'Notebook',
        'price' => 99,
        'stock_quantity' => 10,
    ]);
});