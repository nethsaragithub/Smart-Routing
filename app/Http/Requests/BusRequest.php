<?php

namespace App\Http\Requests;

use App\Enums\BusStatus;
use App\Enums\ServiceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-fleet');
    }

    protected function prepareForValidation(): void
    {
        // Normalise case and spacing, e.g. "wp  nb-1234" -> "WP NB-1234".
        if ($this->filled('registration_no')) {
            $this->merge(['registration_no' => strtoupper(trim(preg_replace('/\s+/', ' ', $this->input('registration_no'))))]);
        }
    }

    public function rules(): array
    {
        return [
            'registration_no' => ['required', 'string', 'max:20', 'regex:/^[A-Z]{0,3}\s?[A-Z]{2,3}-\d{4}$|^\d{2,3}-\d{4}$/', Rule::unique('buses')->ignore($this->route('bus'))],
            'fleet_no' => ['nullable', 'string', 'max:20'],
            'make' => ['required', 'string', 'max:50'],
            'model' => ['required', 'string', 'max:50'],
            'year_of_manufacture' => ['nullable', 'integer', 'min:1970', 'max:'.(date('Y') + 1)],
            'seating_capacity' => ['required', 'integer', 'min:10', 'max:120'],
            'service_type' => ['required', Rule::enum(ServiceType::class)],
            'fuel_type' => ['required', Rule::in(['diesel', 'electric', 'hybrid'])],
            'current_mileage' => ['required', 'integer', 'min:0'],
            'service_interval_km' => ['required', 'integer', 'min:1000', 'max:50000'],
            'last_service_mileage' => ['nullable', 'integer', 'min:0', 'lte:current_mileage'],
            'last_service_date' => ['nullable', 'date', 'before_or_equal:today'],
            'status' => ['required', Rule::enum(BusStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'registration_no.regex' => 'Use the Sri Lankan format, e.g. NB-1234, WP NC-4521 or 62-3381.',
            'last_service_mileage.lte' => 'The last service reading cannot be higher than the current odometer.',
        ];
    }
}
