<?php

namespace App\Console\Commands;

use App\Models\Telemetry;
use Illuminate\Console\Command;

class GenerateKebun1Data extends Command
{
    protected $signature = 'kebun1:generate {--limit=20 : Number of kebun-2 records to mirror}';
    protected $description = 'Generate kebun-1 data as mirror of kebun-2';

    public function handle()
    {
        $limit = $this->option('limit');
        
        $this->info("Fetching latest $limit kebun-2 records...");
        
        $kebun2Data = Telemetry::where('kebun', 'kebun-2')
            ->latest('recorded_at')
            ->take($limit)
            ->get();

        if ($kebun2Data->isEmpty()) {
            $this->error('No kebun-2 data found. Cannot create mirror data.');
            return 1;
        }

        $this->info("Found {$kebun2Data->count()} records. Creating mirrors for kebun-1...");

        $created = 0;
        $skipped = 0;

        foreach ($kebun2Data as $data) {
            try {
                // Check if kebun-1 data with same recorded_at exists
                $existing = Telemetry::where('kebun', 'kebun-1')
                    ->where('recorded_at', $data->recorded_at)
                    ->first();
                
                if ($existing) {
                    $this->line("  <fg=yellow>[SKIP]</> kebun-1 record at {$data->recorded_at} already exists");
                    $skipped++;
                    continue;
                }
                
                // Create mirrored TDS with random offset (±25-45)
                $offset = random_int(-45, 46);
                $mirroredTds = (int) round($data->tds + $offset);
                $mirroredTds = max(265, $mirroredTds); // Enforce minimum TDS
                
                // Create mirrored pH with random offset (±0.08 to ±0.35)
                $phOffset = (random_int(-35, 35) / 100);
                $mirroredPh = $data->ph + $phOffset;
                $mirroredPh = max(5.0, min(8.0, $mirroredPh));
                
                // Create mirrored suhu with random offset (±1.2 to ±3.2)
                $suhuOffset = (random_int(-32, 32) / 10);
                $mirroredSuhu = $data->suhu + $suhuOffset;
                
                Telemetry::create([
                    'kebun' => 'kebun-1',
                    'ph' => round($mirroredPh, 2),
                    'tds' => $mirroredTds,
                    'suhu' => round($mirroredSuhu, 2),
                    'cal_ph_netral' => $data->cal_ph_netral,
                    'cal_ph_asam' => $data->cal_ph_asam,
                    'cal_tds_k' => $data->cal_tds_k,
                    'tds_mentah' => $mirroredTds,
                    'raw_payload' => $data->raw_payload ?? [],
                    'recorded_at' => $data->recorded_at,
                ]);
                
                $created++;
                $this->line("  <fg=green>[✓]</> kebun-1 at {$data->recorded_at} (TDS: $mirroredTds, pH: " . round($mirroredPh, 2) . ", Suhu: " . round($mirroredSuhu, 2) . "°C)");
                
            } catch (\Exception $e) {
                $this->error("  [ERROR] Failed to create kebun-1 record: " . $e->getMessage());
            }
        }

        $this->info("\n<fg=green>✅ Done!</> Created <fg=green>$created</> kebun-1 records, skipped <fg=yellow>$skipped</> duplicates.");
        return 0;
    }
}
