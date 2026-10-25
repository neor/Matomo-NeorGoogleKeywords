<?php
// Dedicated disposable MariaDB database ONLY. Set NGK_TEST_DSN, NGK_TEST_USER, NGK_TEST_PASSWORD.
// Uses a PDO shim to run the plugin's SQL unchanged; not a full Matomo application test.
namespace Piwik {
    class Plugin {}
    class Common { public static function prefixTable($name) { return 'test_' . $name; } public static function getRequestVar($key,$default=null,$type=null) { return $_REQUEST[$key] ?? $default; } }
    class Option {
        public static array $values=[];
        public static function get($key) { return self::$values[$key] ?? false; }
        public static function set($key,$value) { self::$values[$key]=$value; }
        public static function delete($key) { unset(self::$values[$key]); }
        public static function deleteLike($key) { self::$values=[]; }
    }
    class Db {
        public static \PDO $pdo;
        public static function get() { return self::$pdo; }
        public static function query($sql,$params=[]) { $s=self::$pdo->prepare($sql); $s->execute($params);return $s; }
        public static function fetchOne($sql,$params=[]) { return self::query($sql,$params)->fetchColumn(); }
        public static function fetchRow($sql,$params=[]) { return self::query($sql,$params)->fetch(\PDO::FETCH_ASSOC); }
        public static function fetchAll($sql,$params=[]) { return self::query($sql,$params)->fetchAll(\PDO::FETCH_ASSOC); }
    }
}
namespace {
    require_once __DIR__ . '/translations.php';
    spl_autoload_register(function ($class) {
        $p='Piwik\\Plugins\\NeorGoogleKeywords\\';
        if(str_starts_with($class,$p))require dirname(__DIR__).'/'.str_replace('\\','/',substr($class,strlen($p))).'.php';
    });
    use Piwik\Db;
    use Piwik\Plugins\NeorGoogleKeywords\NeorGoogleKeywords;
    use Piwik\Plugins\NeorGoogleKeywords\Repository;
    function check($ok,$name) { if(!$ok)throw new \Exception($name);echo "PASS $name\n"; }
    $dsn = getenv('NGK_TEST_DSN');
    if (!$dsn) die("Set a disposable NGK_TEST_DSN first.\n");
    $dbUser = getenv('NGK_TEST_USER') ?: 'root';
    $dbPass = getenv('NGK_TEST_PASSWORD') ?: '';
    Db::$pdo = new \PDO($dsn, $dbUser, $dbPass, [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_EMULATE_PREPARES => false]);
    $plugin=new NeorGoogleKeywords();$plugin->install();$repo=new Repository();$p='sc-domain:example.com';
    $row=fn($q,$c,$i,$pos,$page=null)=>['keys'=>$page===null?[$q]:[$q,$page],'clicks'=>$c,'impressions'=>$i,'position'=>$pos];
    $data=fn($rows)=>['rows'=>$rows,'capped'=>false];
    $total=fn($c,$i,$pos)=>$data([['keys'=>[],'clicks'=>$c,'impressions'=>$i,'position'=>$pos]]);
    $repo->saveDay(1,$p,'2026-01-01',$total(10,100,2),$data([$row('роза 🌹',2,10,2),$row('Rose',1,10,5),$row('rose',1,10,3)]),$data([$row('роза 🌹',2,10,2,'https://example.com/a')]));
    $repo->saveDay(1,$p,'2026-01-02',$total(20,300,6),$data([$row('роза 🌹',6,30,6)]),$data([]));
    $report=$repo->report(1,$p,'2026-01-01','2026-01-02',null,0);
    check(count($report['rows'])===3,'unicode stored, case-sensitive queries separate');
    check(abs($report['rows'][0]['position']-5)<0.00001,'position weighted by impressions');
    check(abs($report['rows'][0]['ctr']-20)<0.00001,'CTR from summed clicks and impressions');
    check((int)$report['totals']['clicks']===30,'totals independent of visible queries');
    $repo->saveDay(1,$p,'2026-01-02',$total(20,300,6),$data([$row('роза 🌹',6,30,6)]),$data([]));
    check((int)$repo->report(1,$p,'2026-01-01','2026-01-02',null,0)['totals']['clicks']===30,'re-import does not double counts');
    $before=$repo->report(1,$p,'2026-01-01','2026-01-02',null,0);
    try { $repo->saveDay(1,$p,'2026-01-01',$total(99,99,9),$data([$row('duplicate',1,1,1),$row('duplicate',1,1,1)]),$data([]));throw new \Exception('Expected rollback'); }
    catch (\Piwik\Plugins\NeorGoogleKeywords\OperationalException $e) {}
    check($repo->report(1,$p,'2026-01-01','2026-01-02',null,0)===$before,'failed SQL rolls back day replacement');
    check(count($repo->report(1,$p,'2026-01-01','2026-01-02','роза 🌹',0)['rows'])===1,'query-to-page drilldown');
    check(!$repo->report(2,$p,'2026-01-01','2026-01-02',null,0)['rows'],'site isolation');
    check(!$repo->report(1,'sc-domain:other.com','2026-01-01','2026-01-02',null,0)['rows'],'property isolation');
    check(!$repo->report(1,$p,'2026-01-01','2026-01-02',"' OR 1=1 --",0)['rows'],'SQL parameter binding');
    check($repo->lock(1),'acquire import lock');$repo->unlock(1);
    $repo->saveDay(1,$p,'2026-01-03',$data([]),$data([]),$data([]));
    check((int)$repo->report(1,$p,'2026-01-03','2026-01-03',null,0)['totals']['days']===1,'empty imported day recorded');
    $many=[];for($i=0;$i<105;$i++)$many[]=$row('q'.$i,1,10,1);
    $repo->saveDay(1,$p,'2026-01-04',$total(105,1050,1),$data($many),$data([]));
    $page1=$repo->report(1,$p,'2026-01-04','2026-01-04',null,0);$page2=$repo->report(1,$p,'2026-01-04','2026-01-04',null,100);
    check(count($page1['rows'])===100&&$page1['more']&&count($page2['rows'])===5&&!$page2['more'],'report pagination');
    $fixture=[];$pageFixture=[];
    for($i=0;$i<105;$i++) {
        $fixture[]=$row(sprintf('q%03d',$i),$i+1,106-$i,($i%7)+1);
        $pageFixture[]=$row('drill',$i+1,106-$i,($i%7)+1,sprintf('https://example.com/%03d',$i));
    }
    $repo->saveDay(1,$p,'2026-01-05',$total(1000,10000,4),$data($fixture),$data($pageFixture));
    foreach ([null,'drill'] as $query) {
        foreach (['label','clicks','impressions','ctr','position'] as $sort) {
            foreach (['asc','desc'] as $direction) {
                $first=$repo->report(1,$p,'2026-01-05','2026-01-05',$query,0,$sort,$direction);
                $second=$repo->report(1,$p,'2026-01-05','2026-01-05',$query,100,$sort,$direction);
                $actual=array_merge($first['rows'],$second['rows']);
                $expected=range(0,104);
                $value=static function($i) use($sort) {
                    return match($sort) {
                        'label'=>sprintf('%03d',$i), 'clicks'=>$i+1, 'impressions'=>106-$i,
                        'ctr'=>100*($i+1)/(106-$i), 'position'=>($i%7)+1,
                    };
                };
                usort($expected, static function($a,$b) use($value,$direction) {
                    $cmp=$value($a)<=>$value($b);
                    return ($direction==='asc'?$cmp:-$cmp) ?: ($b<=>$a);
                });
                $actualIds=array_map(static fn($r)=>(int)substr($query===null?$r['query_text']:$r['page_url'],-3),$actual);
                check($actualIds===$expected,($query===null?'queries':'pages').' globally sorted '.$sort.' '.$direction.' across 2 pages');
            }
        }
    }
    $default=$repo->report(1,$p,'2026-01-05','2026-01-05',null,0);
    $invalid=$repo->report(1,$p,'2026-01-05','2026-01-05',null,0,'clicks; DROP TABLE anything','desc; SELECT 1');
    check($default['rows']===$invalid['rows'],'invalid sort SQL identifiers safely fall back');
    $pagesRep = $repo->pagesReport(1, $p, '2026-01-05', '2026-01-05', 0);
    check(count($pagesRep['rows']) === 100 && $pagesRep['more'], 'pagesReport pagination page 1');
    check(!empty($pagesRep['rows'][0]['queries']), 'pagesReport populates queries for page');
    $pagesRep2 = $repo->pagesReport(1, $p, '2026-01-05', '2026-01-05', 100);
    check(count($pagesRep2['rows']) === 5 && !$pagesRep2['more'], 'pagesReport pagination page 2');
    $repo->saveDay(1, $p, '2026-01-06', $total(11, 100, 1), $data([]), $data([
        $row('ягода', 0, 10, 1, 'https://example.com/test-sort'),
        $row('арбуз', 0, 10, 1, 'https://example.com/test-sort'),
        $row('яблоко', 5, 10, 1, 'https://example.com/test-sort'),
        $row('груша', 5, 10, 1, 'https://example.com/test-sort'),
        $row('банан', 1, 10, 1, 'https://example.com/test-sort'),
    ]));
    $sortTest = $repo->pagesReport(1, $p, '2026-01-06', '2026-01-06', 0);
    $sortedQueries = array_column($sortTest['rows'][0]['queries'], 'query_text');
    check($sortedQueries === ['груша', 'яблоко', 'банан', 'арбуз', 'ягода'], 'keywords sorted by clicks descending then alphabetically');
    $summary=$repo->summary(1,$p,'2026-01-01','2026-01-02');
    check((int)$summary['clicks']===30 && (int)$summary['days']===2 && $summary['first_date']==='2026-01-01','overview summary uses property totals');
    check((int)$repo->summary(2,$p)['days']===0,'overview summary site isolation');
    $daily=$repo->daily(1,$p,'2026-01-01','2026-01-03');
    check(count($daily)===3 && (int)$daily[2]['clicks']===0,'daily includes imported zero days');
    $top=$repo->top(1,$p,'2026-01-05','2026-01-05');
    check(count($top)===20 && $top[0]['query_text']==='q104' && (int)$top[0]['clicks']===105,'top20 excludes page rows and ranks by clicks');
    $repo->saveConnection(1,$p,true,'siteRestrictedUser');
    check($repo->connection(1,$p)['available'] && !$repo->connection(1,'other'),'connection status bound to resource');
    for($i=0;$i<1005;$i++)$repo->log(1,'event '.$i,['status'=>'success','date'=>'2026-01-01']);
    check(count($repo->logs(1))===1000 && $repo->logs(1)[0]['message']==='event 1004','bounded detailed log newest first');
    $repo->log(1,'test');$plugin->deleteSite(1);
    check($repo->latest(1,$p)===null&&$repo->logs(1)===[],'site deletion cleanup');
    $plugin->uninstall();echo "All SQL integration tests passed.\n";
}
