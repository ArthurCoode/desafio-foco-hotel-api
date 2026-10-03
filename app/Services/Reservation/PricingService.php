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

        return $this->fromCents($subtotalInCents);
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

        return $this->fromCents($subtotalInCents - $discountInCents + $feesInCents);
    }

    /**
     * Converte um valor monetário em reais para centavos, sem aritmética de float.
     *
     * Strings decimais são interpretadas diretamente ("10.50" => 1050). Floats são
     * primeiro convertidos para texto e seguem o mesmo caminho. Arredonda half-up
     * pela terceira casa decimal ("10.505" => 1051, "10.504" => 1050).
     *
     * @throws InvalidArgumentException Para valores malformados, negativos ou grandes demais
     */
    public function toCents(int|float|string $amount): int
    {
        $normalized = is_float($amount) ? sprintf('%.10F', $amount) : trim((string) $amount);

        $isDecimal = preg_match('/^(-)?(\d*)(?:\.(\d*))?$/', $normalized, $m) === 1;
        $integerPart = $m[2] ?? '';
        $fractionPart = $m[3] ?? '';

        if (! $isDecimal || ($integerPart === '' && $fractionPart === '')) {
            throw new InvalidArgumentException("Valor monetário inválido: {$amount}.");
        }

        if (($m[1] ?? '') === '-') {
            throw new InvalidArgumentException("Valor monetário não pode ser negativo: {$amount}.");
        }

        $integerPart = ltrim($integerPart, '0');

        // Garante que reais * 100 cabe em um int, sem estourar para float.
        if (strlen($integerPart) > 16) {
            throw new InvalidArgumentException("Valor monetário grande demais: {$amount}.");
        }

        $fraction = str_pad($fractionPart, 3, '0');
        $cents = (int) $integerPart * 100 + (int) substr($fraction, 0, 2);

        return $cents + ((int) $fraction[2] >= 5 ? 1 : 0);
    }

    /**
     * Converte centavos para o decimal esperado pelo banco (6500 => "65.00").
     *
     * @throws InvalidArgumentException Para valores negativos
     */
    public function fromCents(int $cents): string
    {
        if ($cents < 0) {
            throw new InvalidArgumentException("Centavos não podem ser negativos: {$cents}.");
        }

        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
