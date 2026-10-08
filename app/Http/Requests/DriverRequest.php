<?php

namespace App\Http\Requests;

use App\Enums\DriverStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-fleet');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nic' => strtoupper(trim((string) $this->input('nic'))),
            'license_no' => strtoupper(trim((string) $this->input('license_no'))),
        ]);
    }

    public function rules(): array
    {
        $driver = $this->route('driver');

        return [
            'employee_no' => ['required', 'string', 'max:20', Rule::unique('drivers')->ignore($driver)],
            'full_name' => ['required', 'string', 'max:255'],
            // Old NIC: 9 digits + V/X. New NIC: 12 digits.
            'nic' => ['required', 'regex:/^(\d{9}[VX]|\d{12})$/', Rule::unique('drivers')->ignore($driver)],
            'date_of_birth' => ['nullable', 'date', 'before:-18 years'],
            'phone' => ['required', 'regex:/^(\+94|0)\d{9}$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'license_no' => ['required', 'string', 'max:20', Rule::unique('drivers')->ignore($driver)],
            'license_class' => ['required', 'string', 'max:10'],
            'license_expiry' => ['required', 'date'],
            'joined_on' => ['nullable', 'date', 'before_or_equal:today'],
            'status' => ['required', Rule::enum(DriverStatus::class)],
            'max_weekly_hours' => ['required', 'integer', 'min:10', 'max:84'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'nic.regex' => 'Enter a valid NIC: 9 digits followed by V or X, or 12 digits.',
            'phone.regex' => 'Enter a Sri Lankan phone number, e.g. 0771234567 or +94771234567.',
            'date_of_birth.before' => 'Drivers must be at least 18 years old.',
        ];
    }
}
