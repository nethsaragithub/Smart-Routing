<?php

namespace App\Http\Requests;

use App\Enums\MaintenanceCategory;
use App\Enums\MaintenanceType;
use App\Support\DepotContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('log-fuel-maintenance');
    }

    public function rules(): array
    {
        $depotId = $this->route('record')?->depot_id ?? app(DepotContext::class)->id();

        return [
            'bus_id' => ['required', Rule::exists('buses', 'id')->where('depot_id', $depotId)],
            'type' => ['required', Rule::enum(MaintenanceType::class)],
            'category' => ['required', Rule::enum(MaintenanceCategory::class)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'scheduled_for' => ['required', 'date'],
            'odometer' => ['nullable', 'integer', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'workshop' => ['nullable', 'string', 'max:255'],
        ];
    }
}
