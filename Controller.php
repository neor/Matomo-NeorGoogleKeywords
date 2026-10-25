<?php
namespace Piwik\Plugins\NeorGoogleKeywords;

use Piwik\Common;
use Piwik\Nonce;
use Piwik\Piwik;
use Piwik\Site;

class Controller extends \Piwik\Plugin\Controller
{
    private function input(string $name, string $default = '', bool $post = false): string
    {
        $value = ($post ? $_POST : $_GET)[$name] ?? $default;
        if (!is_string($value) || strlen($value) > 8192) {
            throw new \InvalidArgumentException(\Piwik\Piwik::translate('NeorGoogleKeywords_InvalidParameter', [$name]));
        }
        return $value;
    }

    private function link(array $params): string
    {
        $default = [
            'module' => 'NeorGoogleKeywords',
            'action' => 'index',
            'category' => 'NeorGoogleKeywords_MenuGoogleSearch',
            'subcategory' => 'NeorGoogleKeywords_Overview',
        ];
        return 'index.php?' . http_build_query(array_replace($default, $params), '', '&', PHP_QUERY_RFC3986);
    }

    private function reportDateInput(string $name, string $default, bool $post = false): string
    {
        $value = $this->input($name, $default, $post);
        $value = preg_replace('/\A([0-9]{2})-([0-9]{2})-([0-9]{4})\z/', '$3-$2-$1', $value);
        try {
            return Settings::date($value)->format('Y-m-d');
        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException(\Piwik\Piwik::translate('NeorGoogleKeywords_InvalidDateInput'));
        }
    }

    public function getViewVariables(): array
    {
        Piwik::checkUserIsNotAnonymous();
        $sites = \Piwik\Plugins\SitesManager\API::getInstance()->getSitesWithAtLeastViewAccess();
        $id = (int) Common::getRequestVar('idSite', 0, 'int');
        if ($id < 1) {
            $configuredSites = Settings::sites();
            foreach ($sites as $site) {
                if (isset($configuredSites[$site['idsite']])) {
                    $id = (int) $site['idsite'];
                    break;
                }
            }
            if ($id < 1) {
                $id = (int) ($sites[0]['idsite'] ?? 0);
            }
        }
        if ($id < 1) {
            throw new OperationalException(\Piwik\Piwik::translate('NeorGoogleKeywords_NoSites'));
        }
        Piwik::checkUserHasViewAccess($id);
        // The menu can open this page without idSite. Keep Matomo's dashboard
        // context in sync with the fallback site selected above.
        if (empty($this->site) || (int) $this->idSite !== $id) {
            $this->idSite = $id;
            $this->site = new Site($id);
            $date = Common::getRequestVar('date', 'yesterday', 'string');
            try {
                $this->setDate($this->getDateParameterInTimezone($date, $this->site->getTimezone()));
            } catch (\Exception $e) {
                // Date ranges have no single Date object, as in the base controller.
                $this->date = null;
                $this->strDate = $date;
            }
        }
        $admin = Piwik::hasUserSuperUserAccess();
        $section = $this->input('section', isset($_GET['from']) ? 'detail' : 'stream');
        $titles = ['stream' => \Piwik\Piwik::translate('NeorGoogleKeywords_Stream'), 'detail' => \Piwik\Piwik::translate('NeorGoogleKeywords_Detail'), 'pages' => \Piwik\Piwik::translate('NeorGoogleKeywords_Pages'), 'import' => \Piwik\Piwik::translate('NeorGoogleKeywords_Import'), 'journal' => \Piwik\Piwik::translate('NeorGoogleKeywords_Journal'), 'settings' => \Piwik\Piwik::translate('NeorGoogleKeywords_Settings')];
        if (!isset($titles[$section])) {
            $section = 'stream';
        }
        if (in_array($section, ['import', 'journal', 'settings'], true)) {
            Piwik::checkUserHasSuperUserAccess();
        }
        $from = $this->reportDateInput('from', Settings::latest()->modify('-27 days')->format('Y-m-d'));
        $to = $this->reportDateInput('to', Settings::latest()->format('Y-m-d'));
        [$a, $b] = Settings::range($from, $to, 3660);
        $query = isset($_GET['query']) ? $this->input('query') : null;
        $offset = max(0, min(1000000, (int) Common::getRequestVar('offset', 0, 'int')));
        $sort = $this->input('sort', 'clicks');
        if (!in_array($sort, ['label', 'clicks', 'impressions', 'ctr', 'position'], true)) {
            $sort = 'clicks';
        }
        $defaultDirection = in_array($sort, ['label', 'position'], true) ? 'asc' : 'desc';
        $direction = strtolower($this->input('direction', $defaultDirection));
        if (!in_array($direction, ['asc', 'desc'], true)) {
            $direction = $defaultDirection;
        }
        $configuredSites = Settings::sites();
        foreach ($sites as &$site) {
            $site['configured'] = isset($configuredSites[$site['idsite']]);
        }
        unset($site);
        $repo = new Repository();
        $v = [
            'ngkSection' => $section, 'ngkTitle' => $titles[$section], 'ngkId' => $id,
            'ngkCategory' => 'NeorGoogleKeywords_MenuGoogleSearch', 'ngkSubcategory' => 'NeorGoogleKeywords_Overview',
            'ngkSites' => $sites, 'ngkConfigured' => isset($configuredSites[$id]),
            'ngkConfiguredSites' => array_keys($configuredSites),
            'ngkVersion' => GoogleClient::version(),
            'ngkAdmin' => $admin, 'ngkProperty' => '',
            'ngkFrom' => $from, 'ngkTo' => $to, 'ngkQuery' => $query, 'ngkSort' => $sort, 'ngkDirection' => $direction,
            'ngkExpectedDays' => $a->diff($b)->days + 1, 'ngkLatest' => Settings::latest()->format('Y-m-d'),
            'ngkError' => '', 'ngkMessage' => '', 'ngkProperties' => [], 'ngkRows' => [], 'ngkTotals' => [], 'ngkQuickRanges' => [],
            'ngkAll' => [], 'ngkTop' => [], 'ngkCharts' => [], 'ngkDays' => [], 'ngkRanges' => Overview::ranges(),
            'ngkLogs' => [], 'ngkLogCount' => 0, 'ngkStatus' => $this->input('status'),
            'ngkLogSource' => $this->input('source'), 'ngkInfo' => [], 'ngkKeyError' => '',
            'ngkPrevious' => '', 'ngkNext' => '', 'ngkHeaders' => [],
            'ngkNonce' => $admin ? Nonce::getNonce('NeorGoogleKeywords.' . $id) : '',
            'ngkImportFrom' => $this->input('import_from', Settings::latest()->format('Y-m-d'), true),
            'ngkImportTo' => $this->input('import_to', Settings::latest()->format('Y-m-d'), true),
        ];
        foreach (['ngkImportFrom', 'ngkImportTo'] as $field) {
            $v[$field] = preg_replace('/\A([0-9]{4})-([0-9]{2})-([0-9]{2})\z/', '$3-$2-$1', $v[$field]);
        }
        $base = ['idSite' => $id, 'from' => $from, 'to' => $to, 'sort' => $sort, 'direction' => $direction];
        $v['ngkNav'] = [];
        foreach ($titles as $key => $label) {
            if ($admin || in_array($key, ['stream', 'detail', 'pages'], true)) {
                $v['ngkNav'][] = ['label' => $label, 'key' => $key, 'url' => $this->link($base + ['section' => $key])];
            }
        }
        $detail = $base + ['section' => 'detail'];
        $current = $base + ['section' => $section];
        if ($query !== null && $section === 'detail') {
            $current['query'] = $query;
        }
        $v['ngkBack'] = $this->link($detail);
        $v['ngkPost'] = $this->link($base + ['section' => 'import']);
        $v['ngkSettingsUrl'] = $this->link($base + ['section' => 'settings']);
        $v['ngkJournalUrl'] = $this->link($base + ['section' => 'journal']);
        $authorizedPost = false;
        try {
            $property = Settings::property($id);
            $v['ngkProperty'] = $property;
            if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
                Piwik::checkUserHasSuperUserAccess();
                if ($section !== 'import') {
                    throw new \InvalidArgumentException(\Piwik\Piwik::translate('NeorGoogleKeywords_ImportSectionOnly'));
                }
                Nonce::checkNonce('NeorGoogleKeywords.' . $id, $this->input('nonce', '', true));
                $authorizedPost = true;
                $operation = $this->input('operation', '', true);
                if ($operation === 'test') {
                    $v['ngkProperties'] = (new GoogleClient())->properties();
                    $found = false;
                    $permission = '';
                    foreach ($v['ngkProperties'] as $p) {
                        if (($p['siteUrl'] ?? '') === $property && ($p['permissionLevel'] ?? '') !== 'siteUnverifiedUser') {
                            $found = true;
                            $permission = (string) ($p['permissionLevel'] ?? '');
                        }
                    }
                    $v['ngkMessage'] = $found ? \Piwik\Piwik::translate('NeorGoogleKeywords_ConnectionSuccess')
                        : \Piwik\Piwik::translate('NeorGoogleKeywords_ConnectionMissingProperty', [$id]);
                    $repo->saveConnection($id, $property, $found, $permission);
                    $repo->log($id, $v['ngkMessage'], ['source' => 'web', 'status' => $found ? 'success' : 'warning', 'property' => $property]);
                } elseif ($operation === 'import') {
                    $importFrom = $this->reportDateInput('import_from', Settings::latest()->format('Y-m-d'), true);
                    $importTo = $this->reportDateInput('import_to', Settings::latest()->format('Y-m-d'), true);
                    Settings::range($importFrom, $importTo, 7);
                    $count = (new Importer())->run($id, $importFrom, $importTo, 'web');
                    $v['ngkMessage'] = \Piwik\Piwik::translate('NeorGoogleKeywords_WebImportComplete', [$count]);
                } else {
                    throw new \InvalidArgumentException(\Piwik\Piwik::translate('NeorGoogleKeywords_UnknownAction'));
                }
            }
            if ($section === 'detail') {
                $history = $repo->summary($id, $property);
                foreach (Overview::quickRanges($history['first_date'] ?? null, $history['last_date'] ?? null) as $quickRange) {
                    $quickRange['url'] = $this->link(array_replace($current, [
                        'from' => $quickRange['from'], 'to' => $quickRange['to'],
                    ]));
                    $quickRange['active'] = $from === $quickRange['from'] && $to === $quickRange['to'];
                    $v['ngkQuickRanges'][] = $quickRange;
                }
                $r = $repo->report($id, $property, $from, $to, $query, $offset, $sort, $direction);
                $v['ngkRows'] = $r['rows'];
                $v['ngkTotals'] = $r['totals'];
                foreach ($v['ngkRows'] as &$row) {
                    $row['drill_url'] = $this->link($detail + ['query' => $row['query_text']]);
                    $row['page_display'] = rawurldecode($row['page_url'] ?? '');
                }
                unset($row);
                foreach (['label' => $query === null ? \Piwik\Piwik::translate('NeorGoogleKeywords_Query') : \Piwik\Piwik::translate('NeorGoogleKeywords_Page'), 'clicks' => \Piwik\Piwik::translate('NeorGoogleKeywords_Clicks'),
                    'impressions' => \Piwik\Piwik::translate('NeorGoogleKeywords_Impressions'), 'ctr' => 'CTR', 'position' => \Piwik\Piwik::translate('NeorGoogleKeywords_Position')] as $key => $label) {
                    $active = $sort === $key;
                    $next = $active ? ($direction === 'asc' ? 'desc' : 'asc')
                        : (in_array($key, ['label', 'position'], true) ? 'asc' : 'desc');
                    $v['ngkHeaders'][] = ['label' => $label,
                        'url' => $this->link(array_replace($current, ['sort' => $key, 'direction' => $next])),
                        'aria' => $active ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none',
                        'marker' => $active ? ($direction === 'asc' ? '▲' : '▼') : '↕',
                        'title' => \Piwik\Piwik::translate($next === 'asc' ? 'NeorGoogleKeywords_SortAscending' : 'NeorGoogleKeywords_SortDescending')];
                }
                $v['ngkPrevious'] = $offset > 0 ? $this->link($current + ['offset' => max(0, $offset - 100)]) : '';
                $v['ngkNext'] = $r['more'] ? $this->link($current + ['offset' => $offset + 100]) : '';
                $daily = $repo->dailyKeywords($id, $property, $from, $to, $query);
                $v['ngkDays'] = Overview::series($daily, $from, $to, 3660);
                foreach ($v['ngkDays'] as &$day) {
                    $day['url'] = $this->link(['idSite' => $id, 'section' => 'detail', 'from' => $day['date'], 'to' => $day['date']]
                        + ($query !== null ? ['query' => $query] : []));
                }
                unset($day);
                $v['ngkCharts'] = [Overview::chart($v['ngkDays'], 'clicks'), Overview::chart($v['ngkDays'], 'impressions')];
            } elseif ($section === 'pages') {
                $history = $repo->summary($id, $property);
                foreach (Overview::quickRanges($history['first_date'] ?? null, $history['last_date'] ?? null) as $quickRange) {
                    $quickRange['url'] = $this->link(array_replace($current, [
                        'from' => $quickRange['from'], 'to' => $quickRange['to'],
                    ]));
                    $quickRange['active'] = $from === $quickRange['from'] && $to === $quickRange['to'];
                    $v['ngkQuickRanges'][] = $quickRange;
                }
                $r = $repo->pagesReport($id, $property, $from, $to, $offset, $sort, $direction);
                $v['ngkRows'] = $r['rows'];
                $v['ngkTotals'] = $r['totals'];
                foreach ($v['ngkRows'] as &$row) {
                    foreach ($row['queries'] as &$kw) {
                        $kw['drill_url'] = $this->link($detail + ['query' => $kw['query_text']]);
                    }
                    unset($kw);
                }
                unset($row);
                foreach (['label' => \Piwik\Piwik::translate('NeorGoogleKeywords_Page'),
                    'clicks' => \Piwik\Piwik::translate('NeorGoogleKeywords_Clicks')] as $key => $label) {
                    $active = $sort === $key;
                    $next = $active ? ($direction === 'asc' ? 'desc' : 'asc')
                        : (in_array($key, ['label'], true) ? 'asc' : 'desc');
                    $v['ngkHeaders'][] = ['key' => $key, 'label' => $label,
                        'url' => $this->link(array_replace($current, ['sort' => $key, 'direction' => $next])),
                        'aria' => $active ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none',
                        'marker' => $active ? ($direction === 'asc' ? '▲' : '▼') : '↕',
                        'title' => \Piwik\Piwik::translate($next === 'asc' ? 'NeorGoogleKeywords_SortAscending' : 'NeorGoogleKeywords_SortDescending')];
                }
                $v['ngkPrevious'] = $offset > 0 ? $this->link($current + ['offset' => max(0, $offset - 100)]) : '';
                $v['ngkNext'] = $r['more'] ? $this->link($current + ['offset' => $offset + 100]) : '';
            } elseif ($section === 'stream') {
                $v['ngkAll'] = $repo->summary($id, $property);
                $range = Overview::ranges(null, $v['ngkAll']['last_date'] ?? null);
                $v['ngkRanges'] = $range;
                $v['ngkDays'] = Overview::series($repo->daily($id, $property, $range['chartFrom'], $range['end']), $range['chartFrom'], $range['end']);
                foreach ($v['ngkDays'] as &$day) {
                    $day['url'] = $this->link(['idSite' => $id, 'section' => 'detail', 'from' => $day['date'], 'to' => $day['date']]);
                }
                unset($day);
                $v['ngkCharts'] = [Overview::chart($v['ngkDays'], 'clicks'), Overview::chart($v['ngkDays'], 'impressions')];
                $topState = [];
                foreach (['quarter', 'week'] as $table) {
                    $column = $this->input($table . '_sort', 'clicks');
                    if (!in_array($column, ['query_text', 'clicks', 'impressions'], true)) {
                        $column = 'clicks';
                    }
                    $default = $column === 'query_text' ? 'asc' : 'desc';
                    $order = strtolower($this->input($table . '_direction', $default));
                    $topState[$table . '_sort'] = $column;
                    $topState[$table . '_direction'] = in_array($order, ['asc', 'desc'], true) ? $order : $default;
                }
                foreach (['quarterFrom' => \Piwik\Piwik::translate('NeorGoogleKeywords_TopQuarter'), 'weekFrom' => \Piwik\Piwik::translate('NeorGoogleKeywords_TopWeek')] as $key => $title) {
                    $table = $key === 'weekFrom' ? 'week' : 'quarter';
                    $column = $topState[$table . '_sort'];
                    $order = $topState[$table . '_direction'];
                    $periodEnd = $key === 'weekFrom' ? $range['weekTo'] : $range['quarterTo'];
                    $rows = $repo->top($id, $property, $range[$key], $periodEnd);
                    // Reorder the existing leaders; ascending must not select the bottom 20.
                    usort($rows, static function ($a, $b) use ($column, $order) {
                        $comparison = $column === 'query_text'
                            ? strcmp($a['query_text'], $b['query_text'])
                            : ((float) $a[$column] <=> (float) $b[$column]);
                        return ($order === 'asc' ? $comparison : -$comparison)
                            ?: ((float) $b['clicks'] <=> (float) $a['clicks'])
                            ?: ((float) $b['impressions'] <=> (float) $a['impressions'])
                            ?: strcmp($a['query_text'], $b['query_text']);
                    });
                    foreach ($rows as &$row) {
                        $row['url'] = $this->link(['idSite' => $id, 'section' => 'detail', 'from' => $range[$key], 'to' => $periodEnd, 'query' => $row['query_text']]);
                        $row['google_url'] = 'https://www.google.com/search?' . http_build_query(
                            ['q' => $row['query_text'], 'ie' => 'UTF-8'], '', '&', PHP_QUERY_RFC3986);
                    }
                    unset($row);
                    $headers = [];
                    foreach (['query_text' => \Piwik\Piwik::translate('NeorGoogleKeywords_Query'), 'clicks' => \Piwik\Piwik::translate('NeorGoogleKeywords_Clicks'), 'impressions' => \Piwik\Piwik::translate('NeorGoogleKeywords_Impressions')] as $field => $label) {
                        $active = $column === $field;
                        $next = $active ? ($order === 'asc' ? 'desc' : 'asc') : ($field === 'query_text' ? 'asc' : 'desc');
                        $headers[] = ['label' => $label,
                            'url' => $this->link(array_replace($current, $topState,
                                [$table . '_sort' => $field, $table . '_direction' => $next])) . '#ngk-top-' . $table,
                            'aria' => $active ? ($order === 'asc' ? 'ascending' : 'descending') : 'none',
                            'marker' => $active ? ($order === 'asc' ? '▲' : '▼') : '↕',
                            'title' => \Piwik\Piwik::translate($next === 'asc' ? 'NeorGoogleKeywords_SortAscending' : 'NeorGoogleKeywords_SortDescending')];
                    }
                    $v['ngkTop'][] = ['key' => $table, 'headers' => $headers, 'title' => $title, 'from' => $range[$key], 'to' => $periodEnd,
                        'rows' => $rows, 'coverage' => $repo->summary($id, $property, $range[$key], $periodEnd)];
                }
            }
        } catch (\Throwable $e) {
            $v['ngkError'] = $e instanceof OperationalException || $e instanceof \InvalidArgumentException
                ? $e->getMessage() : \Piwik\Piwik::translate('NeorGoogleKeywords_OperationFailed');
            if ($authorizedPost) {
                $repo->log($id, \Piwik\Piwik::translate('NeorGoogleKeywords_WebActionFailed', [$v['ngkError']]),
                    ['status' => 'error', 'source' => 'web', 'property' => $v['ngkProperty']]);
            }
        }
        // Generate a fresh nonce after a POST, as Matomo discards the previous one on validation.
        $v['ngkNonce'] = $admin ? Nonce::getNonce('NeorGoogleKeywords.' . $id) : '';
        if ($section === 'journal') {
            $logs = $repo->logs($id);
            $logs = array_values(array_filter($logs, static fn($entry) =>
                ($v['ngkStatus'] === '' || ($entry['context']['status'] ?? 'legacy') === $v['ngkStatus'])
                && ($v['ngkLogSource'] === '' || ($entry['context']['source'] ?? '') === $v['ngkLogSource'])));
            $v['ngkLogCount'] = count($logs);
            $v['ngkLogs'] = array_slice($logs, $offset, 50);
            $logParams = $current + ['status' => $v['ngkStatus'], 'source' => $v['ngkLogSource']];
            $v['ngkPrevious'] = $offset > 0 ? $this->link($logParams + ['offset' => max(0, $offset - 50)]) : '';
            $v['ngkNext'] = count($logs) > $offset + 50 ? $this->link($logParams + ['offset' => $offset + 50]) : '';
        } elseif ($section === 'settings') {
            $v['ngkInfo'] = ['file' => (string) (Settings::all()['credentials_file'] ?? ''),
                'project' => '', 'email' => '', 'googleUrl' => 'https://console.cloud.google.com/apis/library/searchconsole.googleapis.com',
                'connection' => $repo->connection($id, $v['ngkProperty'])];
            try {
                $key = Settings::credentials();
                $v['ngkInfo']['project'] = (string) ($key['project_id'] ?? '');
                $v['ngkInfo']['email'] = (string) $key['client_email'];
                $v['ngkInfo']['googleUrl'] .= '?' . http_build_query(['project' => $v['ngkInfo']['project']]);
                unset($key);
            } catch (\Throwable $e) {
                $v['ngkKeyError'] = $e instanceof OperationalException ? $e->getMessage() : \Piwik\Piwik::translate('NeorGoogleKeywords_KeySettingsFailed');
            }
        }
        return $v;
    }

    public function index()
    {
        $v = $this->getViewVariables();
        $v['showMenu'] = true;
        return $this->renderTemplate('index', $v);
    }

    public function overviewReport()
    {
        $v = $this->getViewVariables();
        $view = new \Piwik\View('@NeorGoogleKeywords/report');
        foreach ($v as $key => $value) {
            $view->$key = $value;
        }
        return $view->render();
    }
}
