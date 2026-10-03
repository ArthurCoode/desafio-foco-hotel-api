<?php

declare(strict_types=1);

namespace App\Services\Reservation;

use App\Models\Coupon;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DomainException;

class CouponNotFoundException extends DomainException
{
    public static function forCode(string $code): self
    {
        return new self("Cupom '{$code}' não encontrado.");
    }
}

class InvalidCouponException extends DomainException
{
    public static function inactive(string $code): self
    {
        return new self("O cupom '{$code}' está inativo.");
    }

    public static function notStarted(string $code): self
    {
        return new self("O cupom '{$code}' ainda não está válido.");
    }

    public static function expired(string $code): self
    {
        return new self("O cupom '{$code}' expirou.");
    }

    public static function unsupported(string $code): self
    {
        return new self("O cupom '{$code}' possui tipo ou valor inválido.");
    }
}

final readonly class AppliedCoupon
{
    public function __construct(
        public Coupon $coupon,
        public int $discountCents,
    ) {
    }
}

class CouponService
{
    private const TYPE_PERCENTAGE = 'percentage';
    private const TYPE_FIXED = 'fixed';

    private const MAX_BASIS_POINTS = 10000; // 100%

    /**
     * Valida o cupom e calcula o desconto sobre o subtotal (em centavos).
     * Não persiste nada.
     *
     * @throws CouponNotFoundException
     * @throws InvalidCouponException
     */
    public function apply(string $code, int $subtotalCents, ?CarbonInterface $now = null): AppliedCoupon
    {
        $now ??= Carbon::now();

        $coupon = Coupon::query()->where('code', $code)->first();

        if ($coupon === null) {
            throw CouponNotFoundException::forCode($code);
        }

        $this->assertValid($coupon, $now);

        $discountCents = match ($coupon->type) {
            self::TYPE_PERCENTAGE => $this->percentageDiscount($coupon, $subtotalCents),
            self::TYPE_FIXED => $this->parseMoneyCents($coupon, (string) $coupon->value),
            default => throw InvalidCouponException::unsupported($coupon->code),
        };

        return new AppliedCoupon(
            $coupon,
            max(0, min($discountCents, $subtotalCents)),
        );
    }

    private function assertValid(Coupon $coupon, CarbonInterface $now): void
    {
        if (! $coupon->active) {
            throw InvalidCouponException::inactive($coupon->code);
        }

        if ($coupon->starts_at !== null && $now->lt($coupon->starts_at)) {
            throw InvalidCouponException::notStarted($coupon->code);
        }

        if ($coupon->expires_at !== null && $now->gt($coupon->expires_at)) {
            throw InvalidCouponException::expired($coupon->code);
        }
    }

    private function percentageDiscount(Coupon $coupon, int $subtotalCents): int
    {
        $basisPoints = $this->parsePercentageBasisPoints($coupon, (string) $coupon->value);

        // Arredondamento half-up: subtotal * bp / 10000.
        return intdiv($subtotalCents * $basisPoints + 5000, 10000);
    }

    /**
     * Converte um valor monetário em reais ("12.34") para centavos (1234),
     * sem passar por float.
     */
    private function parseMoneyCents(Coupon $coupon, string $reais): int
    {
        return $this->parseDecimalHundredths($coupon, $reais);
    }

    /**
     * Converte um percentual ("10.50") para basis points (1050).
     * Rejeita valores negativos e acima de 100%.
     */
    private function parsePercentageBasisPoints(Coupon $coupon, string $percentage): int
    {
        $basisPoints = $this->parseDecimalHundredths($coupon, $percentage);

        if ($basisPoints > self::MAX_BASIS_POINTS) {
            throw InvalidCouponException::unsupported($coupon->code);
        }

        return $basisPoints;
    }

    /**
     * Converte um decimal não negativo em string para inteiro com 2 casas
     * (centésimos), com arredondamento half-up pela terceira casa.
     * Valores negativos ou mal formatados são rejeitados.
     */
    private function parseDecimalHundredths(Coupon $coupon, string $decimal): int
    {
        if (! preg_match('/^(\d+)(?:\.(\d+))?$/', trim($decimal), $m)) {
            throw InvalidCouponException::unsupported($coupon->code);
        }

        $fraction = str_pad($m[2] ?? '', 3, '0');
        $hundredths = (int) $m[1] * 100 + (int) substr($fraction, 0, 2);

        return $hundredths + ((int) $fraction[2] >= 5 ? 1 : 0);
    }
}
