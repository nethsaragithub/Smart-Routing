<?php

namespace App\Http\Requests;

use App\Models\Bus;
use App\Models\FuelLog;
use App\Support\DepotContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class FuelLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('log-fuel-maintenance');
    }

    public function rules(): array
    {
        $log = $this->route('fuelLog');
        $depotId = $log?->depot_id ?? app(DepotContext::class)->id();

        // Removed buses, drivers and routes cannot be chosen, but an edited entry may keep its own.
        $inDepot = fn (string $table, ?int $keep) => Rule::exists($table, 'id')
            ->where('depot_id', $depotId)
            ->where(fn ($q) => $q->whereNull('deleted_at')->when($keep, fn ($q) => $q->orWhere('id', $keep)));

        return [
            'bus_id' => ['required', $inDepot('buses', $log?->bus_id)],
            'driver_id' => ['nullable', $inDepot('drivers', $log?->driver_id)],
            'bus_route_id' => ['nullable', $inDepot('bus_routes', $log?->bus_route_id)],
            'filled_on' => ['required', 'date', 'before_or_equal:today'],
            'odometer' => ['required', 'integer', 'min:0'],
            'litres' => ['required', 'numeric', 'min:1', 'max:600'],
            'price_per_litre' => ['required', 'numeric', 'min:1', 'max:2000'],
            'full_tank' => ['boolean'],
            'station' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * The odometer must increase over the bus's previous fill-up.
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $previous = FuelLog::query()
                ->where('bus_id', $this->input('bus_id'))
                ->whereDate('filled_on', '<=', $this->input('filled_on'))
                ->when($this->route('fuelLog'), fn ($q, $log) => $q->whereKeyNot($log->id))
                ->max('odometer');

            if ($previous !== null && (int) $this->input('odometer') <= $previous) {
                $validator->errors()->add('odometer', 'The odometer must be higher than the previous fill-up for this bus ('.number_format($previous).' km).');
            }
        }];
    }

    /** @return array<string, mixed> */
    public function logData(): array
    {
        return [...$this->validated(), 'full_tank' => $this->boolean('full_tank')];
    }
}
