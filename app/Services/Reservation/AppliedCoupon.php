<?php

declare(strict_types=1);

namespace App\Services\Reservation;

use App\Models\Coupon;

final readonly class AppliedCoupon
{
    public function __construct(
        public Coupon $coupon,
        public int $discountCents,
    ) {
    }
}
