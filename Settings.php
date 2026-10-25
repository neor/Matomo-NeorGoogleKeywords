<?php
namespace Piwik\Plugins\NeorGoogleKeywords;

final class Settings
{
    public static function all(): array
    {
        $config = \Piwik\Config::getInstance();
        return (array) ($config->NeorGoogleKeywords ?? $config->GoogleSearchConsoleLite ?? []);
    }

    public static function sites(): array
    {
        $sites = [];
        foreach (self::all() as $key => $value) {
            if (preg_match('/^property_([1-9][0-9]*)$/D', (string) $key, $m) && trim((string) $value) !== '') {
                $sites[(int) $m[1]] = trim((string) $value);
            }
        }
        return $sites;
    }

    public static function property(int $id): string
    {
        $property = self::sites()[$id] ?? '';
        if ($property === '' || !preg_match('~^(sc-domain:[^/\s]+|https?://[^\s]+)$~uD', $property)) {
            throw new OperationalException(\Piwik\Piwik::translate('NeorGoogleKeywords_ConfigureProperty', [$id]));
        }
        return $property;
    }

    public static function credentials(): array
    {
        $file = self::all()['credentials_file'] ?? '';
        $normalize = static fn(string $path): string => DIRECTORY_SEPARATOR === '\\'
            ? strtolower(str_replace('\\', '/', $path)) : $path;
        $normalizedFile = is_string($file) ? $normalize($file) : '';
        $absolute = str_starts_with($normalizedFile, '/')
            || (DIRECTORY_SEPARATOR === '\\' && preg_match('~^[a-z]:/~', $normalizedFile));
        $path = $absolute ? realpath($file) : false;
        if (!$path || !is_file($path) || !is_readable($path)) {
            throw new OperationalException(\Piwik\Piwik::translate('NeorGoogleKeywords_KeyUnavailable'));
        }
        foreach ([PIWIK_DOCUMENT_ROOT, $_SERVER['DOCUMENT_ROOT'] ?? ''] as $root) {
            $root = $root !== '' ? realpath($root) : false;
            if ($root && str_starts_with($normalize($path), rtrim($normalize($root), '/') . '/')) {
                throw new OperationalException(\Piwik\Piwik::translate('NeorGoogleKeywords_KeyOutsideWebRoot'));
            }
        }
        if (filesize($path) > 65536) {
            throw new OperationalException(\Piwik\Piwik::translate('NeorGoogleKeywords_KeySizeInvalid'));
        }
        $json = json_decode((string) file_get_contents($path), true);
        if (!is_array($json) || ($json['type'] ?? '') !== 'service_account'
            || empty($json['client_email']) || empty($json['private_key'])) {
            throw new OperationalException(\Piwik\Piwik::translate('NeorGoogleKeywords_ServiceAccountKeyRequired'));
        }
        return $json;
    }

    public static function date(string $date): \DateTimeImmutable
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone('America/Los_Angeles'));
        if (!$d || $d->format('Y-m-d') !== $date) {
            throw new \InvalidArgumentException(\Piwik\Piwik::translate('NeorGoogleKeywords_IsoDateRequired'));
        }
        return $d;
    }

    public static function latest(): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('today', new \DateTimeZone('America/Los_Angeles')))->modify('-2 days');
    }

    public static function range(string $from, string $to, int $maxDays = 500): array
    {
        $a = self::date($from);
        $b = self::date($to);
        if ($a > $b || $a->diff($b)->days >= $maxDays) {
            throw new \InvalidArgumentException(\Piwik\Piwik::translate('NeorGoogleKeywords_InvalidRange', [$maxDays]));
        }
        return [$a, $b];
    }
}
