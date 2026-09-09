<?php
/**
 * Задание 030, пункт 6 отчёта. Только чтение.
 *
 * 1) Что за файл лежит в медиатеке под логотипом (SVG или растр, размер,
 *    имя) — от этого зависит, можно ли перекрасить его без дизайнера.
 * 2) Все места, где цвет зашит прямо в содержимом страниц и записей
 *    (инлайновый style с хексом). Список нужен, чтобы переписать эти блоки
 *    пакетом и убрать заплатки на !important из mu-rz-theme.php.
 *
 * Запуск: wp eval-file <этот файл>
 */
if (!defined('ABSPATH')) { echo "только через wp eval-file\n"; return; }

echo "=== 1. ЛОГОТИП ===\n";
$ids = array();
$mod = get_theme_mod('custom_logo');
if ($mod) $ids['custom_logo (theme_mod)'] = (int) $mod;
$opt = get_option('site_logo');
if ($opt) $ids['site_logo (option)'] = (int) $opt;
$ico = get_option('site_icon');
if ($ico) $ids['site_icon (option)'] = (int) $ico;

/* Blocksy держит логотип и в своих настройках — вытаскиваем оттуда id/URL. */
$bl = get_option('blocksy_customizer_data');
if (!$bl) $bl = get_theme_mods();
$json = wp_json_encode($bl);
if ($json && preg_match_all('#https?://[^"\\\\ ]+?\.(?:svg|png|jpe?g|webp)#i', $json, $m)) {
    foreach (array_unique($m[0]) as $u) {
        $aid = attachment_url_to_postid($u);
        echo "  в настройках темы: $u" . ($aid ? " (вложение $aid)" : " (вне медиатеки)") . "\n";
        if ($aid) $ids['настройки темы'] = $aid;
    }
}

foreach ($ids as $gde => $id) {
    $f = get_attached_file($id);
    $p = get_post($id);
    echo "  $gde: id=$id"
       . ", имя=" . ($p ? $p->post_name : '?')
       . ", файл=" . ($f ? basename($f) : '?')
       . ", mime=" . get_post_mime_type($id)
       . ", байт=" . ($f && file_exists($f) ? filesize($f) : '?')
       . "\n";
    $md = wp_get_attachment_metadata($id);
    if (is_array($md) && isset($md['width'])) echo "      размер: {$md['width']}x{$md['height']}\n";
    echo "      URL: " . wp_get_attachment_url($id) . "\n";
}
if (!$ids) echo "  логотип через custom_logo/site_logo не задан\n";

/* Логотип может стоять и инлайном в шапке — ищем по имени файла в медиатеке. */
echo "--- вложения, в имени которых есть logo/лого ---\n";
global $wpdb;
$rows = $wpdb->get_results("SELECT ID,post_name,post_mime_type FROM {$wpdb->posts}
    WHERE post_type='attachment' AND (post_name LIKE '%logo%' OR post_title LIKE '%лого%' OR post_name LIKE '%лого%')");
if (!$rows) echo "  нет\n";
foreach ($rows as $r) {
    $f = get_attached_file($r->ID);
    echo "  id={$r->ID} {$r->post_name} [{$r->post_mime_type}] "
       . ($f ? basename($f) . ' ' . (file_exists($f) ? filesize($f) . ' байт' : 'файла нет') : '?') . "\n";
}

echo "\n=== 2. ЦВЕТА, ЗАШИТЫЕ В СОДЕРЖИМОМ ===\n";
$posts = $wpdb->get_results("SELECT ID,post_type,post_name,post_title,post_content
    FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ('page','post')
    AND post_content LIKE '%#%'");
$svod = array();
$vsego_str = 0;
foreach ($posts as $r) {
    if (!preg_match_all('/(background-color|border-color|background|border|color)\s*:\s*(#[0-9a-fA-F]{3,8})/i',
        $r->post_content, $m, PREG_SET_ORDER)) continue;
    $vsego_str++;
    foreach ($m as $x) {
        $hex = strtolower($x[2]);
        $sv  = strtolower($x[1]);
        if (!isset($svod[$hex])) $svod[$hex] = array('n' => 0, 'sv' => array(), 'str' => array());
        $svod[$hex]['n']++;
        $svod[$hex]['sv'][$sv] = isset($svod[$hex]['sv'][$sv]) ? $svod[$hex]['sv'][$sv] + 1 : 1;
        $svod[$hex]['str'][$r->ID] = $r->post_type . ':' . $r->post_name;
    }
}
echo "страниц и записей с инлайновым цветом: $vsego_str\n\n";
uasort($svod, function ($a, $b) { return $b['n'] - $a['n']; });
foreach ($svod as $hex => $d) {
    $sv = array();
    /* Конкатенацией, а не подстановкой в строку: PHP допускает многобайтные
       буквы в именах переменных, и "$k×$v" читается как переменная «k×». */
    foreach ($d['sv'] as $k => $v) $sv[] = $k . ' ' . $v . ' шт';
    echo str_pad($hex, 10) . " вхождений: " . str_pad($d['n'], 4)
       . " страниц: " . str_pad(count($d['str']), 4)
       . " свойства: " . implode(', ', $sv) . "\n";
    $sl = array_values($d['str']);
    sort($sl);
    $pok = array_slice($sl, 0, 30);
    echo "           " . implode(', ', $pok) . (count($sl) > 30 ? ' … ещё ' . (count($sl) - 30) : '') . "\n";
}

echo "\n=== 3. ПРОЧИЕ ИНЛАЙНОВЫЕ ПРИЗНАКИ ОФОРМЛЕНИЯ ===\n";
foreach (array('border-radius', 'box-shadow', 'font-family') as $pr) {
    $n = 0; $s = array();
    foreach ($posts as $r) {
        $c = substr_count(strtolower($r->post_content), $pr . ':');
        if ($c) { $n += $c; $s[$r->ID] = 1; }
    }
    echo "  $pr: вхождений $n на " . count($s) . " страницах\n";
}
