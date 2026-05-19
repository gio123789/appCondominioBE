<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_and_lists_department_messages(): void
    {
        $storeResponse = $this->postJson('/api/chat/messages', [
            'remitente' => 'Residente 101',
            'departamento' => 101,
            'mensaje' => 'Pago realizado.',
        ]);

        $storeResponse
            ->assertCreated()
            ->assertJsonPath('data.remitente', 'Residente 101')
            ->assertJsonPath('data.departamento', 101);

        $listResponse = $this->getJson('/api/chat/messages?departamento=101');

        $listResponse
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.mensaje', 'Pago realizado.');
    }
}
