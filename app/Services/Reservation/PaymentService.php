<?php

declare(strict_types=1);

namespace App\Services\Reservation;

use App\Models\Payment;
use App\Models\Reservation;
use App\Services\Reservation\PaymentExceedsReservationTotalException;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PaymentService
{
    /**
     * Registra um pagamento para a reserva, sem nunca ultrapassar o total.
     *
     * @throws InvalidArgumentException                 se o valor não for positivo
     * @throws PaymentExceedsReservationTotalException  se exceder o saldo restante
     */
    public function register(
        Reservation $reservation,
        string $method,
        string|int|float $amount,
        DateTimeInterface|string|null $paidAt = null
    ): Payment {
        $amountCents = $this->toCents($amount);

        if ($amountCents <= 0) {
            throw new InvalidArgumentException('O valor do pagamento deve ser positivo.');
        }

        return DB::transaction(function () use ($reservation, $method, $amountCents, $paidAt): Payment {
            // Trava a reserva para evitar que dois pagamentos simultâneos
            // passem pela validação ao mesmo tempo e ultrapassem o total.
            $locked = Reservation::query()
                ->whereKey($reservation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $remainingCents = $this->toCents($locked->total) - $this->paidCents($locked);

            if ($amountCents > $remainingCents) {
                throw PaymentExceedsReservationTotalException::forAmounts(
                    $this->fromCents($amountCents),
                    $this->fromCents(max($remainingCents, 0))
                );
            }

            return $locked->payments()->create([
                'method'  => $method,
                'amount'  => $this->fromCents($amountCents),
                'paid_at' => $paidAt,
            ]);
        });
    }

    /**
     * Total já pago da reserva (string decimal, ex.: "150.00").
     */
    public function getTotalPaid(Reservation $reservation): string
    {
        return $this->fromCents($this->paidCents($reservation));
    }

    /**
     * Saldo restante: reservation.total - total já pago (string decimal).
     */
    public function getRemainingBalance(Reservation $reservation): string
    {
        $remainingCents = $this->toCents($reservation->total) - $this->paidCents($reservation);

        return $this->fromCents($remainingCents);
    }

    private function paidCents(Reservation $reservation): int
    {
        return $this->toCents($reservation->payments()->sum('amount'));
    }

    private function toCents(string|int|float|null $value): int
    {
        return (int) round(((float) $value) * 100);
    }

    private function fromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
