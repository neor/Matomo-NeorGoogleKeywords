<?php
// Standalone behavioural tests: php tests/unit.php. No Google keys or Matomo DB.
namespace Piwik {
    class Config {
        public array $NeorGoogleKeywords = ['property_1' => 'sc-domain:example.com'];
        public static function getInstance(): self { static $c; return $c ??= new self(); }
    }
    class Site { public function __construct($id) {} public function getName() { return 'Test'; } }
}
namespace {
    require __DIR__ . '/translations.php';
    spl_autoload_register(function ($class) {
        $prefix = 'Piwik\\Plugins\\NeorGoogleKeywords\\';
        if (str_starts_with($class, $prefix)) {
            require dirname(__DIR__) . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        }
    });
    use Piwik\Plugins\NeorGoogleKeywords\GoogleClient;
    use Piwik\Plugins\NeorGoogleKeywords\Repository;
    use Piwik\Plugins\NeorGoogleKeywords\Importer;
    use Piwik\Plugins\NeorGoogleKeywords\Settings;
    use Piwik\Plugins\NeorGoogleKeywords\OperationalException;
    function check($condition, $label) { if (!$condition) { throw new \Exception($label); } echo "PASS $label\n"; }
    function rejects(callable $call, string $label) {
        try { $call(); } catch (\Throwable $e) { echo "PASS $label\n"; return; }
        throw new \Exception('Did not reject: ' . $label);
    }
    class PagingClient extends GoogleClient {
        public array $calls = [];
        public array $responses = [];
        protected function accessToken(): string { return 'test'; }
        protected function request(string $url, ?string $body, array $headers): array {
            $this->calls[] = ['url' => $url, 'body' => json_decode($body, true)];
            return array_shift($this->responses);
        }
    }
    class FakeClient extends GoogleClient {
        public int $calls = 0;
        public bool $fail = false;
        public function day(string $p, string $d, array $dimensions): array {
            $this->calls++;
            if ($this->fail && $dimensions === ['query','page']) { throw new OperationalException('Network error'); }
            return ['rows' => [], 'capped' => false];
        }
    }
    class MemoryRepo extends Repository {
        public bool $locked = false;
        public int $saves = 0;
        public array $dates = [];
        public array $messages = [];
        public function lock(int $id): bool { if ($this->locked) return false; return $this->locked = true; }
        public function unlock(int $id): void { $this->locked = false; }
        public function saveDay(int $id, string $p, string $d, array $t, array $q, array $pages): void { $this->saves++; $this->dates[$d] = $q; }
        public function log(int $id, string $m, array $context = []): void { $this->messages[] = ['message'=>$m, 'context'=>$context]; }
    }
    rejects(fn() => Settings::date('2026-02-30'), 'reject impossible date');
    rejects(fn() => Settings::range('2026-02-02','2026-02-01'), 'reject reversed range');
    rejects(fn() => Settings::range('2025-01-01','2026-09-01'), 'bound import range');
    check(Settings::date('2024-02-29')->format('Y-m-d') === '2024-02-29', 'leap date');
    check(Settings::latest()->getTimezone()->getName() === 'America/Los_Angeles', 'Google reporting timezone');
    $googleToday = new \DateTimeImmutable('today', new \DateTimeZone('America/Los_Angeles'));
    check(Settings::latest()->format('Y-m-d') === $googleToday->modify('-2 days')->format('Y-m-d'), 'import cutoff is two days ago');
    $client = new PagingClient();
    $row = ['keys' => ['Example'], 'clicks' => 1, 'impressions' => 10, 'position' => 2];
    $client->responses = [['rows' => array_fill(0,25000,$row)],['rows' => [$row]]];
    $r = $client->day('sc-domain:example.com','2026-01-01',['query']);
    check(count($r['rows']) === 25001 && !$r['capped'], 'paginate past 25000');
    check($client->calls[1]['body']['startRow'] === 25000, 'pagination offset');
    check($client->calls[0]['body']['aggregationType'] === 'byProperty', 'query aggregation by property');
    check(str_contains($client->calls[0]['url'], 'sc-domain%3Aexample.com'), 'property URL encoded');
    $client->calls = []; $client->responses = [['rows'=>[]]];
    $client->day('https://example.com/','2026-01-01',['query','page']);
    check($client->calls[0]['body']['aggregationType'] === 'byPage', 'page aggregation kept separate');
    $client->responses = [['rows' => array_fill(0,25000,$row)],['rows' => array_fill(0,25000,$row)]];
    check($client->day('sc-domain:example.com','2026-01-01',['query'])['capped'], 'flag 50000 row cap');
    $repo = new MemoryRepo(); $fake = new FakeClient(); $import = new Importer($fake,$repo);
    check($import->run(1,'2026-01-01','2026-01-02') === 2 && $fake->calls === 6, 'three datasets per day');
    $import->run(1,'2026-01-01','2026-01-02');
    check(count($repo->dates) === 2 && !$repo->locked, 'same dates replace, lock released');
    $fake->fail = true; $before = $repo->saves;
    rejects(fn() => $import->run(1,'2026-01-03','2026-01-03'), 'API failure propagated');
    check($repo->saves === $before && !$repo->locked, 'API failure leaves stored days intact and unlocks');
    $repo->locked = true;
    rejects(fn() => $import->run(1,'2026-01-01','2026-01-01'), 'concurrent import rejected');
    rejects(fn() => $import->run(1,date('Y-m-d'),date('Y-m-d')), 'fresh date rejected');
    $errors=array_values(array_filter($repo->messages,fn($e)=>($e['context']['status']??'')==='error'));
    check(count($errors)===1 && $errors[0]['context']['stage']==='Запросы и страницы', 'failure log identifies stage');
    $saved=array_values(array_filter($repo->messages,fn($e)=>isset($e['context']['queries'])));
    check(count($saved)===4 && isset($saved[0]['context']['seconds'],$saved[0]['context']['run']), 'per-day log records metrics duration and run');
    $cutoff = Settings::latest()->format('Y-m-d');
    $boundaryImport = new Importer(new FakeClient(), new MemoryRepo());
    check($boundaryImport->run(1, $cutoff, $cutoff) === 1, 'two-day-old date can be imported');
    $tooRecent = Settings::latest()->modify('+1 day')->format('Y-m-d');
    rejects(fn() => $boundaryImport->run(1, $tooRecent, $tooRecent), 'date after cutoff is rejected');
    $ranges=\Piwik\Plugins\NeorGoogleKeywords\Overview::ranges(Settings::date('2026-09-28'));
    check($ranges['weekFrom']==='2026-09-20' && $ranges['weekTo']==='2026-09-26' && $ranges['quarterFrom']==='2026-06-27' && $ranges['quarterTo']==='2026-09-26' && $ranges['chartFrom']==='2026-07-29','rolling week, quarter and two-month boundaries');
    foreach ([
        ['2026-10-06', '2026-10-04', '2026-07-05'],
        ['2026-01-02', '2025-12-31', '2025-10-01'],
        ['2026-06-02', '2026-05-31', '2026-03-01'],
        ['2024-06-02', '2024-05-31', '2024-03-01'],
        ['2026-10-06', '2026-08-15', '2026-05-16'],
    ] as [$today, $lastImported, $expectedFrom]) {
        $rolling = \Piwik\Plugins\NeorGoogleKeywords\Overview::ranges(Settings::date($today), $lastImported);
        check($rolling['quarterFrom'] === $expectedFrom && $rolling['quarterTo'] === $lastImported,
            'three-month window anchored to ' . $lastImported . ' including month/year/leap boundaries');
    }
    $feb=\Piwik\Plugins\NeorGoogleKeywords\Overview::ranges(Settings::date('2024-04-30'));
    check($feb['chartFrom']==='2024-03-01','two-month window clamps leap-month boundary');
    $series=\Piwik\Plugins\NeorGoogleKeywords\Overview::series([['data_date'=>'2026-01-02','clicks'=>0,'impressions'=>10]],'2026-01-01','2026-01-03');
    check(count($series)===3 && $series[0]['clicks']===null && $series[1]['clicks']===0.0 && $series[1]['imported'],'missing days distinct from zero');
    $chart=\Piwik\Plugins\NeorGoogleKeywords\Overview::chart($series,'clicks');
    check($chart['max']>0 && $chart['sum']==0 && $chart['bars'][1]['height']==0,'zero-only chart has valid scale');
    check(GoogleClient::version() === trim(file_get_contents(dirname(__DIR__) . '/VERSION')), 'GoogleClient version matches VERSION file');
    echo "All behavioural tests passed.\n";
}
