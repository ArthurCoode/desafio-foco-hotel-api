<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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

            'check_in' => ['required', 'date'],
            'check_out' => ['required', 'date', 'after:check_in'],

            'guests' => ['required', 'array', 'min:1'],
            'guests.*.name' => ['required', 'string', 'max:255'],
            'guests.*.phone' => ['sometimes', 'string', 'max:30'],

            'dailies' => ['required', 'array', 'min:1'],
            'dailies.*.date' => ['required', 'date'],
            'dailies.*.amount' => ['required', 'numeric', 'min:0'],
        ];
    }
}
