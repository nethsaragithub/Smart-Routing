<?php

namespace App\Http\Requests;

use App\Enums\TripStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Administrator correction of any trip, including completed and cancelled ones.
 */
class TripCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('correct-trips');
    }

    public function rules(): array
    {
        $trip = $this->route('trip');

        // The trip's current bus and driver stay valid even if they were removed since.
        $inDepot = fn (string $table, int $current) => Rule::exists($table, 'id')
            ->where('depot_id', $trip->depot_id)
            ->where(fn ($q) => $q->whereNull('deleted_at')->orWhere('id', $current));

        return [
            'bus_id' => ['required', 'integer', $inDepot('buses', $trip->bus_id)],
            'driver_id' => ['required', 'integer', $inDepot('drivers', $trip->driver_id)],
            'status' => ['required', Rule::enum(TripStatus::class)],
            'actual_departure' => ['nullable', 'date_format:Y-m-d\TH:i', 'required_if:status,completed', 'required_with:actual_arrival'],
            'actual_arrival' => ['nullable', 'date_format:Y-m-d\TH:i', 'required_if:status,completed', Rule::when($this->filled('actual_departure'), 'after_or_equal:actual_departure')],
            'delay_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'odometer_start' => ['nullable', 'integer', 'min:0'],
            'odometer_end' => ['nullable', 'integer', 'min:0', Rule::when($this->filled('odometer_start'), 'gte:odometer_start')],
            'passenger_count' => ['nullable', 'integer', 'min:0', 'max:300'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'actual_departure.required_if' => 'A completed trip needs its departure time.',
            'actual_arrival.required_if' => 'A completed trip needs its arrival time.',
            'actual_departure.required_with' => 'Enter the departure time as well as the arrival.',
            'actual_arrival.after_or_equal' => 'Arrival cannot be earlier than departure.',
            'odometer_end.gte' => 'The closing odometer cannot be lower than the opening reading.',
        ];
    }

    /** Validated trip attributes, without the correction note. */
    public function tripData(): array
    {
        $data = collect($this->validated())->except('note')->all();

        foreach (['actual_departure', 'actual_arrival'] as $field) {
            if (! empty($data[$field])) {
                $data[$field] = CarbonImmutable::parse($data[$field]);
            }
        }

        return $data;
    }
}
