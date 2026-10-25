<?php
namespace Piwik\Plugins\NeorGoogleKeywords;

class Importer
{
    private GoogleClient $client;
    private Repository $repository;

    public function __construct(?GoogleClient $client = null, ?Repository $repository = null)
    {
        $this->client = $client ?? new GoogleClient();
        $this->repository = $repository ?? new Repository();
    }

    public function run(int $id, string $from, string $to, string $source = 'cli'): int
    {
        [$a, $b] = Settings::range($from, $to);
        if ($b > Settings::latest()) {
            throw new OperationalException(\Piwik\Piwik::translate('NeorGoogleKeywords_ImportLatestDate', [Settings::latest()->format('Y-m-d')]));
        }
        $property = Settings::property($id);
        (new \Piwik\Site($id))->getName();
        if (!$this->repository->lock($id)) {
            throw new OperationalException(\Piwik\Piwik::translate('NeorGoogleKeywords_ImportLocked'));
        }
        $context = ['run' => bin2hex(random_bytes(6)), 'source' => in_array($source, ['web','cron','cli'], true) ? $source : 'cli',
            'property' => $property, 'from' => $from, 'to' => $to];
        $started = microtime(true);
        $count = 0;
        $stage = \Piwik\Piwik::translate('NeorGoogleKeywords_StageStart');
        $date = $from;
        try {
            $this->repository->log($id, \Piwik\Piwik::translate('NeorGoogleKeywords_ImportStarted', [$from, $to]), $context + ['status' => 'info']);
            for ($d = $a; $d <= $b; $d = $d->modify('+1 day')) {
                $date = $d->format('Y-m-d');
                $dayStarted = microtime(true);
                $stage = \Piwik\Piwik::translate('NeorGoogleKeywords_StageTotals');
                $total = $this->client->day($property, $date, []);
                $stage = \Piwik\Piwik::translate('NeorGoogleKeywords_StageQueries');
                $queries = $this->client->day($property, $date, ['query']);
                $stage = \Piwik\Piwik::translate('NeorGoogleKeywords_StagePages');
                $pages = $this->client->day($property, $date, ['query', 'page']);
                $stage = \Piwik\Piwik::translate('NeorGoogleKeywords_StageSave');
                $this->repository->saveDay($id, $property, $date, $total, $queries, $pages);
                $count++;
                $capped = $queries['capped'] || $pages['capped'];
                $this->repository->log($id, \Piwik\Piwik::translate($capped ? 'NeorGoogleKeywords_DaySavedCapped' : 'NeorGoogleKeywords_DaySaved', [$date]),
                    $context + ['status' => $capped ? 'warning' : 'success', 'date' => $date,
                        'clicks' => (float) ($total['rows'][0]['clicks'] ?? 0),
                        'impressions' => (float) ($total['rows'][0]['impressions'] ?? 0),
                        'queries' => count($queries['rows']), 'pages' => count($pages['rows']),
                        'seconds' => round(microtime(true) - $dayStarted, 2)]);
            }
            $this->repository->log($id, \Piwik\Piwik::translate('NeorGoogleKeywords_ImportComplete', [$count]),
                $context + ['status' => 'success', 'days' => $count, 'seconds' => round(microtime(true) - $started, 2)]);
            return $count;
        } catch (\Throwable $e) {
            $message = $e instanceof OperationalException ? $e->getMessage() : \Piwik\Piwik::translate('NeorGoogleKeywords_ImportInternalError');
            $this->repository->log($id, $message,
                $context + ['status' => 'error', 'date' => $date, 'stage' => $stage,
                    'days' => $count, 'seconds' => round(microtime(true) - $started, 2)]);
            throw new OperationalException($message, 0, $e);
        } finally {
            $this->repository->unlock($id);
        }
    }
}
