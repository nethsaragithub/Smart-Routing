<?php

namespace App\Http\Controllers;

use App\Enums\AdjustmentReason;
use App\Models\Trip;
use App\Models\TripAdjustment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Administrator edits to a trip's activity log. Only the wording of an entry
 * can change; its type and time stay as recorded.
 */
class TripAdjustmentController extends Controller
{
    public function update(Request $request, Trip $trip, TripAdjustment $adjustment): RedirectResponse
    {
        $data = $request->validate([
            'details' => ['required', 'string', 'max:191'],
            'reason' => ['nullable', Rule::enum(AdjustmentReason::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $adjustment->update($data);

        return back()->with('success', 'Activity entry saved.');
    }

    public function destroy(Trip $trip, TripAdjustment $adjustment): RedirectResponse
    {
        $adjustment->delete();

        return back()->with('success', 'Activity entry deleted.');
    }
}
