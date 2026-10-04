<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_list_payments(): void
    {
        $reservation = $this->createReservation('300.00');

        $this->getJson("/api/reservations/{$reservation->id}/payments")
            ->assertStatus(401);
    }

    public function test_lists_payments_with_total_paid_and_remaining_balance(): void
    {
        $this->authenticate();
        $reservation = $this->createReservation('300.00');
        $first = $this->createPayment($reservation, '100.00');
        $second = $this->createPayment($reservation, '50.00');

        $response = $this->getJson("/api/reservations/{$reservation->id}/payments");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'payments')
            ->assertJsonPath('payments.0.id', $second->id)
            ->assertJsonPath('payments.1.id', $first->id)
            ->assertJsonPath('total_paid', '150.00')
            ->assertJsonPath('remaining_balance', '150.00')
            ->assertJsonStructure([
                'payments' => [
                    '*' => ['id', 'reservation_id', 'method', 'amount', 'paid_at', 'created_at'],
                ],
                'total_paid',
                'remaining_balance',
            ]);
    }

    public function test_registers_a_valid_payment(): void
    {
        $this->authenticate();
        $reservation = $this->createReservation('300.00');

        $response = $this->postJson("/api/reservations/{$reservation->id}/payments", [
            'method' => 'pix',
            'amount' => 100,
            'paid_at' => '2026-10-04 10:00:00',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Pagamento registrado com sucesso.')
            ->assertJsonPath('payment.reservation_id', $reservation->id)
            ->assertJsonPath('payment.method', 'pix')
            ->assertJsonPath('total_paid', '100.00')
            ->assertJsonPath('remaining_balance', '200.00');

        $this->assertSame('100.00', $this->decimal($response->json('payment.amount')));

        $this->assertDatabaseHas('payments', [
            'reservation_id' => $reservation->id,
            'method' => 'pix',
        ]);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_rejects_zero_amount(): void
    {
        $this->authenticate();
        $reservation = $this->createReservation('300.00');

        $this->postJson("/api/reservations/{$reservation->id}/payments", [
            'method' => 'pix',
            'amount' => 0,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_rejects_negative_amount(): void
    {
        $this->authenticate();
        $reservation = $this->createReservation('300.00');

        $this->postJson("/api/reservations/{$reservation->id}/payments", [
            'method' => 'pix',
            'amount' => -10,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_rejects_payment_greater_than_remaining_balance(): void
    {
        $this->authenticate();
        $reservation = $this->createReservation('300.00');
        $this->createPayment($reservation, '100.00');

        $this->postJson("/api/reservations/{$reservation->id}/payments", [
            'method' => 'pix',
            'amount' => 250,
        ])
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'O pagamento de 250.00 excede o saldo restante da reserva (200.00).'
            );

        $this->assertDatabaseCount('payments', 1);
    }

    public function test_shows_payment_belonging_to_reservation(): void
    {
        $this->authenticate();
        $reservation = $this->createReservation('300.00');
        $payment = $this->createPayment($reservation, '100.00');

        $response = $this->getJson("/api/reservations/{$reservation->id}/payments/{$payment->id}");

        $response->assertStatus(200)
            ->assertJsonPath('payment.id', $payment->id)
            ->assertJsonPath('payment.reservation_id', $reservation->id)
            ->assertJsonPath('payment.method', 'pix');

        $this->assertSame('100.00', $this->decimal($response->json('payment.amount')));
    }

    public function test_returns_404_when_showing_payment_from_another_reservation(): void
    {
        $this->authenticate();
        $reservation = $this->createReservation('300.00');
        $other = $this->createReservation('500.00', 2);
        $otherPayment = $this->createPayment($other, '100.00');

        $this->getJson("/api/reservations/{$reservation->id}/payments/{$otherPayment->id}")
            ->assertStatus(404)
            ->assertExactJson(['message' => 'Pagamento não encontrado.']);
    }

    public function test_deletes_payment_belonging_to_reservation(): void
    {
        $this->authenticate();
        $reservation = $this->createReservation('300.00');
        $payment = $this->createPayment($reservation, '100.00');

        $this->deleteJson("/api/reservations/{$reservation->id}/payments/{$payment->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('payments', ['id' => $payment->id]);
    }

    public function test_returns_404_when_deleting_payment_from_another_reservation(): void
    {
        $this->authenticate();
        $reservation = $this->createReservation('300.00');
        $other = $this->createReservation('500.00', 2);
        $otherPayment = $this->createPayment($other, '100.00');

        $this->deleteJson("/api/reservations/{$reservation->id}/payments/{$otherPayment->id}")
            ->assertStatus(404)
            ->assertExactJson(['message' => 'Pagamento não encontrado.']);

        $this->assertDatabaseHas('payments', ['id' => $otherPayment->id]);
    }

    public function test_rejects_payment_above_total_when_previous_payments_exist(): void
    {
        $this->authenticate();
        $reservation = $this->createReservation('300.00');
        $this->createPayment($reservation, '250.00');

        $this->postJson("/api/reservations/{$reservation->id}/payments", [
            'method' => 'pix',
            'amount' => 100,
        ])->assertStatus(422);

        $this->assertDatabaseCount('payments', 1);
    }

    private function authenticate(): User
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum');

        return $user;
    }

    private function createReservation(string $total, int $externalId = 1): Reservation
    {
        $hotel = Hotel::forceCreate([
            'external_id' => $externalId,
            'name' => "Hotel Teste {$externalId}",
        ]);

        $room = Room::forceCreate([
            'hotel_id' => $hotel->id,
            'external_id' => $externalId,
            'name' => "Quarto Teste {$externalId}",
            'quantity' => 1,
        ]);

        return Reservation::forceCreate([
            'external_id' => $externalId,
            'hotel_id' => $hotel->id,
            'room_id' => $room->id,
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-13',
            'status' => 'confirmed',
            'subtotal' => $total,
            'discount' => '0.00',
            'fees' => '0.00',
            'total' => $total,
        ]);
    }

    private function createPayment(Reservation $reservation, string $amount): Payment
    {
        return Payment::forceCreate([
            'reservation_id' => $reservation->id,
            'method' => 'pix',
            'amount' => $amount,
            'paid_at' => null,
        ]);
    }

    private function decimal(string|int|float $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
