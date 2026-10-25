<?php
// Run: php plugins/NeorGoogleKeywords/tests/rank-highlights.php
$root = dirname(__DIR__);
require dirname($root, 2) . '/vendor/autoload.php';
$loader = new Twig\Loader\FilesystemLoader($root . '/templates');
$loader->addPath($root . '/templates', 'NeorGoogleKeywords');
$twig = new Twig\Environment($loader, ['autoescape' => 'html']);
require __DIR__ . '/translations.php';
ngkTestTwigTranslations($twig);
$cases = [
    [1, 10, 'ngk-rank-top1'], ['1.00', 10, 'ngk-rank-top1'],
    [2, 10, 'ngk-rank-top3'], [2.5, 10, 'ngk-rank-top3'], [3, 10, 'ngk-rank-top3'],
    [1.01, 10, 'ngk-rank-top3'], [1.5, 10, 'ngk-rank-top3'], [1.99, 10, 'ngk-rank-top3'],
    [3.01, 10, ''], [4, 10, ''], [0, 10, ''], [1, 0, ''],
];
$rows = [];
foreach ($cases as [$position, $impressions, $class]) {
    $rows[] = ['position' => $position, 'impressions' => $impressions, 'clicks' => 1,
        'query_text' => 'test', 'drill_url' => 'index.php', 'page_url' => 'https://example.com'];
}
foreach ([null, 'test'] as $query) {
    $html = $twig->render('detail.twig', ['ngkQuery' => $query, 'ngkRows' => $rows,
        'ngkFrom' => '2026-09-01', 'ngkTo' => '2026-09-29', 'ngkHeaders' => []]);
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    $rendered = (new DOMXPath($dom))->query('//table/tbody/tr');
    if ($rendered->length !== count($cases)) {
        throw new RuntimeException('Incorrect row count');
    }
    foreach ($cases as $i => $case) {
        $expected = $query === null ? $case[2] : '';
        if ($rendered->item($i)->getAttribute('class') !== $expected) {
            throw new RuntimeException('Incorrect highlight for case ' . $i);
        }
    }
}
echo "PASS: rank boundaries, decimal positions, missing impressions and page view.\n";
