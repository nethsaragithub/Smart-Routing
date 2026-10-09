<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

abstract class Controller
{
    /**
     * Options for a select, plus the record's current choice when the list no
     * longer contains it (e.g. a bus removed from the fleet), so that editing an
     * old record never silently swaps it for another.
     */
    protected function withCurrent(Collection $options, ?Model $current): Collection
    {
        return $current && ! $options->contains('id', $current->id) ? $options->prepend($current) : $options;
    }
}
