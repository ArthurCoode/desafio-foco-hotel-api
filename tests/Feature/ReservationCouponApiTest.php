<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\ReservationCoupon;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReservationCouponApiTest extends TestCase
{
    use RefreshDatabase;

    /** Endpoint de criação de reservas. */
    private const ENDPOINT = '/api/reservations';

    private Hotel $hotel;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = Hotel::create(['external_id' => 9001, 'name' => 'Hotel Teste']);
        $this->room = Room::create(['external_id' => 9001, 'hotel_id' => $this->hotel->id, 'name' => 'Quarto 101']);
    }

    /**
     * Payload válido: 3 noites x R$ 200,00 = subtotal R$ 600,00.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'hotel_id' => $this->hotel->id,
            'room_id' => $this->room->id,
            'check_in' => '2026-11-10',
            'check_out' => '2026-11-13',
            'guests' => [
                ['name' => 'Maria Silva'],
            ],
            'dailies' => [
                ['date' => '2026-11-10', 'amount' => '200.00'],
                ['date' => '2026-11-11', 'amount' => '200.00'],
                ['date' => '2026-11-12', 'amount' => '200.00'],
            ],
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function makeCoupon(array $overrides = []): Coupon
    {
        return Coupon::create(array_merge([
            'code' => 'PROMO',
            'type' => 'percentage',
            'value' => '10.00',
            'starts_at' => null,
            'expires_at' => null,
            'active' => true,
        ], $overrides));
    }

    private function assertNothingWasCreated(): void
    {
        $this->assertSame(0, Reservation::count());
        $this->assertSame(0, DB::table('reservation_coupons')->count());
    }

    private function assertReservationCouponDiscount(Coupon $coupon, string $expectedDiscount): void
    {
        $this->assertSame(1, ReservationCoupon::count());

        $row = DB::table('reservation_coupons')->first();

        $this->assertSame($coupon->id, (int) $row->coupon_id);
        $this->assertSame($expectedDiscount, $this->normalizeDecimal((string) $row->discount));
    }

    /**
     * Normaliza um decimal em string para 2 casas ("60" => "60.00", "60.5" => "60.50"),
     * sem passar por float. Valores já em "60.00" permanecem iguais.
     */
    private function normalizeDecimal(string $decimal): string
    {
        [$integer, $fraction] = array_pad(explode('.', $decimal, 2), 2, '');

        return $integer.'.'.str_pad(substr($fraction, 0, 2), 2, '0');
    }

    public function test_creates_reservation_without_coupon(): void
    {
        $response = $this->postJson(self::ENDPOINT, $this->payload());

        $response->assertCreated();

        $reservation = Reservation::firstOrFail();

        $this->assertSame('600.00', $reservation->subtotal);
        $this->assertSame('0.00', $reservation->discount);
        $this->assertSame('600.00', $reservation->total);
        $this->assertSame(0, DB::table('reservation_coupons')->count());
    }

    public function test_applies_percentage_coupon(): void
    {
        $coupon = $this->makeCoupon(['type' => 'percentage', 'value' => '10.00']);

        $response = $this->postJson(self::ENDPOINT, $this->payload(['coupon_code' => 'PROMO']));

        $response->assertCreated();

        $reservation = Reservation::firstOrFail();

        // 10% de R$ 600,00 = R$ 60,00
        $this->assertSame('600.00', $reservation->subtotal);
        $this->assertSame('60.00', $reservation->discount);
        $this->assertSame('540.00', $reservation->total);

        $this->assertReservationCouponDiscount($coupon, '60.00');
        $this->assertSame($reservation->id, (int) DB::table('reservation_coupons')->value('reservation_id'));
    }

    public function test_applies_fixed_coupon(): void
    {
        $coupon = $this->makeCoupon(['code' => 'FIXO50', 'type' => 'fixed', 'value' => '50.00']);

        $response = $this->postJson(self::ENDPOINT, $this->payload(['coupon_code' => 'FIXO50']));

        $response->assertCreated();

        $reservation = Reservation::firstOrFail();

        $this->assertSame('600.00', $reservation->subtotal);
        $this->assertSame('50.00', $reservation->discount);
        $this->assertSame('550.00', $reservation->total);

        $this->assertReservationCouponDiscount($coupon, '50.00');
    }

    public function test_rejects_nonexistent_coupon(): void
    {
        $response = $this->postJson(self::ENDPOINT, $this->payload(['coupon_code' => 'NAO-EXISTE']));

        $response->assertUnprocessable();

        $this->assertNothingWasCreated();
    }

    public function test_rejects_inactive_coupon(): void
    {
        $this->makeCoupon(['active' => false]);

        $response = $this->postJson(self::ENDPOINT, $this->payload(['coupon_code' => 'PROMO']));

        $response->assertUnprocessable();

        $this->assertNothingWasCreated();
    }

    public function test_rejects_expired_coupon(): void
    {
        $this->makeCoupon(['expires_at' => now()->subDay()]);

        $response = $this->postJson(self::ENDPOINT, $this->payload(['coupon_code' => 'PROMO']));

        $response->assertUnprocessable();

        $this->assertNothingWasCreated();
    }

    public function test_caps_discount_at_subtotal(): void
    {
        $coupon = $this->makeCoupon(['code' => 'GIGANTE', 'type' => 'fixed', 'value' => '1000.00']);

        $response = $this->postJson(self::ENDPOINT, $this->payload(['coupon_code' => 'GIGANTE']));

        $response->assertCreated();

        $reservation = Reservation::firstOrFail();

        $this->assertSame('600.00', $reservation->subtotal);
        $this->assertSame('600.00', $reservation->discount);
        $this->assertSame('0.00', $reservation->total);

        $this->assertReservationCouponDiscount($coupon, '600.00');
    }

    public function test_ignores_financial_values_sent_by_client(): void
    {
        $coupon = $this->makeCoupon(['type' => 'percentage', 'value' => '10.00']);

        $response = $this->postJson(self::ENDPOINT, $this->payload([
            'coupon_code' => 'PROMO',
            'subtotal' => '1.00',
            'discount' => '999.00',
            'total' => '0.01',
        ]));

        $response->assertCreated();

        $reservation = Reservation::firstOrFail();

        $this->assertSame('600.00', $reservation->subtotal);
        $this->assertSame('60.00', $reservation->discount);
        $this->assertSame('540.00', $reservation->total);

        $this->assertReservationCouponDiscount($coupon, '60.00');
    }
}
