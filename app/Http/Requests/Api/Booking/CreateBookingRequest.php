<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Booking;

use Illuminate\Foundation\Http\FormRequest;

class CreateBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'serviceId' => ['required'],
            'appointmentDate' => ['required', 'date_format:Y-m-d'],
            'appointmentTime' => ['required', 'string'],
            // Additional optional fields pass through to BookingService::create()
        ];
    }
}
