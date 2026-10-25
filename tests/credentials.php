<?php
// Synthetic key only. No real Google credentials, database or network calls.
namespace Piwik {
    class Config {
        public array $NeorGoogleKeywords = [];
        public static function getInstance(): self { static $config; return $config ??= new self(); }
    }
    class Site {
        public function __construct($id) {}
        public function getName() { return 'Fixture site'; }
    }
}
namespace {
    require __DIR__ . '/translations.php';
    use Piwik\Plugins\NeorGoogleKeywords\Settings;
    use Piwik\Plugins\NeorGoogleKeywords\OperationalException;
    use Piwik\Plugins\NeorGoogleKeywords\GoogleClient;
    use Piwik\Plugins\NeorGoogleKeywords\Importer;
    use Piwik\Plugins\NeorGoogleKeywords\Repository;

    class CredentialClient extends GoogleClient
    {
        public array $issuers = [];
        public array $authorizations = [];

        protected function request(string $url, ?string $body, array $headers): array
        {
            if ($url === 'https://oauth2.googleapis.com/token') {
                parse_str($body, $form);
                $parts = explode('.', $form['assertion']);
                $claims = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true, 512, JSON_THROW_ON_ERROR);
                $this->issuers[] = $claims['iss'];
                return ['access_token' => 'fixture-token-' . $claims['iss'], 'expires_in' => 3600];
            }
            $this->authorizations[] = $headers;
            return ['siteEntry' => [], 'rows' => []];
        }
    }

    class CredentialRepository extends Repository
    {
        public array $savedSites = [];
        public function lock(int $id): bool { return true; }
        public function unlock(int $id): void {}
        public function log(int $id, string $message, array $context = []): void {}
        public function saveDay(int $id, string $property, string $date, array $total, array $queries, array $pages): void
        {
            $this->savedSites[] = $id;
        }
    }

    function credentialsCheck(bool $condition, string $message): void {
        if (!$condition) { throw new RuntimeException($message); }
        echo "PASS $message\n";
    }
    function credentialsReject($path, string $key, string $message): void {
        \Piwik\Config::getInstance()->NeorGoogleKeywords['credentials_file'] = $path;
        try {
            Settings::credentials();
        } catch (OperationalException $e) {
            credentialsCheck($e->getMessage() === ngkTestTranslate('NeorGoogleKeywords_' . $key), $message);
            return;
        }
        throw new RuntimeException($message);
    }

    $fixture = sys_get_temp_dir() . '/ngk-credentials-' . bin2hex(random_bytes(8));
    $directories = [$fixture, $fixture . '/matomo', $fixture . '/web', $fixture . '/matomo-sibling'];
    $files = [];
    $oldDocumentRoot = $_SERVER['DOCUMENT_ROOT'] ?? null;
    try {
        foreach ($directories as $directory) { mkdir($directory); }
        define('PIWIK_DOCUMENT_ROOT', $fixture . '/matomo');
        $_SERVER['DOCUMENT_ROOT'] = $fixture . '/web';
        $fakeKey = ['type' => 'service_account', 'client_email' => 'fixture@example.invalid', 'private_key' => 'synthetic-not-a-real-key'];
        foreach (['key.json', 'matomo/key.json', 'web/key.json', 'matomo-sibling/key.json'] as $name) {
            $path = $fixture . '/' . $name;
            $files[] = $path;
            file_put_contents($path, json_encode($fakeKey));
        }
        foreach ([$fixture . '/key.json', $fixture . '/matomo-sibling/key.json'] as $path) {
            \Piwik\Config::getInstance()->NeorGoogleKeywords['credentials_file'] = $path;
            credentialsCheck(Settings::credentials() === $fakeKey, 'absolute path outside web roots accepted');
        }
        if (DIRECTORY_SEPARATOR === '\\') {
            foreach ([str_replace('/', '\\', $fixture . '/key.json'), strtoupper($fixture . '/key.json')] as $path) {
                \Piwik\Config::getInstance()->NeorGoogleKeywords['credentials_file'] = $path;
                credentialsCheck(Settings::credentials() === $fakeKey, 'Windows separators and path case accepted');
            }
            $_SERVER['DOCUMENT_ROOT'] = strtoupper(str_replace('/', '\\', $fixture . '/web'));
        }
        credentialsReject($fixture . '/matomo/key.json', 'KeyOutsideWebRoot', 'key inside Matomo rejected');
        credentialsReject($fixture . '/web/key.json', 'KeyOutsideWebRoot', 'key inside document root rejected across case and separators');
        credentialsReject($fixture . '/matomo/../web/key.json', 'KeyOutsideWebRoot', 'resolved traversal into web root rejected');
        foreach (['key.json', '', null, [], $fixture . '/missing.json', $fixture] as $path) {
            credentialsReject($path, 'KeyUnavailable', 'relative, invalid, missing or directory path rejected');
        }
        $config = \Piwik\Config::getInstance();
        $config->NeorGoogleKeywords = [
            'credentials_file' => $fixture . '/key.json',
            'credentials_file_7' => $fixture . '/matomo-sibling/key.json',
        ];
        credentialsCheck(Settings::credentialsFile(7) === $fixture . '/matomo-sibling/key.json', 'site override selected');
        credentialsCheck(Settings::credentialsFile(10) === $fixture . '/key.json', 'site without override uses shared key');
        credentialsCheck(Settings::credentialsFile() === $fixture . '/key.json', 'existing no-site callers keep shared key');
        foreach (['', null, [], $fixture . '/missing.json', $fixture . '/web/key.json'] as $invalidOverride) {
            $config->NeorGoogleKeywords['credentials_file_7'] = $invalidOverride;
            try {
                Settings::credentials(7);
                throw new RuntimeException('Invalid site override accepted');
            } catch (OperationalException $e) {
                credentialsCheck(true, 'invalid site override rejected without shared-key fallback');
            }
        }

        $signingKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        credentialsCheck($signingKey !== false && openssl_pkey_export($signingKey, $privateKey), 'temporary signing fixture created');
        $sharedKey = $fakeKey + ['project_id' => 'fixture-shared'];
        $sharedKey['private_key'] = $privateKey;
        $siteKey = $sharedKey;
        $siteKey['client_email'] = 'site-seven@example.invalid';
        $siteKey['project_id'] = 'fixture-seven';
        file_put_contents($fixture . '/key.json', json_encode($sharedKey));
        file_put_contents($fixture . '/matomo-sibling/key.json', json_encode($siteKey));
        $config->NeorGoogleKeywords = [
            'credentials_file' => $fixture . '/key.json',
            'credentials_file_7' => $fixture . '/matomo-sibling/key.json',
            'property_7' => 'sc-domain:seven.example',
            'property_10' => 'sc-domain:ten.example',
        ];
        credentialsCheck(Settings::credentials(7)['client_email'] === $siteKey['client_email'], 'site-specific key contents loaded');
        credentialsCheck(Settings::credentials(10)['client_email'] === $sharedKey['client_email'], 'shared fallback key contents loaded');
        $client = new CredentialClient();
        $client->setSite(7);
        $client->properties();
        $client->setSite(7);
        $client->properties();
        credentialsCheck($client->issuers === [$siteKey['client_email']], 'same-site connection checks reuse the token');
        $client->setSite(10);
        $client->properties();
        $client->setSite(7);
        $client->properties();
        credentialsCheck($client->issuers === [$siteKey['client_email'], $sharedKey['client_email'], $siteKey['client_email']],
            'switching sites authenticates with the correct account each time');
        credentialsCheck(in_array('Authorization: Bearer fixture-token-' . $sharedKey['client_email'], $client->authorizations[2], true),
            'API request uses the newly selected account token');

        $repository = new CredentialRepository();
        $client = new CredentialClient();
        $importer = new Importer($client, $repository);
        $config->NeorGoogleKeywords['credentials_file_10'] = $fixture . '/key.json';
        unset($config->NeorGoogleKeywords['credentials_file']);
        $date = Settings::latest()->format('Y-m-d');
        $importer->run(7, $date, $date, 'cron');
        $importer->run(10, $date, $date, 'cron');
        credentialsCheck($repository->savedSites === [7, 10]
            && $client->issuers === [$siteKey['client_email'], $sharedKey['client_email']],
            'consecutive imports with a shared client select site-specific credentials');
        credentialsCheck(Settings::credentials(7)['client_email'] === $siteKey['client_email'], 'site key works without a shared key');
        unset($config->NeorGoogleKeywords['credentials_file_10']);
        try {
            Settings::credentials(10);
            throw new RuntimeException('Missing shared and site key accepted');
        } catch (OperationalException $e) {
            credentialsCheck(true, 'missing shared and site key rejected');
        }
        $config->NeorGoogleKeywords = [];
        file_put_contents($fixture . '/key.json', '{}');
        credentialsReject($fixture . '/key.json', 'ServiceAccountKeyRequired', 'invalid key format still rejected');
    } finally {
        foreach ($files as $file) { if (is_file($file)) { unlink($file); } }
        foreach (array_reverse($directories) as $directory) { if (is_dir($directory)) { rmdir($directory); } }
        if ($oldDocumentRoot === null) { unset($_SERVER['DOCUMENT_ROOT']); }
        else { $_SERVER['DOCUMENT_ROOT'] = $oldDocumentRoot; }
    }
    echo "Credentials checks passed.\n";
}
