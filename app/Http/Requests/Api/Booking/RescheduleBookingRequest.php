<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Booking;

use Illuminate\Foundation\Http\FormRequest;

class RescheduleBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'appointmentDate' => ['required', 'date_format:Y-m-d'],
            'appointmentTime' => ['required', 'string'],
        ];
    }
}
