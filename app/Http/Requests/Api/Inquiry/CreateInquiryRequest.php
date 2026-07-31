<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Inquiry;

use Illuminate\Foundation\Http\FormRequest;

class CreateInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'fullName' => ['required', 'string', 'max:200'],
            'emailAddress' => ['required', 'email', 'max:255'],
            'contactNumber' => ['required', 'string', 'max:30'],
            'productToPurchase' => ['required', 'string', 'max:300'],
            'make' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'yearModel' => ['nullable', 'string', 'max:10'],
        ];
    }
}
