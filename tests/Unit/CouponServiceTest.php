<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Coupon;
use App\Services\Reservation\CouponNotFoundException;
use App\Services\Reservation\CouponService;
use App\Services\Reservation\InvalidCouponException;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CouponServiceTest extends TestCase
{
    use RefreshDatabase;

    private CouponService $service;

    private CarbonInterface $now;

    protected function setUp(): void
    {
        parent::setUp();

        // Instanciar o serviço carrega o arquivo que também declara
        // as exceções e o AppliedCoupon (todos no mesmo arquivo).
        $this->service = new CouponService();

        $this->now = Carbon::parse('2026-06-15 12:00:00');
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

    public function test_rejects_nonexistent_coupon(): void
    {
        $this->expectException(CouponNotFoundException::class);

        $this->service->apply('NAO-EXISTE', 10000, $this->now);
    }

    public function test_rejects_inactive_coupon(): void
    {
        $this->makeCoupon(['active' => false]);

        $this->expectException(InvalidCouponException::class);

        $this->service->apply('PROMO', 10000, $this->now);
    }

    public function test_rejects_coupon_that_has_not_started_yet(): void
    {
        $this->makeCoupon(['starts_at' => $this->now->copy()->addSecond()]);

        $this->expectException(InvalidCouponException::class);

        $this->service->apply('PROMO', 10000, $this->now);
    }

    public function test_rejects_expired_coupon(): void
    {
        $this->makeCoupon(['expires_at' => $this->now->copy()->subSecond()]);

        $this->expectException(InvalidCouponException::class);

        $this->service->apply('PROMO', 10000, $this->now);
    }

    public function test_calculates_ten_percent_discount(): void
    {
        $this->makeCoupon(['type' => 'percentage', 'value' => '10.00']);

        // 10% de R$ 200,00 = R$ 20,00
        $applied = $this->service->apply('PROMO', 20000, $this->now);

        $this->assertSame(2000, $applied->discountCents);
    }

    public function test_calculates_percentage_discount_with_decimal_places(): void
    {
        $this->makeCoupon(['type' => 'percentage', 'value' => '10.50']);

        // 10,50% de R$ 200,00 = R$ 21,00
        $applied = $this->service->apply('PROMO', 20000, $this->now);

        $this->assertSame(2100, $applied->discountCents);
    }

    public function test_calculates_fixed_discount(): void
    {
        $this->makeCoupon(['type' => 'fixed', 'value' => '50.00']);

        $applied = $this->service->apply('PROMO', 20000, $this->now);

        $this->assertSame(5000, $applied->discountCents);
    }

    public function test_rejects_percentage_greater_than_one_hundred(): void
    {
        $this->makeCoupon(['type' => 'percentage', 'value' => '100.01']);

        $this->expectException(InvalidCouponException::class);

        $this->service->apply('PROMO', 10000, $this->now);
    }

    public function test_caps_fixed_discount_at_subtotal(): void
    {
        $this->makeCoupon(['type' => 'fixed', 'value' => '50.00']);

        // Subtotal de R$ 30,00 e cupom de R$ 50,00: desconto limitado a R$ 30,00.
        $applied = $this->service->apply('PROMO', 3000, $this->now);

        $this->assertSame(3000, $applied->discountCents);
    }

    public function test_accepts_coupon_exactly_at_start_time(): void
    {
        $coupon = $this->makeCoupon(['starts_at' => $this->now->copy()]);

        $applied = $this->service->apply('PROMO', 20000, $this->now);

        $this->assertSame($coupon->id, $applied->coupon->id);
        $this->assertSame(2000, $applied->discountCents);
    }

    public function test_accepts_coupon_exactly_at_expiration_time(): void
    {
        $coupon = $this->makeCoupon(['expires_at' => $this->now->copy()]);

        $applied = $this->service->apply('PROMO', 20000, $this->now);

        $this->assertSame($coupon->id, $applied->coupon->id);
        $this->assertSame(2000, $applied->discountCents);
    }

    public function test_does_not_persist_any_change_to_the_database(): void
    {
        $coupon = $this->makeCoupon(['type' => 'fixed', 'value' => '50.00']);

        $couponsBefore = DB::table('coupons')->get()->toArray();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->service->apply('PROMO', 20000, $this->now);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Nenhuma query de escrita foi executada.
        foreach ($queries as $query) {
            $this->assertStringStartsWith(
                'select',
                strtolower(ltrim($query['query'])),
                'O serviço executou uma query que não é de leitura: ' . $query['query'],
            );
        }

        // O estado da tabela permanece idêntico.
        $this->assertEquals($couponsBefore, DB::table('coupons')->get()->toArray());
        $this->assertSame(1, Coupon::count());
        $this->assertSame('50.00', number_format((float) $coupon->fresh()->value, 2, '.', ''));
    }
}
