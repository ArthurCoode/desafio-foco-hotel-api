<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'hotel_id' => ['bail', 'required', 'integer', 'exists:hotels,id'],
            'room_id' => ['bail', 'required', 'integer', 'exists:rooms,id'],

            'check_in' => ['required', 'date_format:Y-m-d'],
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in'],

            'guests' => ['required', 'array', 'min:1'],
            'guests.*.name' => ['required', 'string', 'max:255'],
            'guests.*.phone' => ['sometimes', 'string', 'max:30'],

            'dailies' => ['required', 'array', 'min:1'],
            'dailies.*.date' => ['required', 'date_format:Y-m-d'],
            'dailies.*.amount' => ['required', 'numeric', 'min:0'],

            'coupon_code' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [$this->validateDailiesMatchStay(...)];
    }

    /**
     * Exige exatamente uma diária por noite, com check-in inclusivo e check-out exclusivo.
     */
    private function validateDailiesMatchStay(Validator $validator): void
    {
        $errors = $validator->errors();

        // Sem datas válidas não há como comparar o período com as diárias.
        if ($errors->hasAny(['check_in', 'check_out', 'dailies', 'dailies.*'])) {
            return;
        }

        $checkIn = $this->toDate($this->input('check_in'));
        $checkOut = $this->toDate($this->input('check_out'));
        $checkInDate = $checkIn->toDateString();
        $checkOutDate = $checkOut->toDateString();

        $dailies = $this->input('dailies');
        $seen = [];

        foreach ($dailies as $index => $daily) {
            $date = $this->toDate($daily['date'])->toDateString();

            if (isset($seen[$date])) {
                $errors->add("dailies.{$index}.date", "A data {$date} está duplicada nas diárias.");

                continue;
            }

            $seen[$date] = true;

            if ($date < $checkInDate) {
                $errors->add("dailies.{$index}.date", "A diária de {$date} é anterior ao check-in ({$checkInDate}).");
            } elseif ($date >= $checkOutDate) {
                $errors->add("dailies.{$index}.date", "A diária de {$date} é igual ou posterior ao check-out ({$checkOutDate}).");
            }
        }

        // Com datas únicas e todas dentro de [check_in, check_out), a mesma quantidade de
        // diárias e de noites implica uma diária para cada noite, sem faltar nenhuma.
        $nights = (int) $checkIn->diffInDays($checkOut);

        if (count($dailies) !== $nights) {
            $errors->add(
                'dailies',
                "A hospedagem tem {$nights} noite(s) e exige exatamente uma diária por noite, mas foram enviadas ".count($dailies).'.'
            );
        }
    }

    private function toDate(string $value): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC');
    }
}
