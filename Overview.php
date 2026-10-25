<?php
namespace Piwik\Plugins\NeorGoogleKeywords;

final class Overview
{
    public static function quickRanges(?string $firstDate = null, ?string $lastDate = null): array
    {
        $end = $lastDate !== null ? Settings::date($lastDate) : Settings::latest();
        $ranges = self::ranges(null, $end->format('Y-m-d'));
        $month = $end->modify('first day of this month')->modify('-1 month');
        $day = min((int) $end->format('d'), (int) $month->format('t'));
        $monthStart = $month->setDate((int) $month->format('Y'), (int) $month->format('m'), $day)->modify('+1 day');
        $to = $end->format('Y-m-d');
        return [
            ['label' => \Piwik\Piwik::translate('NeorGoogleKeywords_Week'), 'from' => $ranges['weekFrom'], 'to' => $to],
            ['label' => \Piwik\Piwik::translate('NeorGoogleKeywords_Month'), 'from' => $monthStart->format('Y-m-d'), 'to' => $to],
            ['label' => \Piwik\Piwik::translate('NeorGoogleKeywords_Quarter'), 'from' => $ranges['quarterFrom'], 'to' => $to],
            ['label' => \Piwik\Piwik::translate('NeorGoogleKeywords_AllTime'), 'from' => $firstDate ?? $to, 'to' => $to],
        ];
    }

    public static function ranges(?\DateTimeImmutable $today = null, ?string $lastImportedDate = null): array
    {
        $today ??= new \DateTimeImmutable('today', new \DateTimeZone('America/Los_Angeles'));
        // Two calendar months ending today; clamp day-of-month at the lower boundary.
        $month = $today->modify('first day of this month')->modify('-2 months');
        $day = min((int) $today->format('d'), (int) $month->format('t'));
        $start = $month->setDate((int) $month->format('Y'), (int) $month->format('m'), $day)->modify('+1 day');
        $weekEnd = $lastImportedDate !== null ? Settings::date($lastImportedDate) : $today->modify('-2 days');
        // A rolling three-month window, anchored to the latest imported day.
        // Clamp before subtracting months to avoid PHP's month-end overflow.
        $quarterMonth = $weekEnd->modify('first day of this month')->modify('-3 months');
        $quarterDay = min((int) $weekEnd->format('d'), (int) $quarterMonth->format('t'));
        $quarterStart = $quarterMonth->setDate((int) $quarterMonth->format('Y'), (int) $quarterMonth->format('m'), $quarterDay)->modify('+1 day');
        return [
            'end' => $today->format('Y-m-d'), 'chartFrom' => $start->format('Y-m-d'),
            'quarterFrom' => $quarterStart->format('Y-m-d'),
            'quarterTo' => $weekEnd->format('Y-m-d'),
            'weekFrom' => $weekEnd->modify('-6 days')->format('Y-m-d'),
            'weekTo' => $weekEnd->format('Y-m-d'),
        ];
    }

    public static function series(array $rows, string $from, string $to, int $maxDays = 100): array
    {
        [$start, $end] = Settings::range($from, $to, $maxDays);
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['data_date']] = $row;
        }
        $days = [];
        for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) {
            $key = $date->format('Y-m-d');
            $row = $indexed[$key] ?? null;
            $days[] = ['date' => $key, 'label' => $date->format('d.m'), 'imported' => $row !== null,
                'clicks' => $row === null ? null : (float) $row['clicks'],
                'impressions' => $row === null ? null : (float) $row['impressions']];
        }
        return $days;
    }

    public static function chart(array $days, string $metric): array
    {
        $maximum = max(1, ...array_map(static fn($d) => (float) ($d[$metric] ?? 0), $days));
        $power = 10 ** floor(log10($maximum));
        $ceiling = ceil($maximum / $power) * $power;
        $step = 920 / max(1, count($days));
        $bars = [];
        $labels = [];
        foreach ($days as $i => $d) {
            $height = $d['imported'] ? 125 * $d[$metric] / $ceiling : 0;
            $bars[] = $d + ['x' => 62 + $i * $step, 'y' => 146 - $height,
                'height' => $height, 'width' => max(1, $step - 3)];
            if ($i % 7 === 0 || $i === count($days) - 1) {
                $labels[] = ['x' => 62 + $i * $step, 'label' => $d['label']];
            }
        }
        $sum = array_sum(array_column($days, $metric));
        $importedCount = count(array_filter($days, static fn($d) => !empty($d['imported'])));
        $avg = $importedCount > 0 ? $sum / $importedCount : 0.0;
        return [
            'metric' => $metric,
            'max' => $ceiling,
            'half' => $ceiling / 2,
            'bars' => $bars,
            'labels' => $labels,
            'sum' => $sum,
            'avg' => $avg,
            'imported_count' => $importedCount,
        ];
    }
}
