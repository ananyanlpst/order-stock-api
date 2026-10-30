<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;

uses(RefreshDatabase::class);

test('guests cannot view products', function () {
    $response = $this->getJson('/api/products');

    $response->assertUnauthorized();
});

test('cannot login with an incorrect password', function () {
    $user = User::factory()->create([
        'password' => 'correct-test-password',
    ]);

    $response = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'wrong-test-password',
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email'])
        ->assertJsonMissingPath('token');

    $this->assertDatabaseCount('personal_access_tokens', 0);
});

test('can login with the correct password', function () {
    $user = User::factory()->create([
        'password' => 'correct-test-password',
    ]);

    $response = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'correct-test-password',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonStructure(['token'])
        ->assertJsonMissingPath('user.password');

    expect($response->json('token'))
        ->toBeString()
        ->not->toBeEmpty();

    $this->assertDatabaseHas('personal_access_tokens', [
        'tokenable_id' => $user->id,
        'tokenable_type' => $user->getMorphClass(),
        'name' => 'order-stock-api',
    ]);
});

test('logout revokes the current token', function () {
    $user = User::factory()->create();

    $token = $user->createToken('test-token')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/logout')
        ->assertOk()
        ->assertJsonPath('message', 'Logged out successfully.');

    $this->assertDatabaseCount('personal_access_tokens', 0);

    // ล้างผู้ใช้ที่ถูกจำไว้ระหว่างคำขอใน Test
    app('auth')->forgetGuards();

    $this->withToken($token)
        ->getJson('/api/products')
        ->assertUnauthorized();
});