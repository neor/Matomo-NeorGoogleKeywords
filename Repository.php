<?php
namespace Piwik\Plugins\NeorGoogleKeywords;

use Piwik\Common;
use Piwik\Db;
use Piwik\Option;

class Repository
{
    private function table(string $suffix): string
    {
        return '`' . Common::prefixTable('neor_gkeys_' . $suffix) . '`';
    }

    public function lock(int $id): bool
    {
        return (int) Db::fetchOne('SELECT GET_LOCK(?, 0)', [$this->lockName($id)]) === 1;
    }

    public function unlock(int $id): void
    {
        Db::fetchOne('SELECT RELEASE_LOCK(?)', [$this->lockName($id)]);
    }

    private function lockName(int $id): string
    {
        return 'neor_gkeys:' . substr(hash('sha256', Common::prefixTable('neor_gkeys_rows')), 0, 32) . ':' . $id;
    }

    public function latest(int $id, string $property): ?string
    {
        $d = Db::fetchOne('SELECT MAX(data_date) FROM ' . $this->table('days')
            . ' WHERE idsite = ? AND property_hash = ?', [$id, hash('sha256', $property)]);
        return $d ? (string) $d : null;
    }

    public function saveDay(int $id, string $property, string $date, array $total, array $queries, array $pages): void
    {
        $hash = hash('sha256', $property);
        $db = Db::get();
        $db->beginTransaction();
        try {
            Db::query('DELETE FROM ' . $this->table('rows') . ' WHERE idsite=? AND property_hash=? AND data_date=?', [$id, $hash, $date]);
            foreach ([1 => $queries['rows'], 2 => $pages['rows']] as $kind => $rows) {
                // Batches reduce round trips while bounding placeholders and packet size.
                foreach (array_chunk($rows, 100) as $batch) {
                    $params = [];
                    $values = [];
                    foreach ($batch as $row) {
                        $query = (string) $row['keys'][0];
                        $page = (string) ($row['keys'][1] ?? '');
                        $values[] = '(?,?,?,?,?,?,?,?,?,?)';
                        array_push($params, $id, $hash, $date, $kind,
                            hash('sha256', json_encode([$query, $page], JSON_THROW_ON_ERROR)),
                            $query, $page, (float) $row['clicks'], (float) $row['impressions'],
                            (float) $row['position'] * (float) $row['impressions']);
                    }
                    Db::query('INSERT INTO ' . $this->table('rows')
                        . ' (idsite,property_hash,data_date,kind,row_hash,query_text,page_url,clicks,impressions,position_sum) VALUES '
                        . implode(',', $values), $params);
                }
            }
            $row = $total['rows'][0] ?? ['clicks' => 0, 'impressions' => 0, 'position' => 0];
            Db::query('REPLACE INTO ' . $this->table('days')
                . ' (idsite,property_hash,data_date,clicks,impressions,position_sum,capped,imported_at) VALUES (?,?,?,?,?,?,?,?)',
                [$id, $hash, $date, $row['clicks'], $row['impressions'], $row['position'] * $row['impressions'],
                    (int) ($queries['capped'] || $pages['capped']), gmdate('Y-m-d H:i:s')]);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw new OperationalException(\Piwik\Piwik::translate('NeorGoogleKeywords_SaveDayFailed'), 0, $e);
        }
    }

    public function report(int $id, string $property, string $from, string $to, ?string $query, int $offset, string $sort = 'clicks', string $direction = 'desc'): array
    {
        $params = [$id, hash('sha256', $property), $from, $to, $query === null ? 1 : 2];
        $where = 'idsite=? AND property_hash=? AND data_date BETWEEN ? AND ? AND kind=?';
        if ($query !== null) {
            $where .= ' AND query_text=?';
            $params[] = $query;
        }
        // Only fixed column aliases and directions may enter ORDER BY.
        $columns = ['label' => $query === null ? 'query_text' : 'page_url',
            'clicks' => 'clicks', 'impressions' => 'impressions', 'ctr' => 'ctr', 'position' => 'position'];
        $column = $columns[$sort] ?? 'clicks';
        $direction = strtolower($direction) === 'asc' ? 'ASC' : 'DESC';
        $nullOrder = in_array($column, ['ctr', 'position'], true) ? 'SUM(impressions)=0 ASC, ' : '';
        $order = $nullOrder . $column . ' ' . $direction
            . ', clicks DESC, impressions DESC, row_hash ASC';
        $rows = Db::fetchAll('SELECT row_hash, MAX(query_text) AS query_text, MAX(page_url) AS page_url,
            SUM(clicks) AS clicks, SUM(impressions) AS impressions,
            SUM(position_sum)/NULLIF(SUM(impressions),0) AS position,
            100*SUM(clicks)/NULLIF(SUM(impressions),0) AS ctr
            FROM ' . $this->table('rows') . ' WHERE ' . $where
            . ' GROUP BY row_hash ORDER BY ' . $order . ' LIMIT 101 OFFSET ' . max(0, $offset), $params);
        $more = count($rows) > 100;
        $rows = array_slice($rows, 0, 100);
        $totals = Db::fetchRow('SELECT SUM(clicks) AS clicks, SUM(impressions) AS impressions,
            SUM(position_sum)/NULLIF(SUM(impressions),0) AS position,
            100*SUM(clicks)/NULLIF(SUM(impressions),0) AS ctr,
            COUNT(*) AS days, MAX(capped) AS capped, MAX(imported_at) AS imported_at
            FROM ' . $this->table('days') . ' WHERE idsite=? AND property_hash=? AND data_date BETWEEN ? AND ?',
            [$id, hash('sha256', $property), $from, $to]);
        return ['rows' => $rows, 'more' => $more, 'totals' => $totals];
    }

    public function pagesReport(int $id, string $property, string $from, string $to, int $offset, string $sort = 'clicks', string $direction = 'desc'): array
    {
        $params = [$id, hash('sha256', $property), $from, $to];
        $where = 'idsite=? AND property_hash=? AND data_date BETWEEN ? AND ? AND kind=2 AND page_url != ""';
        $columns = [
            'label' => 'page_url',
            'clicks' => 'clicks',
            'impressions' => 'impressions',
            'ctr' => 'ctr',
            'position' => 'position',
        ];
        $column = $columns[$sort] ?? 'clicks';
        $direction = strtolower($direction) === 'asc' ? 'ASC' : 'DESC';
        $nullOrder = in_array($column, ['ctr', 'position'], true) ? 'SUM(impressions)=0 ASC, ' : '';
        $order = $nullOrder . $column . ' ' . $direction . ', clicks DESC, impressions DESC, page_url ASC';

        $rows = Db::fetchAll('SELECT page_url,
            SUM(clicks) AS clicks,
            SUM(impressions) AS impressions,
            100*SUM(clicks)/NULLIF(SUM(impressions),0) AS ctr,
            SUM(position_sum)/NULLIF(SUM(impressions),0) AS position
            FROM ' . $this->table('rows') . ' WHERE ' . $where
            . ' GROUP BY page_url ORDER BY ' . $order . ' LIMIT 101 OFFSET ' . max(0, $offset), $params);

        $more = count($rows) > 100;
        $rows = array_slice($rows, 0, 100);

        if (!empty($rows)) {
            $urls = array_column($rows, 'page_url');
            $placeholders = implode(',', array_fill(0, count($urls), '?'));
            $queryParams = array_merge([$id, hash('sha256', $property), $from, $to], $urls);
            $queryRows = Db::fetchAll('SELECT page_url, query_text,
                SUM(clicks) AS clicks,
                SUM(impressions) AS impressions
                FROM ' . $this->table('rows') . '
                WHERE idsite=? AND property_hash=? AND data_date BETWEEN ? AND ? AND kind=2
                  AND page_url IN (' . $placeholders . ')
                GROUP BY page_url, query_text
                ORDER BY clicks DESC, query_text ASC', $queryParams);

            $queriesByPage = [];
            foreach ($queryRows as $qr) {
                $queriesByPage[$qr['page_url']][] = [
                    'query_text' => $qr['query_text'],
                    'clicks' => (float) $qr['clicks'],
                    'impressions' => (float) $qr['impressions'],
                ];
            }

            foreach ($queriesByPage as &$qList) {
                usort($qList, static function ($a, $b) {
                    $cmp = ((float) $b['clicks'] <=> (float) $a['clicks']);
                    if ($cmp !== 0) {
                        return $cmp;
                    }
                    if (class_exists(\Collator::class)) {
                        static $collator;
                        if ($collator === null) {
                            $collator = new \Collator('ru_RU');
                        }
                        $colCmp = $collator->compare($a['query_text'], $b['query_text']);
                        if ($colCmp !== 0) {
                            return $colCmp;
                        }
                    }
                    return strcmp(mb_strtolower($a['query_text'], 'UTF-8'), mb_strtolower($b['query_text'], 'UTF-8'))
                        ?: strcmp($a['query_text'], $b['query_text']);
                });
            }
            unset($qList);

            foreach ($rows as &$row) {
                $row['page_display'] = rawurldecode($row['page_url']);
                $row['queries'] = $queriesByPage[$row['page_url']] ?? [];
            }
            unset($row);
        }

        $totals = Db::fetchRow('SELECT SUM(clicks) AS clicks, SUM(impressions) AS impressions,
            SUM(position_sum)/NULLIF(SUM(impressions),0) AS position,
            100*SUM(clicks)/NULLIF(SUM(impressions),0) AS ctr,
            COUNT(*) AS days, MAX(capped) AS capped, MAX(imported_at) AS imported_at
            FROM ' . $this->table('days') . ' WHERE idsite=? AND property_hash=? AND data_date BETWEEN ? AND ?',
            [$id, hash('sha256', $property), $from, $to]);

        return ['rows' => $rows, 'more' => $more, 'totals' => $totals];
    }

    public function summary(int $id, string $property, ?string $from = null, ?string $to = null): array
    {
        $where = 'idsite=? AND property_hash=?';
        $params = [$id, hash('sha256', $property)];
        if ($from !== null && $to !== null) {
            $where .= ' AND data_date BETWEEN ? AND ?';
            array_push($params, $from, $to);
        }
        return Db::fetchRow('SELECT COALESCE(SUM(clicks),0) AS clicks,
            COALESCE(SUM(impressions),0) AS impressions,
            100*SUM(clicks)/NULLIF(SUM(impressions),0) AS ctr,
            SUM(position_sum)/NULLIF(SUM(impressions),0) AS position,
            COUNT(*) AS days, MIN(data_date) AS first_date, MAX(data_date) AS last_date,
            MAX(imported_at) AS imported_at, MAX(capped) AS capped
            FROM ' . $this->table('days') . ' WHERE ' . $where, $params);
    }

    public function daily(int $id, string $property, string $from, string $to): array
    {
        return Db::fetchAll('SELECT data_date, clicks, impressions, capped FROM ' . $this->table('days')
            . ' WHERE idsite=? AND property_hash=? AND data_date BETWEEN ? AND ? ORDER BY data_date',
            [$id, hash('sha256', $property), $from, $to]);
    }

    public function dailyKeywords(int $id, string $property, string $from, string $to, ?string $query = null): array
    {
        $hash = hash('sha256', $property);
        $days = Db::fetchAll('SELECT data_date, clicks, impressions, capped FROM ' . $this->table('days')
            . ' WHERE idsite=? AND property_hash=? AND data_date BETWEEN ? AND ? ORDER BY data_date',
            [$id, $hash, $from, $to]);
        if (empty($days)) {
            return [];
        }
        $params = [$id, $hash, $from, $to];
        $queryWhere = '';
        if ($query !== null) {
            $queryWhere = ' AND query_text = ?';
            $params[] = $query;
        }
        $queryRows = Db::fetchAll('SELECT data_date, SUM(clicks) AS clicks, SUM(impressions) AS impressions
            FROM ' . $this->table('rows')
            . ' WHERE idsite=? AND property_hash=? AND data_date BETWEEN ? AND ? AND kind=1' . $queryWhere
            . ' GROUP BY data_date', $params);
        $sums = [];
        foreach ($queryRows as $qr) {
            $sums[$qr['data_date']] = $qr;
        }
        $result = [];
        foreach ($days as $day) {
            $date = $day['data_date'];
            $kw = $sums[$date] ?? null;
            $result[] = [
                'data_date' => $date,
                'clicks' => $kw !== null ? (float) $kw['clicks'] : 0.0,
                'impressions' => $kw !== null ? (float) $kw['impressions'] : 0.0,
                'capped' => (int) $day['capped'],
            ];
        }
        return $result;
    }

    public function top(int $id, string $property, string $from, string $to): array
    {
        return Db::fetchAll('SELECT MAX(query_text) AS query_text, SUM(clicks) AS clicks,
            SUM(impressions) AS impressions FROM ' . $this->table('rows')
            . ' WHERE idsite=? AND property_hash=? AND data_date BETWEEN ? AND ? AND kind=1
            GROUP BY row_hash ORDER BY clicks DESC, impressions DESC, row_hash ASC LIMIT 20',
            [$id, hash('sha256', $property), $from, $to]);
    }

    public function saveConnection(int $id, string $property, bool $available, string $permission): void
    {
        Option::set('NeorGoogleKeywords.connection.' . $id, json_encode([
            'property' => $property, 'available' => $available, 'permission' => $permission,
            'time' => gmdate('Y-m-d H:i:s'),
        ], JSON_THROW_ON_ERROR));
    }

    public function connection(int $id, string $property): array
    {
        $raw = Option::get('NeorGoogleKeywords.connection.' . $id) ?: Option::get('GoogleSearchConsoleLite.connection.' . $id);
        $value = json_decode((string) $raw, true);
        return is_array($value) && ($value['property'] ?? '') === $property ? $value : [];
    }

    public function log(int $id, string $message, array $context = []): void
    {
        $log = $this->logs($id);
        // Context is constructed by the importer, never from Google response bodies or keys.
        array_unshift($log, ['time' => gmdate('Y-m-d H:i:s'), 'message' => $message,
            'context' => $context]);
        Option::set('NeorGoogleKeywords.log.' . $id, json_encode(array_slice($log, 0, 1000), JSON_THROW_ON_ERROR));
    }

    public function logs(int $id): array
    {
        $raw = Option::get('NeorGoogleKeywords.log.' . $id) ?: Option::get('GoogleSearchConsoleLite.log.' . $id);
        $value = json_decode((string) $raw, true);
        return is_array($value) ? $value : [];
    }
}
