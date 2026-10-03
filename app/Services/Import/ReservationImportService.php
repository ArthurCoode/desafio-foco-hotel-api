<?php

namespace App\Services\Import;

use App\Models\Hotel;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

class ReservationImportService
{
    /** O XML não informa status. Valor inicial atribuído apenas na criação da reserva. */
    private const DEFAULT_STATUS = 'confirmed';

    /** O XML não informa desconto nem taxas. Valor inicial atribuído apenas na criação. */
    private const DEFAULT_AMOUNT = '0.00';

    public function __construct(private readonly ImportErrorRecorder $errors) {}

    /**
     * @return array{created: int, updated: int, failed: int}
     */
    public function import(SimpleXMLElement $xml, string $source): array
    {
        $summary = ['created' => 0, 'updated' => 0, 'failed' => 0];

        foreach ($xml->Reserve as $node) {
            $externalId = (int) $node['id'];

            try {
                // Uma transação por reserva: ela e seus filhos entram juntos ou não entram.
                $created = DB::transaction(fn () => $this->importReservation($node, $source));

                $summary[$created ? 'created' : 'updated']++;
            } catch (Throwable $e) {
                // Registrado fora da transação, para não ser desfeito pelo rollback.
                $summary['failed']++;
                $this->errors->record($source, 'reservation', 'import_failed', $e->getMessage(), $externalId ?: null, $node);
            }
        }

        return $summary;
    }

    /**
     * @return bool true se a reserva foi criada, false se já existia e foi atualizada
     */
    private function importReservation(SimpleXMLElement $node, string $source): bool
    {
        $externalId = (int) $node['id'];
        $hotelCode = (int) $node['hotelCode'];
        $roomCode = (int) $node['roomCode'];

        if ($externalId <= 0 || $hotelCode <= 0 || $roomCode <= 0) {
            throw new RuntimeException('Reserva sem id, hotelCode ou roomCode válidos.');
        }

        $hotel = Hotel::where('external_id', $hotelCode)->first()
            ?? throw new RuntimeException("Hotel {$hotelCode} não encontrado.");

        $room = $hotel->rooms()->where('external_id', $roomCode)->first()
            ?? throw new RuntimeException("Quarto {$roomCode} não encontrado no hotel {$hotelCode}.");

        $checkIn = $this->requiredDate($node, 'CheckIn');
        $checkOut = $this->requiredDate($node, 'CheckOut');
        $total = $this->requiredAmount($node, 'Total');

        $guests = $this->guests($node);
        $dailies = $this->dailies($node);
        $payments = $this->payments($node);

        $subtotal = $this->formatCents(
            array_sum(array_map(fn (array $daily) => $this->toCents($daily['amount']), $dailies)),
        );

        // Chave de idempotência igual à constraint unique(['hotel_id', 'external_id']).
        $reservation = Reservation::firstOrNew([
            'hotel_id' => $hotel->id,
            'external_id' => $externalId,
        ]);

        $reservation->fill([
            'room_id' => $room->id,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'subtotal' => $subtotal,
            'total' => $total,
        ]);

        // Valores iniciais só na criação: a reimportação não pode apagar
        // descontos ou mudanças de status aplicados depois pelos Services.
        if (! $reservation->exists) {
            $reservation->fill([
                'status' => self::DEFAULT_STATUS,
                'discount' => self::DEFAULT_AMOUNT,
                'fees' => self::DEFAULT_AMOUNT,
            ]);
        }

        $reservation->save();

        // Guests, Dailies e Payments não têm ID no XML. Para manter a idempotência,
        // os filhos são substituídos a cada importação.
        $reservation->guests()->delete();
        $reservation->dailies()->delete();
        $reservation->payments()->delete();

        $reservation->guests()->createMany($guests);
        $reservation->dailies()->createMany($dailies);
        $reservation->payments()->createMany($payments);

        $this->flagDailiesOutsideStay($source, $externalId, $hotelCode, $checkIn, $checkOut, $dailies);
        $this->flagTotalMismatch($source, $externalId, $hotelCode, $subtotal, $total);

        return $reservation->wasRecentlyCreated;
    }

    /**
     * Inconsistência preservada no banco e registrada, nunca corrigida:
     * diária cuja data não está em [check_in, check_out).
     */
    private function flagDailiesOutsideStay(
        string $source,
        int $externalId,
        int $hotelCode,
        string $checkIn,
        string $checkOut,
        array $dailies,
    ): void {
        $outside = collect($dailies)
            ->pluck('date')
            ->filter(fn (string $date) => $date < $checkIn || $date >= $checkOut)
            ->values()
            ->all();

        if ($outside === []) {
            return;
        }

        $this->errors->record(
            $source,
            'reservation',
            'daily_outside_stay_period',
            "Reserva {$externalId} possui diária(s) fora do período {$checkIn} a {$checkOut}: ".implode(', ', $outside).'.',
            $externalId,
            ['hotel_code' => $hotelCode, 'check_in' => $checkIn, 'check_out' => $checkOut, 'dates' => $outside],
        );
    }

    /**
     * Apenas registra: o Total do XML é mantido como veio.
     */
    private function flagTotalMismatch(
        string $source,
        int $externalId,
        int $hotelCode,
        string $subtotal,
        string $total,
    ): void {
        if ($this->toCents($subtotal) === $this->toCents($total)) {
            return;
        }

        $this->errors->record(
            $source,
            'reservation',
            'total_mismatch',
            "Reserva {$externalId}: Total {$total} difere da soma das diárias {$subtotal}.",
            $externalId,
            ['hotel_code' => $hotelCode, 'total' => $total, 'subtotal' => $subtotal],
        );
    }

    private function guests(SimpleXMLElement $node): array
    {
        $guests = [];

        foreach ($node->Guests->Guest as $guest) {
            $guests[] = [
                'name' => trim($this->required($guest, 'Name').' '.trim((string) $guest->LastName)),
                'phone' => trim((string) $guest->Phone),
            ];
        }

        return $guests;
    }

    private function dailies(SimpleXMLElement $node): array
    {
        $dailies = [];

        foreach ($node->Dailies->Daily as $daily) {
            $dailies[] = [
                'date' => $this->requiredDate($daily, 'Date'),
                'amount' => $this->requiredAmount($daily, 'Value'),
            ];
        }

        return $dailies;
    }

    private function payments(SimpleXMLElement $node): array
    {
        $payments = [];

        if (! isset($node->Payments->Payment)) {
            return $payments;
        }

        foreach ($node->Payments->Payment as $payment) {
            $payments[] = [
                'method' => $this->required($payment, 'Method'),
                'amount' => $this->requiredAmount($payment, 'Value'),
            ];
        }

        return $payments;
    }

    private function required(SimpleXMLElement $node, string $field): string
    {
        $value = trim((string) $node->{$field});

        if ($value === '') {
            throw new RuntimeException("Campo obrigatório ausente: {$field}.");
        }

        return $value;
    }

    private function requiredDate(SimpleXMLElement $node, string $field): string
    {
        return CarbonImmutable::parse($this->required($node, $field))->toDateString();
    }

    private function requiredAmount(SimpleXMLElement $node, string $field): string
    {
        $value = $this->required($node, $field);

        if (! is_numeric($value)) {
            throw new RuntimeException("Valor numérico inválido em {$field}: {$value}.");
        }

        return $value;
    }

    /** Compara valores monetários em centavos, evitando erro de ponto flutuante. */
    private function toCents(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function formatCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
