<?php

namespace App\Http\Controllers;

use App\Enums\MaintenanceCategory;
use App\Enums\MaintenanceStatus;
use App\Enums\MaintenanceType;
use App\Http\Requests\MaintenanceRequest;
use App\Models\Bus;
use App\Models\MaintenanceRecord;
use App\Services\Fleet\MaintenanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class MaintenanceRecordController extends Controller
{
    public function __construct(private readonly MaintenanceService $service)
    {
    }

    public function index(Request $request): View
    {
        $records = MaintenanceRecord::query()
            ->with('bus')
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
            ->when($request->query('bus'), fn ($q, $id) => $q->where('bus_id', $id))
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 0 WHEN 'scheduled' THEN 1 ELSE 2 END")
            ->latest('scheduled_for')
            ->paginate(20)
            ->withQueryString();

        return view('maintenance.index', [
            'records' => $records,
            'statuses' => MaintenanceStatus::options(),
            'types' => MaintenanceType::options(),
            'buses' => Bus::query()->orderBy('registration_no')->get(),
            'dueForService' => Bus::query()->operational()->get()->filter->isServiceDue()->sortBy(fn ($b) => $b->kmToNextService()),
            'monthCost' => MaintenanceRecord::query()->whereBetween('scheduled_for', [today()->startOfMonth(), today()->endOfMonth()])->sum('cost'),
            'inWorkshop' => MaintenanceRecord::query()->where('status', MaintenanceStatus::InProgress)->count(),
        ]);
    }

    public function create(Request $request): View
    {
        return $this->form(new MaintenanceRecord([
            'type' => MaintenanceType::Routine,
            'category' => MaintenanceCategory::GeneralService,
            'scheduled_for' => today(),
            'bus_id' => $request->integer('bus') ?: null,
        ]));
    }

    public function store(MaintenanceRequest $request): RedirectResponse
    {
        $record = MaintenanceRecord::create([
            ...$request->validated(),
            'status' => MaintenanceStatus::Scheduled,
            'recorded_by' => $request->user()->id,
        ]);

        if ($request->boolean('start_now')) {
            $this->service->start($record, today());
        }

        return redirect()->route('maintenance.index')->with('success', "Maintenance job for {$record->bus->registration_no} recorded.");
    }

    public function edit(MaintenanceRecord $record): View
    {
        return $this->form($record);
    }

    public function update(MaintenanceRequest $request, MaintenanceRecord $record): RedirectResponse
    {
        $record->update($request->validated());

        return redirect()->route('maintenance.index')->with('success', 'Maintenance job saved.');
    }

    public function destroy(MaintenanceRecord $record): RedirectResponse
    {
        if ($record->status === MaintenanceStatus::InProgress) {
            return back()->with('error', 'Complete the job before deleting it, so the bus returns to service.');
        }

        $record->delete();

        return redirect()->route('maintenance.index')->with('success', 'Maintenance job deleted.');
    }

    /** Bus enters the workshop: it is taken off the road. */
    public function start(MaintenanceRecord $record): RedirectResponse
    {
        $this->service->start($record, today());
        $affected = $this->service->tripsAffectedByUnavailableBuses()->where('bus_id', $record->bus_id)->count();

        $message = "{$record->bus->registration_no} is now in the workshop.";
        if ($affected > 0) {
            $message .= " {$affected} upcoming trip(s) need another bus. See the dashboard alerts.";
        }

        return back()->with('success', $message);
    }

    public function complete(Request $request, MaintenanceRecord $record): RedirectResponse
    {
        $data = $request->validate([
            'completed_on' => ['required', 'date', 'before_or_equal:today'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'odometer' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->service->complete(
            $record,
            Carbon::parse($data['completed_on']),
            isset($data['cost']) ? (float) $data['cost'] : null,
            isset($data['odometer']) ? (int) $data['odometer'] : null,
        );

        return back()->with('success', "Job completed. {$record->bus->registration_no} is back in service.");
    }

    private function form(MaintenanceRecord $record): View
    {
        return view('maintenance.form', [
            'record' => $record,
            'buses' => $this->withCurrent(Bus::query()->orderBy('registration_no')->get(), $record->bus),
            'types' => MaintenanceType::options(),
            'categories' => MaintenanceCategory::options(),
        ]);
    }
}
