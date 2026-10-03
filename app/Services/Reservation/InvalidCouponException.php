<?php

declare(strict_types=1);

namespace App\Services\Reservation;

use DomainException;

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
