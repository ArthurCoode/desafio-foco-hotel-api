<?php

namespace App\Services\Reservation;

use InvalidArgumentException;

class PricingService
{
    /**
     * Subtotal é exclusivamente a soma dos valores das diárias.
     *
     * @param  iterable<int, array{date?: mixed, amount: int|float|string}>  $dailies
     */
    public function calculateSubtotal(iterable $dailies): string
    {
        $subtotalInCents = 0;

        foreach ($dailies as $daily) {
            if (! isset($daily['amount'])) {
                throw new InvalidArgumentException('Toda diária deve conter o campo amount.');
            }

            $subtotalInCents += $this->toCents($daily['amount']);
        }

        return $this->formatCents($subtotalInCents);
    }

    /**
     * Total = subtotal - desconto + taxas.
     *
     * O desconto é limitado ao subtotal, então o total nunca fica negativo.
     */
    public function calculateTotal(
        int|float|string $subtotal,
        int|float|string $discount,
        int|float|string $fees,
    ): string {
        $subtotalInCents = $this->toCents($subtotal);
        $discountInCents = min($this->toCents($discount), $subtotalInCents);
        $feesInCents = $this->toCents($fees);

        return $this->formatCents($subtotalInCents - $discountInCents + $feesInCents);
    }

    private function toCents(int|float|string $amount): int
    {
        if (! is_numeric($amount)) {
            throw new InvalidArgumentException("Valor monetário inválido: {$amount}.");
        }

        $cents = (int) round(((float) $amount) * 100);

        if ($cents < 0) {
            throw new InvalidArgumentException("Valor monetário não pode ser negativo: {$amount}.");
        }

        return $cents;
    }

    private function formatCents(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
