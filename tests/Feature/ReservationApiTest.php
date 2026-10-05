<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReservationApiTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private Hotel $otherHotel;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        // As rotas de reserva exigem auth:sanctum; autenticar aqui cobre toda a classe.
        Sanctum::actingAs(User::factory()->create());

        $this->hotel = Hotel::create(['external_id' => 1001, 'name' => 'Hotel Teste A']);
        $this->otherHotel = Hotel::create(['external_id' => 1002, 'name' => 'Hotel Teste B']);

        $this->room = Room::create([
            'hotel_id' => $this->hotel->id,
            'external_id' => 1,
            'name' => 'Quarto Teste',
            'quantity' => 1,
        ]);
    }

    public function test_creates_a_valid_reservation(): void
    {
        $response = $this->postJson('/api/reservations', $this->payload());

        $response
            ->assertCreated()
            ->assertJsonPath('data.hotel_id', $this->hotel->id)
            ->assertJsonPath('data.room_id', $this->room->id)
            ->assertJsonPath('data.check_in', '2027-01-10')
            ->assertJsonPath('data.check_out', '2027-01-13')
            ->assertJsonPath('data.status', 'confirmed');
    }

    public function test_calculates_subtotal_and_total_on_the_backend(): void
    {
        $response = $this->postJson('/api/reservations', $this->payload());

        $response
            ->assertCreated()
            ->assertJsonPath('data.subtotal', '650.00')
            ->assertJsonPath('data.discount', '0.00')
            ->assertJsonPath('data.fees', '0.00')
            ->assertJsonPath('data.total', '650.00');
    }

    public function test_persists_reservation_guest_and_dailies(): void
    {
        $response = $this->postJson('/api/reservations', $this->payload());

        $response->assertCreated();

        $reservationId = $response->json('data.id');

        $this->assertDatabaseCount('reservations', 1);
        $this->assertDatabaseHas('reservations', [
            'id' => $reservationId,
            'hotel_id' => $this->hotel->id,
            'room_id' => $this->room->id,
            'status' => 'confirmed',
            'subtotal' => '650.00',
            'total' => '650.00',
        ]);

        $reservation = Reservation::findOrFail($reservationId);

        $this->assertSame('2027-01-10', $reservation->check_in->toDateString());
        $this->assertSame('2027-01-13', $reservation->check_out->toDateString());

        $this->assertDatabaseCount('guests', 1);
        $this->assertDatabaseHas('guests', [
            'reservation_id' => $reservationId,
            'name' => 'Teste Automatizado',
            'phone' => '77999999999',
        ]);

        $this->assertDatabaseCount('reservation_dailies', 3);

        $dailies = $reservation->dailies()
            ->orderBy('date')
            ->get()
            ->map(fn ($daily) => [
            'date' => $daily->date->toDateString(),
            'amount' => $daily->amount,
        ])
        ->all();

        $this->assertSame([
            ['date' => '2027-01-10', 'amount' => '200.00'],
            ['date' => '2027-01-11', 'amount' => '200.00'],
            ['date' => '2027-01-12', 'amount' => '250.00'],
        ], $dailies);
    }

    public function test_rejects_room_that_belongs_to_another_hotel(): void
    {
        $response = $this->postJson('/api/reservations', $this->payload([
            'hotel_id' => $this->otherHotel->id,
        ]));

        $response
            ->assertStatus(422)
            ->assertJson([
                'message' => "O quarto {$this->room->id} não pertence ao hotel {$this->otherHotel->id}.",
            ]);

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_rejects_reservation_when_room_is_already_booked(): void
    {
        $this->postJson('/api/reservations', $this->payload())->assertCreated();

        $response = $this->postJson('/api/reservations', $this->payload());

        $response
            ->assertStatus(409)
            ->assertJson([
                'message' => "Não há disponibilidade para o quarto {$this->room->id} no período informado.",
            ]);

        $this->assertDatabaseCount('reservations', 1);
        $this->assertDatabaseCount('guests', 1);
        $this->assertDatabaseCount('reservation_dailies', 3);
    }

    public function test_rejects_reservation_with_a_missing_daily(): void
    {
        $response = $this->postJson('/api/reservations', $this->payload([
            'dailies' => [
                ['date' => '2027-01-10', 'amount' => 200],
                ['date' => '2027-01-11', 'amount' => 200],
            ],
        ]));

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['dailies']);

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_rejects_a_daily_on_the_checkout_date(): void
    {
        $response = $this->postJson('/api/reservations', $this->payload([
            'dailies' => [
                ['date' => '2027-01-10', 'amount' => 200],
                ['date' => '2027-01-11', 'amount' => 200],
                ['date' => '2027-01-13', 'amount' => 250],
            ],
        ]));

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['dailies.2.date']);

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_rejects_duplicated_daily_dates(): void
    {
        $response = $this->postJson('/api/reservations', $this->payload([
            'dailies' => [
                ['date' => '2027-01-10', 'amount' => 200],
                ['date' => '2027-01-10', 'amount' => 200],
                ['date' => '2027-01-12', 'amount' => 250],
            ],
        ]));

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['dailies.1.date']);

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_rejects_unauthenticated_request(): void
    {
        // Descarta os guards resolvidos para remover o usuário definido por
        // Sanctum::actingAs() no setUp, apenas neste teste.
        $this->app['auth']->forgetGuards();

        $response = $this->postJson('/api/reservations', $this->payload());

        $response->assertUnauthorized();

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_rejects_nonexistent_hotel(): void
    {
        $response = $this->postJson('/api/reservations', $this->payload([
            'hotel_id' => 999999,
        ]));

        $response->assertStatus(422);

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_rejects_nonexistent_room(): void
    {
        $response = $this->postJson('/api/reservations', $this->payload([
            'room_id' => 999999,
        ]));

        $response->assertStatus(422);

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_rejects_check_out_before_check_in(): void
    {
        $response = $this->postJson('/api/reservations', $this->payload([
            'check_in' => '2027-01-13',
            'check_out' => '2027-01-10',
        ]));

        $response->assertStatus(422);

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_rejects_check_out_equal_to_check_in(): void
    {
        $response = $this->postJson('/api/reservations', $this->payload([
            'check_in' => '2027-01-10',
            'check_out' => '2027-01-10',
        ]));

        $response->assertStatus(422);

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_allows_multiple_reservations_when_room_quantity_is_greater_than_one(): void
    {
        $room = Room::create([
            'hotel_id' => $this->hotel->id,
            'external_id' => 2,
            'name' => 'Quarto Duplo Teste',
            'quantity' => 2,
        ]);

        $this->postJson('/api/reservations', $this->payload(['room_id' => $room->id]))
            ->assertCreated();

        $this->postJson('/api/reservations', $this->payload(['room_id' => $room->id]))
            ->assertCreated();

        $this->assertDatabaseCount('reservations', 2);
        $this->assertSame(2, Reservation::where('room_id', $room->id)->count());
    }

    public function test_rejects_third_reservation_when_room_quantity_is_two(): void
    {
        $room = Room::create([
            'hotel_id' => $this->hotel->id,
            'external_id' => 2,
            'name' => 'Quarto Duplo Teste',
            'quantity' => 2,
        ]);

        $this->postJson('/api/reservations', $this->payload(['room_id' => $room->id]))
            ->assertCreated();

        $this->postJson('/api/reservations', $this->payload(['room_id' => $room->id]))
            ->assertCreated();

        $this->postJson('/api/reservations', $this->payload(['room_id' => $room->id]))
            ->assertStatus(409);

        $this->assertDatabaseCount('reservations', 2);
        $this->assertSame(2, Reservation::where('room_id', $room->id)->count());
    }

    public function test_allows_consecutive_reservations_because_check_out_is_exclusive(): void
    {
        $this->postJson('/api/reservations', $this->payload())
            ->assertCreated();

        $this->postJson('/api/reservations', $this->payload([
            'check_in' => '2027-01-13',
            'check_out' => '2027-01-16',
            'dailies' => [
                ['date' => '2027-01-13', 'amount' => 200],
                ['date' => '2027-01-14', 'amount' => 200],
                ['date' => '2027-01-15', 'amount' => 250],
            ],
        ]))->assertCreated();

        $this->assertDatabaseCount('reservations', 2);
        $this->assertSame(2, Reservation::where('room_id', $this->room->id)->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'hotel_id' => $this->hotel->id,
            'room_id' => $this->room->id,
            'check_in' => '2027-01-10',
            'check_out' => '2027-01-13',
            'guests' => [
                ['name' => 'Teste Automatizado', 'phone' => '77999999999'],
            ],
            'dailies' => [
                ['date' => '2027-01-10', 'amount' => 200],
                ['date' => '2027-01-11', 'amount' => 200],
                ['date' => '2027-01-12', 'amount' => 250],
            ],
        ], $overrides);
    }
}
