<?php

namespace App\Http\Requests;

use App\Enums\Recurrence;
use App\Support\DepotContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-schedules');
    }

    public function rules(): array
    {
        $depotId = $this->route('schedule')?->depot_id ?? app(DepotContext::class)->id();
        $inDepot = fn (string $table) => Rule::exists($table, 'id')
            ->where('depot_id', $depotId)
            ->whereNull('deleted_at');

        return [
            'bus_route_id' => ['required', $inDepot('bus_routes')],
            'bus_id' => ['required', $inDepot('buses')],
            'driver_id' => ['required', $inDepot('drivers')],
            'departure_time' => ['required', 'date_format:H:i'],
            'arrival_time' => ['required', 'date_format:H:i', 'after:departure_time'],
            'recurrence' => ['required', Rule::enum(Recurrence::class)],
            'weekdays' => ['nullable', 'required_if:recurrence,weekly', 'array'],
            'weekdays.*' => ['integer', 'between:1,7'],
            'month_days' => ['nullable', 'required_if:recurrence,monthly', 'array'],
            'month_days.*' => ['integer', 'between:1,31'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'acknowledge_warnings' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'arrival_time.after' => 'Arrival must be later than departure. Overnight services should be split into two timetables.',
            'weekdays.required_if' => 'Pick at least one day of the week.',
            'month_days.required_if' => 'Pick at least one day of the month.',
        ];
    }

    /** @return array<string, mixed> */
    public function scheduleData(): array
    {
        $data = $this->safe()->except('acknowledge_warnings');
        $recurrence = Recurrence::from($data['recurrence']);

        // Keep only the day list relevant to the chosen recurrence.
        $data['weekdays'] = $recurrence === Recurrence::Weekly ? array_map('intval', $data['weekdays'] ?? []) : null;
        $data['month_days'] = $recurrence === Recurrence::Monthly ? array_map('intval', $data['month_days'] ?? []) : null;

        return $data;
    }
}
