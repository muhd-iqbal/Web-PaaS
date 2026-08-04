<?php

namespace App\Services;

use App\Models\BandwidthUsage;
use App\Models\MonitoringCheckpoint;
use App\Models\Project;
use App\ValueObjects\AccessLogImportResult;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use JsonException;

class TraefikAccessLogImporter
{
    private const CHECKPOINT_KEY = 'traefik_access_log';

    public function import(): AccessLogImportResult
    {
        $result = Cache::lock('monitoring:traefik-access-log-import', 55)->get(
            fn (): AccessLogImportResult => $this->importUnlocked(),
        );

        return $result instanceof AccessLogImportResult
            ? $result
            : new AccessLogImportResult(0, 0, []);
    }

    private function importUnlocked(): AccessLogImportResult
    {
        $path = (string) config('hosting.monitoring.traefik_access_log');

        if ($path === '' || ! is_file($path)) {
            return new AccessLogImportResult(0, 0, [], false);
        }

        clearstatcache(true, $path);
        $stat = stat($path);

        if ($stat === false) {
            return new AccessLogImportResult(0, 0, [], false);
        }

        $identity = ($stat['dev'] ?? 'unknown').':'.($stat['ino'] ?? 'unknown');
        $checkpoint = MonitoringCheckpoint::query()->find(self::CHECKPOINT_KEY);
        $offset = $checkpoint && $checkpoint->file_identity === $identity && $checkpoint->byte_offset <= $stat['size']
            ? (int) $checkpoint->byte_offset
            : 0;
        $handle = fopen($path, 'rb');

        if ($handle === false || fseek($handle, $offset) !== 0) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            return new AccessLogImportResult(0, 0, [], false);
        }

        $maxLines = max(1, (int) config('hosting.monitoring.access_log_batch_lines'));
        $linesRead = 0;
        $entries = [];

        while ($linesRead < $maxLines) {
            $lineOffset = ftell($handle);
            $line = fgets($handle);

            if ($line === false) {
                break;
            }

            if (! str_ends_with($line, "\n") && feof($handle)) {
                fseek($handle, $lineOffset);
                break;
            }

            $linesRead++;
            $entry = $this->parseLine($line);

            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        $newOffset = ftell($handle);
        fclose($handle);

        if ($newOffset === false) {
            return new AccessLogImportResult(0, 0, [], false);
        }

        $projectIds = Project::query()
            ->whereKey(collect($entries)->pluck('project_id')->unique()->all())
            ->pluck('id')
            ->all();
        $validIds = array_fill_keys($projectIds, true);
        $entries = array_values(array_filter($entries, fn (array $entry): bool => isset($validIds[$entry['project_id']])));
        $aggregates = [];

        foreach ($entries as $entry) {
            $key = $entry['project_id'].':'.$entry['period_start'];

            if (! isset($aggregates[$key])) {
                $aggregates[$key] = $entry + ['request_count' => 0];
                $aggregates[$key]['bytes_sent'] = 0;
                $aggregates[$key]['bytes_received'] = 0;
            }

            $aggregates[$key]['bytes_sent'] += $entry['bytes_sent'];
            $aggregates[$key]['bytes_received'] += $entry['bytes_received'];
            $aggregates[$key]['request_count']++;

            if ($entry['requested_at']->isAfter($aggregates[$key]['requested_at'])) {
                $aggregates[$key]['requested_at'] = $entry['requested_at'];
            }
        }

        DB::transaction(function () use ($aggregates, $identity, $newOffset): void {
            foreach ($aggregates as $entry) {
                $usage = BandwidthUsage::query()
                    ->lockForUpdate()
                    ->where('project_id', $entry['project_id'])
                    ->whereDate('period_start', $entry['period_start'])
                    ->first() ?? new BandwidthUsage([
                        'project_id' => $entry['project_id'],
                        'period_start' => $entry['period_start'],
                    ]);
                $usage->bytes_sent = (int) $usage->bytes_sent + $entry['bytes_sent'];
                $usage->bytes_received = (int) $usage->bytes_received + $entry['bytes_received'];
                $usage->request_count = (int) $usage->request_count + $entry['request_count'];
                $usage->last_request_at = ! $usage->last_request_at || $entry['requested_at']->isAfter($usage->last_request_at)
                    ? $entry['requested_at']
                    : $usage->last_request_at;
                $usage->save();
            }

            MonitoringCheckpoint::query()->updateOrCreate(
                ['key' => self::CHECKPOINT_KEY],
                ['file_identity' => $identity, 'byte_offset' => $newOffset, 'processed_at' => now()],
            );
        });

        return new AccessLogImportResult($linesRead, count($entries), array_values($projectIds));
    }

    /** @return array{project_id:int, period_start:string, bytes_sent:int, bytes_received:int, requested_at:CarbonImmutable}|null */
    private function parseLine(string $line): ?array
    {
        try {
            $data = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($data) || ! preg_match('/^project-(\d+)@docker$/', (string) ($data['RouterName'] ?? ''), $matches)) {
            return null;
        }

        try {
            $requestedAt = CarbonImmutable::parse((string) ($data['StartUTC'] ?? ''));
        } catch (\Throwable) {
            return null;
        }

        return [
            'project_id' => (int) $matches[1],
            'period_start' => $requestedAt->startOfMonth()->toDateString(),
            'bytes_sent' => max(0, (int) ($data['DownstreamContentSize'] ?? 0)),
            'bytes_received' => max(0, (int) ($data['RequestContentSize'] ?? 0)),
            'requested_at' => $requestedAt,
        ];
    }
}
