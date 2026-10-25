<?php
// Standalone CSS compilation and readability checks; no database or Google API.
require dirname(__DIR__, 3) . '/vendor/autoload.php';

$css = file_get_contents(dirname(__DIR__) . '/stylesheets/report.css');
$parser = new Less_Parser();
$parser->parse($css);
$compiled = $parser->getCss();
if (!str_contains($compiled, 'prefers-color-scheme: dark')) {
    throw new RuntimeException('Auto theme missing from compiled CSS');
}
echo "PASS Matomo LESS compiler accepts stylesheet\n";

preg_match('/:root\[data-theme-mode="dark"\]\s*\{([^}]+)\}/', $css, $dark);
preg_match('/:root\[data-theme-mode="auto"\]\s*\{([^}]+)\}/', $css, $auto);
preg_match_all('/(--ngk-[\w-]+)\s*:\s*([^;]+);/', $dark[1], $definitions, PREG_SET_ORDER);
$palette = [];
$theme = new Piwik\Plugin\ThemeStyles('dark');
$native = [
    '--theme-color-text' => $theme->getPropertyValue('colorText'),
    '--theme-color-background-contrast' => $theme->getPropertyValue('colorBackgroundContrast'),
    '--theme-color-background-tinyContrast' => $theme->getPropertyValue('colorBackgroundTinyContrast'),
    '--theme-color-border-alternative' => $theme->getPropertyValue('colorBorderAlternative'),
    '--theme-color-focus-ring' => $theme->getPropertyValue('colorFocusRing'),
];
foreach ($definitions as $definition) {
    $value = trim($definition[2]);
    if (preg_match('/var\((--theme-[\w-]+),\s*(#[0-9a-f]+)\)/i', $value, $variable)) {
        $value = $native[$variable[1]];
    }
    $palette[substr($definition[1], 6)] = $value;
}
if (preg_replace('/\s+/', '', $dark[1]) !== preg_replace('/\s+/', '', $auto[1])) {
    throw new RuntimeException('Explicit dark and auto-dark palettes differ');
}
if (preg_match('/(--ngk-[\w-]+):\s*var\(\1[,)]/', $css)) {
    throw new RuntimeException('Self-referencing palette variable');
}
echo "PASS explicit dark and auto-dark palettes match without cyclic variables\n";

function luminance(string $hex): float
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    $rgb = array_map(static function ($channel) {
        $value = hexdec($channel) / 255;
        return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
    }, str_split($hex, 2));
    return $rgb[0] * 0.2126 + $rgb[1] * 0.7152 + $rgb[2] * 0.0722;
}

$palette['white'] = '#fff';
foreach (['surface', 'subtle', 'hover', 'rank-top1', 'rank-top1-hover', 'rank-top3', 'rank-top3-hover'] as $background) {
    foreach (['text', 'muted', 'link'] as $foreground) {
        $pairs[] = [$foreground, $background];
    }
}
foreach (['error', 'success', 'notice', 'warning'] as $status) {
    $pairs[] = [$status . '-text', $status . '-bg'];
}
$pairs[] = ['white', 'accent'];
$pairs[] = ['white', 'accent-hover'];
foreach (['clicks', 'impressions', 'series', 'series-alt'] as $series) {
    $pairs[] = [$series, 'surface'];
}
foreach ($pairs as [$foreground, $background]) {
    $a = luminance($palette[$foreground]);
    $b = luminance($palette[$background]);
    $ratio = (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    if ($ratio < 4.5) {
        throw new RuntimeException("Low contrast: $foreground on $background ($ratio)");
    }
}
echo 'PASS ' . count($pairs) . " text/background pairs meet 4.5:1 contrast\n";
