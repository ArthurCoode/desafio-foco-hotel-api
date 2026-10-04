<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'method'  => ['required', 'string', 'max:50'],
            'amount'  => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:99999999.99'],
            'paid_at' => ['nullable', 'date'],
        ];
    }
}
