<?php
/**
 * Задание 031, сверка после заливки. Только чтение.
 * Сравнивает содержимое страниц в базе с пакетом посимвольно и показывает,
 * какие ещё страницы менялись (их быть не должно: сверка «до/после» по
 * выгрузке tools/w031/vygruzka-do.json делается на сервере).
 *
 * Запуск: wp eval-file <файл> <пакет.json>
 */
if (!defined('WP_CLI') || !WP_CLI) { fwrite(STDERR, "только wp eval-file\n"); exit(1); }
$file = null;
foreach ($args as $a) { if (substr($a, 0, 2) !== '--') { $file = $a; break; } }
$pkg = json_decode(file_get_contents($file), true);

$sovpalo = $razoshlos = 0;
foreach ($pkg['items'] as $it) {
    $path = trim(parse_url($it['url'], PHP_URL_PATH), '/');
    $p = $path === '' ? get_post((int) get_option('page_on_front')) : get_page_by_path($path, OBJECT, 'page');
    if (!$p) { WP_CLI::warning($it['url'] . ": страница не найдена"); $razoshlos++; continue; }
    if ($p->post_content === $it['content']) {
        $sovpalo++;
    } else {
        $razoshlos++;
        WP_CLI::warning(sprintf("%s #%d: в базе %d байт, в пакете %d",
            $it['url'], $p->ID, strlen($p->post_content), strlen($it['content'])));
    }
}
WP_CLI::log("совпало посимвольно: $sovpalo, разошлось: $razoshlos");

/* Остатки старой палитры по ВСЕЙ базе, а не только по пакету. */
global $wpdb;
$hex = array('#23483a','#23272e','#1b1e22','#2f6b52','#9fbbad','#c6cbc6','#b7cec1','#6c726b','#f6f7f3');
$rows = $wpdb->get_results("SELECT ID,post_type,post_name,post_content FROM {$wpdb->posts}
    WHERE post_status='publish' AND post_type IN ('page','post')");
WP_CLI::log("--- остатки в содержимом (опубликованных страниц и записей: " . count($rows) . ")");
$nashli = 0;
foreach ($rows as $r) {
    foreach ($hex as $h) {
        $n = preg_match_all('/(background-color|border-color|background|border|color)\s*:\s*'
            . preg_quote($h, '/') . '\b/i', $r->post_content);
        if ($n) { WP_CLI::log("  {$r->post_type}:{$r->post_name} $h × $n"); $nashli += $n; }
    }
    $n = preg_match_all('/border-radius\s*:/i', $r->post_content);
    if ($n) { WP_CLI::log("  {$r->post_type}:{$r->post_name} border-radius × $n"); $nashli += $n; }
}
WP_CLI::log($nashli ? "ОСТАЛОСЬ вхождений: $nashli" : "остатков нет");
