<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('passwordConfirm') && ! $this->has('password_confirmation')) {
            $this->merge([
                'password_confirmation' => $this->input('passwordConfirm'),
            ]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
