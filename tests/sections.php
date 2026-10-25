<?php
// Requires the same disposable DB as database.php and MATOMO_TEST_ROOT for bundled Twig.
namespace Piwik {
    class Config {
        public array $NeorGoogleKeywords=['property_1'=>'https://example.com/'];
        public static function getInstance(): self { static $v;return $v??=new self(); }
    }
    class Piwik {
        public static function translate($key, $parameters = []) { return \ngkTestTranslate($key, $parameters); }
        public static bool $admin=true;
        public static function checkUserIsNotAnonymous() {}
        public static function checkUserHasViewAccess($id) { if($id!==1)throw new \Exception('Denied site'); }
        public static function hasUserSuperUserAccess() { return self::$admin; }
        public static function checkUserHasSuperUserAccess() { if(!self::$admin)throw new \Exception('Denied admin'); }
    }
    class Nonce {
        public static function getNonce($name) { return 'fixture-nonce'; }
        public static function checkNonce($name,$value) { if($value!=='fixture-nonce')throw new \Exception('Bad nonce'); }
    }
    class Site {
        public function __construct($id) {}
        public function getTimezone() { return 'Europe/Tallinn'; }
    }
}
namespace Piwik\Plugin {
    class Controller {
        protected $site;
        protected $idSite;
        protected $date;
        protected $strDate;
        protected function getDateParameterInTimezone($date, $timezone) { return \Piwik\Date::factory($date, $timezone); }
        protected function setDate($date) { $this->date = $date; $this->strDate = $date->toString(); }
        public static \Twig\Environment $twig;
        public static array $lastVars = [];
        protected function renderTemplate($name,array $vars=[]) { self::$lastVars=$vars; return self::$twig->render('@NeorGoogleKeywords/'.$name.'.twig',$vars); }
    }
}
namespace Piwik\Plugins\SitesManager {
    class API {
        public static function getInstance() { return new self(); }
        public function getSitesWithAtLeastViewAccess() { return [['idsite'=>1,'name'=>'Example site']]; }
    }
}
namespace {
    require __DIR__.'/database.php';
    $matomo=getenv('MATOMO_TEST_ROOT');if(!$matomo)die('Set MATOMO_TEST_ROOT');
    require $matomo.'/vendor/autoload.php';
    define('PIWIK_DOCUMENT_ROOT',$matomo);
    $filesystem=new \Twig\Loader\FilesystemLoader();
    $filesystem->addPath(dirname(__DIR__).'/templates','NeorGoogleKeywords');
    $css=file_get_contents(dirname(__DIR__).'/stylesheets/report.css');
    $loader=new \Twig\Loader\ChainLoader([new \Twig\Loader\ArrayLoader([
        'dashboard.twig'=>'<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><style>body{margin:0;background:#f5f6fa;font-family:Arial,sans-serif}{% verbatim %}'.$css.'{% endverbatim %}</style></head><body><div class="top_controls">{% block topcontrols %}{% endblock %}</div>{% block content %}{% endblock %}</body></html>'
    ]),$filesystem]);
    \Piwik\Plugin\Controller::$twig=new \Twig\Environment($loader,['autoescape'=>'html','strict_variables'=>true]);
    require_once __DIR__ . '/translations.php';
    ngkTestTwigTranslations(\Piwik\Plugin\Controller::$twig);
    $plugin=new \Piwik\Plugins\NeorGoogleKeywords\NeorGoogleKeywords();$plugin->install();
    $repo=new \Piwik\Plugins\NeorGoogleKeywords\Repository();$p='https://example.com/';
    $latest=\Piwik\Plugins\NeorGoogleKeywords\Settings::latest();
    $phrases=['уход & цветы + \"Нарва\" <test>','уборка могил нарва','кладбище силламяэ','возложение цветов','grave care estonia','kalmistu hooldus','памятник очистка','поиск захоронения','ангелио нарва','свеча на могилу','цветы хризантемы','кладбища нарвы','уход за памятником','уборка листвы','вереск на могиле','кладбище йыхви','чистка мха','могила родственника','нарва йыэсуу','песок на могилу'];
    for($i=0;$i<70;$i++) {
        $date=$latest->modify('-'.$i.' days')->format('Y-m-d');$rows=[];
        foreach($phrases as $j=>$phrase)$rows[]=['keys'=>[$phrase],'clicks'=>max(0,30-$j-$i%8),'impressions'=>100+$i*2+$j,'position'=>3+$j/4];
        $repo->saveDay(1,$p,$date,['rows'=>[['clicks'=>array_sum(array_column($rows,'clicks')),'impressions'=>array_sum(array_column($rows,'impressions')),'position'=>5]],'capped'=>false],['rows'=>$rows,'capped'=>false],['rows'=>[],'capped'=>false]);
    }
    $repo->log(1,'День сохранён.',['status'=>'success','source'=>'cron','date'=>$latest->format('Y-m-d'),'run'=>'test-run','clicks'=>3,'impressions'=>142,'queries'=>20,'pages'=>25,'seconds'=>1.8,'property'=>$p]);
    $repo->log(1,'<script>alert(1)</script>',['status'=>'error','source'=>'web','stage'=>'Поисковые запросы']);
    $_SERVER['REQUEST_METHOD']='GET';$_POST=[];
    $output = getenv('NGK_RENDER_DIR') ?: sys_get_temp_dir();
    if(!is_dir($output))mkdir($output,0700,true);
    foreach(['stream','detail','pages','import','journal','settings'] as $section) {
        $_GET=['idSite'=>'1','section'=>$section];$_REQUEST=$_GET;
        $html=(new \Piwik\Plugins\NeorGoogleKeywords\Controller())->index();
        check(substr_count($html,'class="ngk-nav-link')===6,'six topcontrol buttons on '.$section);
        check(!str_contains($html,'<script>alert'),'escaped content on '.$section);
        file_put_contents($output.'/'.$section.'.html',$html);
        if($section==='stream')check(str_contains($html,'ТОП20 Фразы квартала')&&str_contains($html,'ТОП20 Фразы недели')&&substr_count($html,'<svg')===1,'stream charts and top tables render');
        if($section==='pages')check(str_contains($html,'Страницы')&&str_contains($html,'Ключевые слова'),'pages table renders');
        if($section==='settings')check(!str_contains($html,'private_key'),'settings never renders private key');
    }
    $_GET=['idSite'=>'1','section'=>'stream'];$_REQUEST=$_GET;
    $html=(new \Piwik\Plugins\NeorGoogleKeywords\Controller())->index();
    $tops=\Piwik\Plugin\Controller::$lastVars['ngkTop'];
    check(count($tops[1]['rows'])===20 && $tops[1]['coverage']['days']==7
        && $tops[1]['from']===$latest->modify('-6 days')->format('Y-m-d')
        && $tops[1]['to']===$latest->format('Y-m-d'), 'weekly TOP20 covers latest imported day and six preceding calendar days');
    check(substr_count($html,'class="ngk-google-link"')===40
        && substr_count($html,'target="_blank" rel="noopener noreferrer"')===40, 'both TOP20 tables render safe new-tab Google links');
    foreach($tops as $top)foreach($top['rows'] as $row) {
        parse_str(parse_url($row['google_url'],PHP_URL_QUERY),$params);
        if($params!==['q'=>$row['query_text'],'ie'=>'UTF-8'])throw new \Exception('Google query encoding');
    }
    check(!str_contains($html,'<test>'), 'special query text escapes in labels, titles and links');
    $members=[];foreach($tops as $top){$members[$top['key']]=array_column($top['rows'],'query_text');sort($members[$top['key']],SORT_STRING);}
    foreach(['quarter','week'] as $table)foreach(['query_text','clicks','impressions'] as $column)foreach(['asc','desc'] as $direction) {
        $other=$table==='week'?'quarter':'week';
        $_GET=['idSite'=>'1','section'=>'stream',$table.'_sort'=>$column,$table.'_direction'=>$direction,$other.'_sort'=>'impressions',$other.'_direction'=>'asc'];$_REQUEST=$_GET;
        (new \Piwik\Plugins\NeorGoogleKeywords\Controller())->index();
        foreach(\Piwik\Plugin\Controller::$lastVars['ngkTop'] as $top) {
            $rows=$top['rows'];$names=array_column($rows,'query_text');sort($names,SORT_STRING);
            check($names===$members[$top['key']], 'TOP20 membership unchanged '.$top['key'].' '.$column.' '.$direction);
            $field=$top['key']===$table?$column:'impressions';$order=$top['key']===$table?$direction:'asc';
            for($j=1;$j<count($rows);$j++) {
                $cmp=$field==='query_text'?strcmp($rows[$j-1][$field],$rows[$j][$field]):((float)$rows[$j-1][$field]<=>(float)$rows[$j][$field]);
                if(($order==='asc'&&$cmp>0)||($order==='desc'&&$cmp<0))throw new \Exception('Wrong row order');
            }
            foreach($top['headers'] as $header) {
                parse_str(parse_url($header['url'],PHP_URL_QUERY),$params);
                if($top['key']===$table) {
                    if($params[$other.'_sort']!=='impressions'||$params[$other.'_direction']!=='asc')throw new \Exception('Lost other table state');
                    if($header['aria']!=='none' && $params[$table.'_direction']!==($direction==='asc'?'desc':'asc'))throw new \Exception('Direction did not toggle');
                }
            }
        }
        check(true,'independent sorting and header toggles '.$table.' '.$column.' '.$direction);
    }
    $_GET=['idSite'=>'1','section'=>'stream','week_sort'=>'evil','week_direction'=>'invalid'];$_REQUEST=$_GET;
    (new \Piwik\Plugins\NeorGoogleKeywords\Controller())->index();
    check(\Piwik\Plugin\Controller::$lastVars['ngkTop'][1]['headers'][1]['aria']==='descending','invalid top sort falls back to clicks descending');
    $_GET=['idSite'=>'1','section'=>'import'];$_REQUEST=$_GET;$_SERVER['REQUEST_METHOD']='POST';
    $_POST=['nonce'=>'invalid','operation'=>'import'];$before=count($repo->logs(1));
    (new \Piwik\Plugins\NeorGoogleKeywords\Controller())->index();
    check(count($repo->logs(1))===$before,'invalid nonce cannot mutate import or journal');
    $_POST=['nonce'=>'fixture-nonce','operation'=>'import','import_from'=>$latest->modify('-7 days')->format('Y-m-d'),'import_to'=>$latest->format('Y-m-d')];
    $html=(new \Piwik\Plugins\NeorGoogleKeywords\Controller())->index();
    check(str_contains($html,'максимум 7 дней') && count($repo->logs(1))===$before+1,'oversized web import rejected and logged before API');
    $_POST=[];$_SERVER['REQUEST_METHOD']='GET';
    \Piwik\Piwik::$admin=false;
    $_GET=['idSite'=>'1','section'=>'stream'];$_REQUEST=$_GET;
    $html=(new \Piwik\Plugins\NeorGoogleKeywords\Controller())->index();
    check(substr_count($html,'class="ngk-nav-link')===3,'viewers get only data sections');
    foreach(['import','journal','settings'] as $section) {
        $_GET=['idSite'=>'1','section'=>$section];$_REQUEST=$_GET;
        try {(new \Piwik\Plugins\NeorGoogleKeywords\Controller())->index();throw new \RuntimeException('Missing access check');}
        catch(\Exception $e){check($e->getMessage()==='Denied admin','protected section '.$section);}
    }
    $plugin->uninstall();echo "All section checks passed.\n";
}
