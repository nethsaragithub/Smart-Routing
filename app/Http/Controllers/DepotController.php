<?php

namespace App\Http\Controllers;

use App\Models\Depot;
use App\Support\DepotContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DepotController extends Controller
{
    public function index(): View
    {
        return view('depots.index', [
            'depots' => Depot::query()->withCount(['buses', 'drivers', 'routes', 'users'])->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('depots.form', ['depot' => new Depot()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Depot::create($this->validated($request));

        return redirect()->route('depots.index')->with('success', 'Depot added.');
    }

    public function edit(Depot $depot): View
    {
        return view('depots.form', ['depot' => $depot]);
    }

    public function update(Request $request, Depot $depot): RedirectResponse
    {
        $depot->update($this->validated($request, $depot));

        return redirect()->route('depots.index')->with('success', 'Depot details saved.');
    }

    /** Administrators switch the depot they are working in. */
    public function switch(Request $request, DepotContext $context): RedirectResponse
    {
        abort_unless($context->canSwitch(), 403);

        $depot = Depot::findOrFail($request->integer('depot_id'));
        $context->switchTo($depot);

        return redirect()->route('dashboard')->with('success', "Now working in {$depot->name}.");
    }

    private function validated(Request $request, ?Depot $depot = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:10', Rule::unique('depots')->ignore($depot)],
            'name' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);
    }
}
