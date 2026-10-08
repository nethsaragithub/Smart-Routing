<?php

namespace App\Http\Requests;

use App\Enums\ServiceType;
use App\Support\DepotContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BusRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-routes');
    }

    protected function prepareForValidation(): void
    {
        // Stops arrive as a JSON string from the map editor.
        if (is_string($this->input('stops'))) {
            $this->merge(['stops' => json_decode($this->input('stops'), true) ?: []]);
        }
    }

    public function rules(): array
    {
        $route = $this->route('route');
        $depotId = $route?->depot_id ?? app(DepotContext::class)->id();

        return [
            'route_no' => [
                'required', 'string', 'max:10',
                Rule::unique('bus_routes')->where('depot_id', $depotId)->whereNull('deleted_at')->ignore($route),
            ],
            'name' => ['required', 'string', 'max:255'],
            'origin' => ['required', 'string', 'max:255'],
            'destination' => ['required', 'string', 'max:255', 'different:origin'],
            'distance_km' => ['required', 'numeric', 'min:0.5', 'max:1000'],
            'estimated_duration_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'service_type' => ['required', Rule::enum(ServiceType::class)],
            'min_capacity' => ['nullable', 'integer', 'min:0', 'max:120'],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
            'stops' => ['required', 'array', 'min:2'],
            'stops.*.name' => ['required', 'string', 'max:255'],
            'stops.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'stops.*.lng' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    public function messages(): array
    {
        return [
            'stops.required' => 'Add at least a start and an end stop on the map.',
            'stops.min' => 'A route needs at least a start and an end stop.',
            'stops.*.name.required' => 'Every stop needs a name.',
            'destination.different' => 'The destination must be different from the origin.',
            'route_no.unique' => 'This depot already has a route with that number.',
        ];
    }

    /** @return array<string, mixed> */
    public function routeAttributes(): array
    {
        return [
            ...$this->safe()->except('stops'),
            'min_capacity' => (int) $this->input('min_capacity', 0),
            'is_active' => $this->boolean('is_active'),
        ];
    }

    /** @return list<array{name: string, lat: float, lng: float}> */
    public function stops(): array
    {
        return array_values($this->validated('stops'));
    }
}
