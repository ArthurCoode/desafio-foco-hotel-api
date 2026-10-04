<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Hotel;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Services\Reservation\PaymentExceedsReservationTotalException;
use App\Services\Reservation\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PaymentService();
    }

    public function test_registers_a_valid_payment(): void
    {
        $reservation = $this->createReservation('300.00');

        $payment = $this->service->register($reservation, 'pix', '100.00');

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertTrue($payment->exists);
        $this->assertSame($reservation->id, $payment->reservation_id);
        $this->assertSame('pix', $payment->method);
        $this->assertSame('100.00', $this->decimal($payment->fresh()->amount));
        $this->assertSame(1, $reservation->payments()->count());
    }

    public function test_calculates_total_paid(): void
    {
        $reservation = $this->createReservation('300.00');
        $this->createPayment($reservation, '100.00');
        $this->createPayment($reservation, '50.00');

        $this->assertSame('150.00', $this->service->getTotalPaid($reservation));
    }

    public function test_calculates_remaining_balance(): void
    {
        $reservation = $this->createReservation('300.00');
        $this->createPayment($reservation, '100.00');
        $this->createPayment($reservation, '50.00');

        $this->assertSame('150.00', $this->service->getRemainingBalance($reservation));
    }

    public function test_rejects_zero_amount(): void
    {
        $reservation = $this->createReservation('300.00');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('O valor do pagamento deve ser positivo.');

        $this->service->register($reservation, 'pix', 0);
    }

    public function test_rejects_negative_amount(): void
    {
        $reservation = $this->createReservation('300.00');

        $this->expectException(InvalidArgumentException::class);

        $this->service->register($reservation, 'pix', -10);
    }

    public function test_rejects_payment_greater_than_remaining_balance(): void
    {
        $reservation = $this->createReservation('300.00');
        $this->createPayment($reservation, '100.00');

        try {
            $this->service->register($reservation, 'pix', '250.00');

            $this->fail('Era esperada a PaymentExceedsReservationTotalException.');
        } catch (PaymentExceedsReservationTotalException $exception) {
            $this->assertStringContainsString('250.00', $exception->getMessage());
            $this->assertStringContainsString('200.00', $exception->getMessage());
        }
    }

    public function test_allows_successive_payments_until_exact_total(): void
    {
        $reservation = $this->createReservation('300.00');

        $this->service->register($reservation, 'pix', '100.00');
        $this->service->register($reservation, 'credit_card', '200.00');

        $this->assertSame('300.00', $this->service->getTotalPaid($reservation));
        $this->assertSame('0.00', $this->service->getRemainingBalance($reservation));
        $this->assertSame(2, $reservation->payments()->count());
    }

    public function test_does_not_create_payment_when_operation_is_rejected(): void
    {
        $reservation = $this->createReservation('300.00');
        $original = $this->createPayment($reservation, '100.00');

        try {
            $this->service->register($reservation, 'pix', '250.00');

            $this->fail('Era esperada a PaymentExceedsReservationTotalException.');
        } catch (PaymentExceedsReservationTotalException) {
            // Esperado.
        }

        $payments = $reservation->payments()->get();

        $this->assertCount(1, $payments);
        $this->assertSame($original->id, $payments->first()->id);
        $this->assertSame('100.00', $this->service->getTotalPaid($reservation));
    }

    /**
     * Cria Hotel, Room e Reservation reais. Se algum NOT NULL da sua migration
     * diferir dos campos abaixo, ajuste apenas este helper.
     */
    private function createReservation(string $total): Reservation
    {
        $hotel = Hotel::forceCreate([
            'external_id' => 1,
            'name' => 'Hotel Teste',
        ]);

        $room = Room::forceCreate([
            'hotel_id' => $hotel->id,
            'external_id' => 1,
            'name' => 'Quarto Teste',
            'quantity' => 1,
        ]);

        return Reservation::forceCreate([
            'external_id' => 1,
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
