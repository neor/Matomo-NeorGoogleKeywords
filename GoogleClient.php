<?php
namespace Piwik\Plugins\NeorGoogleKeywords;

class GoogleClient
{
    private string $token = '';
    private int $expires = 0;
    private ?int $idSite = null;

    public function setSite(int $idSite): void
    {
        if ($this->idSite !== $idSite) {
            // A token must not carry over to another website's service account.
            $this->idSite = $idSite;
            $this->token = '';
            $this->expires = 0;
        }
    }

    public static function version(): string
    {
        static $version = null;
        if ($version === null) {
            $file = __DIR__ . '/VERSION';
            $version = is_readable($file) ? trim((string) file_get_contents($file)) : '';
            if ($version === '') {
                $version = '1.0.0';
            }
        }
        return $version;
    }

    protected function request(string $url, ?string $body, array $headers): array
    {
        if (!extension_loaded('curl') || !extension_loaded('openssl')) {
            throw new OperationalException(\Piwik\Piwik::translate('NeorGoogleKeywords_ExtensionsRequired'));
        }
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 45,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_USERAGENT => 'Matomo-NeorGoogleKeywords/' . self::version(),
            ]);
            if ($body !== null) {
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
            }
            $raw = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            if (($status === 429 || $status >= 500 || $raw === false) && $attempt < 2) {
                usleep((2 ** $attempt) * 1000000);
                continue;
            }
            if ($raw === false) {
                throw new OperationalException(\Piwik\Piwik::translate('NeorGoogleKeywords_GoogleConnectionFailed'));
            }
            $data = json_decode($raw, true);
            if ($status < 200 || $status >= 300) {
                $hints = [
                    400 => \Piwik\Piwik::translate('NeorGoogleKeywords_GoogleBadRequest'),
                    401 => \Piwik\Piwik::translate('NeorGoogleKeywords_GoogleUnauthorized'),
                    403 => \Piwik\Piwik::translate('NeorGoogleKeywords_GoogleForbidden'),
                    404 => \Piwik\Piwik::translate('NeorGoogleKeywords_GoogleNotFound'),
                    429 => \Piwik\Piwik::translate('NeorGoogleKeywords_GoogleQuota')
                ];
                // Never expose raw Google errors, credentials, JWTs or tokens.
                throw new OperationalException(\Piwik\Piwik::translate('NeorGoogleKeywords_GoogleHttpError', [$status, $hints[$status] ?? \Piwik\Piwik::translate('NeorGoogleKeywords_RetryImport')]));
            }
            if (!is_array($data)) {
                throw new OperationalException(\Piwik\Piwik::translate('NeorGoogleKeywords_GoogleInvalidJson'));
            }
            return $data;
        }
        throw new OperationalException(\Piwik\Piwik::translate('NeorGoogleKeywords_GoogleUnavailable'));
    }

    private static function base64url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    protected function accessToken(): string
    {
        if ($this->expires > time() + 60) {
            return $this->token;
        }
        $key = Settings::credentials($this->idSite);
        $now = time();
        $header = self::base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $claims = self::base64url(json_encode([
            'iss' => $key['client_email'],
            'scope' => 'https://www.googleapis.com/auth/webmasters.readonly',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_THROW_ON_ERROR));
        $input = $header . '.' . $claims;
        if (!openssl_sign($input, $signature, $key['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new OperationalException(\Piwik\Piwik::translate('NeorGoogleKeywords_SigningFailed'));
        }
        $response = $this->request('https://oauth2.googleapis.com/token', http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $input . '.' . self::base64url($signature),
        ]), ['Content-Type: application/x-www-form-urlencoded']);
        if (empty($response['access_token'])) {
            throw new OperationalException(\Piwik\Piwik::translate('NeorGoogleKeywords_GoogleMissingToken'));
        }
        $this->token = (string) $response['access_token'];
        $this->expires = $now + (int) ($response['expires_in'] ?? 3600);
        return $this->token;
    }

    public function properties(): array
    {
        $r = $this->request(
            'https://www.googleapis.com/webmasters/v3/sites',
            null,
            ['Authorization: Bearer ' . $this->accessToken()]
        );
        return $r['siteEntry'] ?? [];
    }

    public function day(string $property, string $date, array $dimensions): array
    {
        $all = [];
        $limit = 25000;
        for ($offset = 0; $offset < 50000; $offset += $limit) {
            $response = $this->request('https://www.googleapis.com/webmasters/v3/sites/'
                . rawurlencode($property) . '/searchAnalytics/query', json_encode([
                    'startDate' => $date,
                    'endDate' => $date,
                    'type' => 'web',
                    'dataState' => 'final',
                    'dimensions' => $dimensions,
                    'aggregationType' => in_array('page', $dimensions, true) ? 'byPage' : 'byProperty',
                    'rowLimit' => $limit,
                    'startRow' => $offset,
                ], JSON_THROW_ON_ERROR), ['Content-Type: application/json', 'Authorization: Bearer ' . $this->accessToken()]);
            $rows = $response['rows'] ?? [];
            if (!is_array($rows)) {
                throw new OperationalException(\Piwik\Piwik::translate('NeorGoogleKeywords_GoogleInvalidRows'));
            }
            foreach ($rows as $row) {
                if (
                    !isset($row['clicks'], $row['impressions'], $row['position'])
                    || count($row['keys'] ?? []) !== count($dimensions)
                ) {
                    throw new OperationalException(\Piwik\Piwik::translate('NeorGoogleKeywords_GoogleInvalidMetrics'));
                }
                $all[] = $row;
            }
            if (count($rows) < $limit) {
                return ['rows' => $all, 'capped' => false];
            }
        }
        return ['rows' => $all, 'capped' => true];
    }
}
