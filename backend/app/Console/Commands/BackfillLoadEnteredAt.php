<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillLoadEnteredAt extends Command
{
    protected $signature = 'load-audit:backfill-entered-at';
    protected $description = 'Backfill load.entered_at from earliest create log_history row';

    public function handle(): int
    {
        $rows = DB::table('log_history')
            ->select('entity_id', DB::raw('MIN(action_time) as first_action_time'))
            ->where('entity_type', 'Load')
            ->where('action_type', 'create')
            ->groupBy('entity_id')
            ->get();

        $count = 0;

        foreach ($rows as $row) {
            $updated = DB::table('load')
                ->where('id_load', $row->entity_id)
                ->whereNull('entered_at')
                ->update([
                    'entered_at' => $row->first_action_time,
                ]);

            $count += $updated;
        }

        $this->info("Backfilled entered_at for {$count} loads.");

        return self::SUCCESS;
    }
}
