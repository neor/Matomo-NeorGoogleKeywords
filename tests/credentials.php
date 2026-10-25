<?php
// Synthetic key only. No real Google credentials, database or network calls.
namespace Piwik {
    class Config {
        public array $NeorGoogleKeywords = [];
        public static function getInstance(): self { static $config; return $config ??= new self(); }
    }
}
namespace {
    require __DIR__ . '/translations.php';
    use Piwik\Plugins\NeorGoogleKeywords\Settings;
    use Piwik\Plugins\NeorGoogleKeywords\OperationalException;

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
