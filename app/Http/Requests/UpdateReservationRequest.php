<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'units' => ['sometimes', 'integer', 'min:1'],
            'start_time' => ['sometimes', 'date'],
            'end_time' => ['sometimes', 'date', 'after:start_time'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->hasAny(['units', 'start_time', 'end_time'])) {
                $validator->errors()->add('units', 'At least one of units, start_time, end_time must be provided.');
            }

            if ($this->has('start_time') xor $this->has('end_time')) {
                $validator->errors()->add('start_time', 'start_time and end_time must be provided together.');
            }
        });
    }
}
