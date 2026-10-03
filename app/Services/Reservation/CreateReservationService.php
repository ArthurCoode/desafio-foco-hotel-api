<?php

namespace App\Services\Reservation;

use App\Models\Reservation;
use App\Models\Room;
use DomainException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreateReservationService
{
    private const INITIAL_STATUS = 'confirmed';

    /** Taxas ainda não são implementadas: começam em zero. */
    private const NO_FEES = 0;

    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly PricingService $pricing,
        private readonly CouponService $coupons,
    ) {}

    /**
     * @param  array{
     *     hotel_id: int|string,
     *     room_id: int|string,
     *     check_in: string,
     *     check_out: string,
     *     guests: array<int, array{name: string, phone?: string}>,
     *     dailies: array<int, array{date: string, amount: int|float|string}>,
     *     coupon_code?: string|null
     * }  $data  Dados já validados pelo StoreReservationRequest
     *
     * @throws DomainException Quando o quarto não pertence ao hotel, não há disponibilidade
     *                         ou o cupom é inexistente/inválido (CouponNotFoundException e
     *                         InvalidCouponException estendem DomainException)
     */
    public function create(array $data): Reservation
    {
        return DB::transaction(function () use ($data): Reservation {
            // O lock no quarto serializa reservas concorrentes do mesmo quarto, evitando que
            // duas requisições passem na checagem de disponibilidade ao mesmo tempo (overbooking).
            $room = Room::query()->lockForUpdate()->findOrFail($data['room_id']);

            if ((int) $room->hotel_id !== (int) $data['hotel_id']) {
                throw new DomainException(
                    "O quarto {$room->id} não pertence ao hotel {$data['hotel_id']}."
                );
            }

            if (! $this->availability->isAvailable($room, $data['check_in'], $data['check_out'])) {
                throw new DomainException(
                    "Não há disponibilidade para o quarto {$room->id} no período informado."
                );
            }

            // Valores financeiros vêm exclusivamente das diárias e do cupom consultado no banco;
            // nenhum discount/total enviado pelo cliente é usado.
            $subtotal = $this->pricing->calculateSubtotal($data['dailies']);

            $appliedCoupon = null;
            $discountCents = 0;

            $couponCode = $data['coupon_code'] ?? null;

            if ($couponCode !== null) {
                $appliedCoupon = $this->coupons->apply($couponCode, $this->pricing->toCents($subtotal));
                $discountCents = $appliedCoupon->discountCents;
            }

            $discount = $this->pricing->fromCents($discountCents);
            $total = $this->pricing->calculateTotal($subtotal, $discount, self::NO_FEES);

            $reservation = Reservation::create([
                'hotel_id' => $room->hotel_id,
                'room_id' => $room->id,
                'check_in' => $data['check_in'],
                'check_out' => $data['check_out'],
                'status' => self::INITIAL_STATUS,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'fees' => self::NO_FEES,
                'total' => $total,
            ]);

            if ($appliedCoupon !== null) {
                $reservation->reservationCoupons()->create([
                    'coupon_id' => $appliedCoupon->coupon->id,
                    'discount' => $discount,
                ]);
            }

            $reservation->guests()->createMany(
                array_map(fn (array $guest) => Arr::only($guest, ['name', 'phone']), $data['guests'])
            );

            $reservation->dailies()->createMany(
                array_map(fn (array $daily) => Arr::only($daily, ['date', 'amount']), $data['dailies'])
            );

            return $reservation->load(['room', 'hotel', 'guests', 'dailies']);
        });
    }
}
