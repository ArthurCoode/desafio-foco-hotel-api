<?php

declare(strict_types=1);

namespace App\Services\Reservation;

use DomainException;

class PaymentExceedsReservationTotalException extends DomainException
{
    public static function forAmounts(string $amount, string $remaining): self
    {
        return new self(
            "O pagamento de {$amount} excede o saldo restante da reserva ({$remaining})."
        );
    }
}
