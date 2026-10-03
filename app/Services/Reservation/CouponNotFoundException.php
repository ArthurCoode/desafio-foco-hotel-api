<?php

declare(strict_types=1);

namespace App\Services\Reservation;

use DomainException;

class CouponNotFoundException extends DomainException
{
    public static function forCode(string $code): self
    {
        return new self("Cupom '{$code}' não encontrado.");
    }
}
