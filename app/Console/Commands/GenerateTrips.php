<?php

namespace App\Console\Commands;

use App\Services\Trips\TripGenerator;
use Illuminate\Console\Command;

/**
 * Creates trips from the active timetables for the coming days.
 * Scheduled to run every night (see routes/console.php).
 */
class GenerateTrips extends Command
{
    protected $signature = 'srmss:generate-trips {--days=7 : How many days ahead to generate, starting today}';

    protected $description = 'Generate daily trips from active timetables for all depots';

    public function handle(TripGenerator $generator): int
    {
        $days = max(1, (int) $this->option('days'));
        $created = $generator->generateBetween(today(), today()->addDays($days - 1));

        $this->info("{$created} trip(s) generated for the next {$days} day(s).");

        return self::SUCCESS;
    }
}
