<?php

namespace App\Http\Requests;

use App\Models\Room;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoomRequest extends FormRequest
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

            'external_id' => [
                'required',
                'integer',
                'min:1',
                Rule::unique(Room::class, 'external_id')
                    ->where('hotel_id', $this->input('hotel_id'))
                    ->ignore($this->route('room')),
            ],

            'name' => ['required', 'string', 'max:255'],

            'quantity' => ['sometimes', 'integer', 'min:1', 'max:4294967295'],
        ];
    }
}
