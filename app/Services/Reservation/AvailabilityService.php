<?php

namespace App\Services\Reservation;

use App\Models\Reservation;
use App\Models\Room;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class AvailabilityService
{
    /**
     * Status de reserva que ocupam uma unidade do quarto.
     * Reservas com qualquer outro status (ex.: canceladas) não contam na disponibilidade.
     * Ajuste esta lista se o domínio tiver outros status que também bloqueiam o quarto.
     */
    private const OCCUPYING_STATUSES = ['confirmed'];

    public function isAvailable(Room $room, CarbonInterface|string $checkIn, CarbonInterface|string $checkOut): bool
    {
        return $this->availableUnits($room, $checkIn, $checkOut) > 0;
    }

    /**
     * Quantidade de unidades livres do quarto no período. Nunca é negativa.
     */
    public function availableUnits(Room $room, CarbonInterface|string $checkIn, CarbonInterface|string $checkOut): int
    {
        $occupied = $this->countConflictingReservations($room, $checkIn, $checkOut);

        return max(0, $room->quantity - $occupied);
    }

    /**
     * Check-in inclusivo e check-out exclusivo: uma reserva que termina no dia
     * em que a outra começa não conflita.
     */
    private function countConflictingReservations(
        Room $room,
        CarbonInterface|string $checkIn,
        CarbonInterface|string $checkOut,
    ): int {
        $checkIn = Carbon::parse($checkIn)->toDateString();
        $checkOut = Carbon::parse($checkOut)->toDateString();

        return Reservation::query()
            ->where('room_id', $room->id)
            ->whereIn('status', self::OCCUPYING_STATUSES)
            ->where('check_in', '<', $checkOut)
            ->where('check_out', '>', $checkIn)
            ->count();
    }
}
