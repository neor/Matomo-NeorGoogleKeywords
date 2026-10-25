<?php
// Focused template check; MATOMO_TEST_ROOT must include Matomo's vendor directory.
$matomo = getenv('MATOMO_TEST_ROOT');
if (!$matomo) { $matomo = dirname(__DIR__, 3); }
require $matomo . '/vendor/autoload.php';
$root = dirname(__DIR__);
foreach (['OperationalException', 'Settings', 'Overview'] as $name) { require $root . '/' . $name . '.php'; }
$loader = new Twig\Loader\FilesystemLoader($root . '/templates');
$loader->addPath($root . '/templates', 'NeorGoogleKeywords');
$twig = new Twig\Environment($loader, ['autoescape' => 'html', 'strict_variables' => true]);
require __DIR__ . '/translations.php';
ngkTestTwigTranslations($twig);
function chartCheck($condition, $message) { if (!$condition) { throw new RuntimeException($message); } echo "PASS $message\n"; }
function renderChart(Twig\Environment $twig, array $rows, string $from = '2026-09-01', string $to = '2026-09-03'): DOMXPath {
    $days = Piwik\Plugins\NeorGoogleKeywords\Overview::series($rows, $from, $to);
    foreach ($days as &$day) { $day['url'] = 'index.php?section=detail&from=' . $day['date']; } unset($day);
    $html = $twig->render('stream.twig', [
        'ngkAll' => [], 'ngkRanges' => ['chartFrom'=>$from,'end'=>$to],
        'ngkCharts' => [Piwik\Plugins\NeorGoogleKeywords\Overview::chart($days, 'clicks'),
            Piwik\Plugins\NeorGoogleKeywords\Overview::chart($days, 'impressions')],
        'ngkDays' => $days, 'ngkTop' => [],
    ]);
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html); libxml_clear_errors();
    return new DOMXPath($dom);
}
$x = renderChart($twig, [
    ['data_date'=>'2026-09-01','clicks'=>0,'impressions'=>0],
    ['data_date'=>'2026-09-02','clicks'=>10,'impressions'=>1000],
]);
chartCheck($x->query('//svg')->length === 1, 'one combined chart');
chartCheck($x->query('//line[@class="ngk-sunday-guide"]')->length === 0, 'weekdays have no Sunday guides');
chartCheck($x->query('//path[contains(@class,"ngk-series-line")]')->length === 2, 'two line series');
chartCheck($x->query('//rect[contains(@class,"ngk-bar")]')->length === 0, 'histogram removed');
chartCheck($x->query('//svg//a')->length === 3, 'all day links retained');
chartCheck($x->query('//line[@class="ngk-missing-day"]')->length === 1, 'missing day marked separately');
foreach (['clicks', 'impressions'] as $metric) {
    $points = $x->query('//circle[contains(@class,"ngk-' . $metric . '-point")]');
    chartCheck($points->length === 2, "$metric imported points only");
    chartCheck((float)$points[0]->getAttribute('cy') === 250.0, "$metric zero baseline");
    chartCheck((float)$points[1]->getAttribute('cy') === 50.0, "$metric independent scale");
    chartCheck($x->query('//path[contains(@class,"ngk-' . $metric . '-line")]')->item(0)->getAttribute('d') === ' M75,250 L505,50', "$metric connects adjacent imported days");
}
chartCheck($x->query('//g[@class="ngk-axis-clicks"]')->length===1
    && $x->query('//g[@class="ngk-axis-impressions"]')->length===1, 'both axes labelled');
chartCheck(str_contains($x->query('//svg//a')->item(1)->getAttribute('aria-label'), '1 000'), 'day has accessible values');
$x = renderChart($twig, [
    ['data_date'=>'2026-09-01','clicks'=>2,'impressions'=>20],
    ['data_date'=>'2026-09-03','clicks'=>10,'impressions'=>1000],
]);
foreach (['clicks', 'impressions'] as $metric) {
    $path = $x->query('//path[contains(@class,"ngk-' . $metric . '-line")]')->item(0)->getAttribute('d');
    chartCheck(substr_count($path, ' M') === 2 && !str_contains($path, ' L'), "$metric breaks across missing import");
}
$x = renderChart($twig, [['data_date'=>'2026-09-02','clicks'=>0,'impressions'=>0]]);
chartCheck($x->query('//circle[@cy="250"]')->length === 2, 'isolated zero day remains visible');
$x = renderChart($twig, [['data_date'=>'2026-09-02','clicks'=>10,'impressions'=>1000]], '2026-09-02', '2026-09-02');
chartCheck($x->query('//circle[@cx="505"]')->length === 2, 'single-day chart centers both points');
chartCheck($x->query('//rect[@class="ngk-day-hit" and @width="860"]')->length === 1, 'single-day chart has valid hit area');
$x = renderChart($twig, []);
chartCheck($x->query('//path[contains(@class,"ngk-series-line")]')->length===0
    && $x->query('//circle')->length===0
    && $x->query('//line[@class="ngk-missing-day"]')->length===3, 'empty history has no fabricated zero lines');
$x = renderChart($twig, [['data_date'=>'2026-09-13','clicks'=>10,'impressions'=>1000]], '2026-09-06', '2026-09-20');
$guides = $x->query('//line[@class="ngk-sunday-guide"]');
chartCheck($guides->length === 3, 'every Sunday is marked including missing imports and range boundaries');
foreach ([75, 505, 935] as $index => $expectedX) {
    $guide = $guides->item($index);
    chartCheck((float)$guide->getAttribute('x1') === (float)$expectedX
        && $guide->getAttribute('x1') === $guide->getAttribute('x2')
        && $guide->getAttribute('y1') === '50' && $guide->getAttribute('y2') === '250', 'Sunday guide spans plot at day coordinate ' . $expectedX);
}
$x = renderChart($twig, [], '2026-09-06', '2026-09-06');
chartCheck($x->query('//line[@class="ngk-sunday-guide" and @x1="505" and @x2="505"]')->length === 1, 'single Sunday guide is centered');
$x = renderChart($twig, [['data_date'=>'2026-09-07','clicks'=>10,'impressions'=>1000]], '2026-09-07', '2026-09-13');
foreach (['пн', 'вт', 'ср', 'чт', 'пт', 'сб', 'вс'] as $index => $weekday) {
    $link = $x->query('//svg//a')->item($index);
    $prefix = '2026-09-' . sprintf('%02d', 7 + $index) . ' ' . $weekday . ': ';
    chartCheck(str_starts_with($x->query('title', $link)->item(0)->textContent, $prefix)
        && str_starts_with($link->getAttribute('aria-label'), $prefix), 'tooltip and accessible label include weekday ' . $weekday);
}

function renderDetailChart(Twig\Environment $twig, array $rows, string $from = '2026-09-01', string $to = '2026-09-03', ?string $query = null): DOMXPath {
    $days = Piwik\Plugins\NeorGoogleKeywords\Overview::series($rows, $from, $to, 3660);
    foreach ($days as &$day) { $day['url'] = 'index.php?section=detail&from=' . $day['date']; } unset($day);
    $charts = [Piwik\Plugins\NeorGoogleKeywords\Overview::chart($days, 'clicks'),
        Piwik\Plugins\NeorGoogleKeywords\Overview::chart($days, 'impressions')];
    $html = $twig->render('detail.twig', [
        'ngkId' => 1, 'ngkFrom' => $from, 'ngkTo' => $to, 'ngkQuery' => $query,
        'ngkSort' => 'clicks', 'ngkDirection' => 'desc',
        'ngkTotals' => ['days' => count($rows), 'clicks' => 12, 'impressions' => 1020, 'ctr' => 1.18, 'position' => 2.5, 'capped' => false, 'imported_at' => '2026-09-04'],
        'ngkExpectedDays' => 3, 'ngkRows' => [], 'ngkHeaders' => [], 'ngkQuickRanges' => [],
        'ngkPrevious' => '', 'ngkNext' => '', 'ngkBack' => 'index.php',
        'ngkDays' => $days, 'ngkCharts' => $charts,
    ]);
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html); libxml_clear_errors();
    return new DOMXPath($dom);
}

$xd = renderDetailChart($twig, [
    ['data_date' => '2026-09-01', 'clicks' => 2, 'impressions' => 20],
    ['data_date' => '2026-09-02', 'clicks' => 10, 'impressions' => 1000],
]);
chartCheck($xd->query('//svg[contains(@class,"ngk-combined-chart")]')->length === 1, 'group page renders combined chart');
chartCheck($xd->query('//line[contains(@class,"ngk-clicks-avg")]')->length === 1, 'group chart renders clicks average line');
chartCheck($xd->query('//line[contains(@class,"ngk-impressions-avg")]')->length === 1, 'group chart renders impressions average line');
chartCheck($xd->query('//line[contains(@class,"ngk-clicks-avg")]')->item(0)->getAttribute('stroke-dasharray') === '4,4', 'average line has dashed pattern');
$legendText = $xd->query('//div[@class="ngk-combined-legend"]')->item(0)->textContent;
chartCheck(str_contains($legendText, '6') && str_contains($legendText, '510'), 'group chart legend displays daily average values');

$xdQuery = renderDetailChart($twig, [
    ['data_date' => '2026-09-01', 'clicks' => 5, 'impressions' => 50],
], '2026-09-01', '2026-09-01', 'test query');
chartCheck(str_contains($xdQuery->query('//div[@class="ngk-section-title"]/h3')->item(0)->textContent, 'test query'), 'group chart heading displays drilled query');
chartCheck($xdQuery->query('//div[@class="ngk-section-title"]/h3//span[contains(@class,"ngk-highlight-keyword")]')->length === 1, 'group chart heading highlights drilled query');
chartCheck($xdQuery->query('//div[@class="ngk-section-title"]/h3//span[contains(@class,"ngk-highlight-keyword")]')->item(0)->textContent === 'test query', 'highlighted text matches query');

$encodedUrl = 'https://angelio.ee/ru/%D0%BA%D0%BB%D0%B0%D0%B4%D0%B1%D0%B8%D1%89%D0%B5-%D1%80%D0%B8%D0%B9%D0%B3%D0%B8%D0%BA%D1%8E%D0%BB%D0%B0-%D0%B2%D0%BE%D0%B7%D0%BB%D0%B5-%D0%BD%D0%B0%D1%80%D0%B2%D1%8B/';
$decodedUrl = rawurldecode($encodedUrl);
$pageHtml = $twig->render('detail.twig', [
    'ngkId' => 1, 'ngkFrom' => '2026-09-01', 'ngkTo' => '2026-09-03', 'ngkQuery' => 'кладбище',
    'ngkSort' => 'clicks', 'ngkDirection' => 'desc',
    'ngkTotals' => ['days' => 1, 'clicks' => 1, 'impressions' => 10, 'ctr' => 10.0, 'position' => 1.0, 'capped' => false, 'imported_at' => '2026-09-04'],
    'ngkExpectedDays' => 1,
    'ngkRows' => [[
        'position' => 1, 'impressions' => 10, 'clicks' => 1, 'ctr' => 10.0,
        'query_text' => 'кладбище', 'drill_url' => 'index.php',
        'page_url' => $encodedUrl,
        'page_display' => $decodedUrl,
    ]],
    'ngkHeaders' => [], 'ngkQuickRanges' => [],
    'ngkPrevious' => '', 'ngkNext' => '', 'ngkBack' => 'index.php',
    'ngkDays' => [], 'ngkCharts' => [],
]);
$pageDom = new DOMDocument();
libxml_use_internal_errors(true);
$pageDom->loadHTML('<?xml encoding="UTF-8">' . $pageHtml);
libxml_clear_errors();
$pageXPath = new DOMXPath($pageDom);
$linkNode = $pageXPath->query('//table/tbody/tr//a[contains(@class,"ngk-query-link")]')->item(0);
chartCheck($linkNode !== null, 'page table renders query link');
chartCheck($linkNode->getAttribute('href') === $encodedUrl, 'page link href preserves valid URL encoding');
chartCheck(str_contains($linkNode->textContent, 'кладбище-рийгикюла-возле-нарвы'), 'page link text displays decoded Cyrillic');
$pagesHeading = $pageXPath->query('//section[@class="ngk-card"]/h3//span[contains(@class,"ngk-highlight-keyword")]')->item(0);
chartCheck($pagesHeading !== null && $pagesHeading->textContent === 'кладбище', 'pages heading highlights drilled query');

echo "Line chart checks passed.\n";
