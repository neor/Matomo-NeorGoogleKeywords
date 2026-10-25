<?php
namespace Piwik\Plugins\NeorGoogleKeywords;

class Tasks extends \Piwik\Plugin\Tasks
{
    public function schedule()
    {
        $this->daily('importDaily', null, self::LOW_PRIORITY);
    }

    public function importDaily()
    {
        $repository = new Repository();
        $client = new GoogleClient();
        foreach (Settings::sites() as $id => $property) {
            try {
                $end = Settings::latest();
                $last = $repository->latest($id, $property);
                // Refresh the past week; after downtime continue from the last imported date.
                $start = $last ? Settings::date($last)->modify('-6 days') : $end->modify('-27 days');
                $oldest = $end->modify('-15 months');
                if ($start < $oldest) {
                    $start = $oldest;
                }
                if ($start > $end) {
                    continue;
                }
                $batchEnd = $start->modify('+30 days');
                if ($batchEnd > $end) {
                    $batchEnd = $end;
                }
                (new Importer($client, $repository))->run($id, $start->format('Y-m-d'), $batchEnd->format('Y-m-d'), 'cron');
            } catch (\Throwable $e) {
                // Continue importing other configured sites. Importer logs ordinary failures.
                $repository->log($id, \Piwik\Piwik::translate('NeorGoogleKeywords_DailyTaskFailed'), ['status' => 'error', 'source' => 'cron', 'property' => $property]);
            }
        }
    }
}
