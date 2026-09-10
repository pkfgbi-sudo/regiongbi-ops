<?php
/**
 * Задание 032, пункт 2. Только чтение: смотрит, у каких страниц ключ
 * «Стандарт» приходит и из таблицы характеристик (specs), и из поля gost,
 * и совпадают ли значения.
 *
 * Совпало   — дубль, его снимает правка rz_build_product().
 * РАЗОШЛОСЬ — в таблице один ГОСТ, в gost другой: это расхождение данных,
 *             оно не «лечится» кодом и идёт в отчёт.
 *
 * Заодно печатает сводку по _rz_product: сколько страниц, сколько битых,
 * сколько с характеристиками, и выгрузку «ID<TAB>цена» для сверки до/после.
 *
 * Запуск: wp eval-file skan-standart.php
 */
if (!defined('WP_CLI') || !WP_CLI) { fwrite(STDERR, "только wp eval-file\n"); exit(1); }

global $wpdb;
$rows = $wpdb->get_results("SELECT post_id, meta_value FROM {$wpdb->postmeta}
    WHERE meta_key='_rz_product' ORDER BY post_id");

$vsego = count($rows);
$bityh = 0; $s_harakt = 0; $s_massoy = 0;
$dubl = array(); $razoshlos = array(); $ceny = array();

/* «ГОСТ 8020-2016» и «ГОСТ  8020-2016» — одно и то же значение. */
function w032_norm($s) {
    $s = str_replace("\xC2\xA0", ' ', (string) $s);
    return trim(preg_replace('/\s+/u', ' ', $s));
}

foreach ($rows as $r) {
    $d = json_decode($r->meta_value, true);
    if (!is_array($d) || empty($d['name'])) { $bityh++; continue; }
    $id = (int) $r->post_id;
    $ceny[$id] = isset($d['price']) ? $d['price'] : null;

    $specs = (isset($d['specs']) && is_array($d['specs'])) ? $d['specs'] : array();
    if ($specs) $s_harakt++;
    if (!empty($d['weight_kg'])) $s_massoy++;

    /* значения всех ключей, похожих на «Стандарт» */
    $iz_tablicy = array();
    foreach ($specs as $k => $v) {
        if (mb_stripos(w032_norm($k), 'стандарт') !== false) $iz_tablicy[] = w032_norm($v);
    }
    if (!$iz_tablicy) continue;

    $iz_gost = array();
    if (!empty($d['gost']) && is_array($d['gost'])) {
        foreach ($d['gost'] as $g) $iz_gost[] = w032_norm('ГОСТ ' . $g);
    }
    if (!$iz_gost) continue;

    $sovpali = array_intersect($iz_gost, $iz_tablicy);
    $tolko_v_gost = array_diff($iz_gost, $iz_tablicy);

    $stroka = sprintf('%-5d %-28s таблица: %-22s gost: %s',
        $id, get_post_field('post_name', $id),
        implode(' | ', $iz_tablicy), implode(' | ', $iz_gost));

    if ($sovpali) $dubl[] = $stroka . '   ДУБЛЬ: ' . implode(', ', $sovpali);
    if ($tolko_v_gost) $razoshlos[] = $stroka . '   ТОЛЬКО В gost: ' . implode(', ', $tolko_v_gost);
}

/* То же самое по рядам марок: у страницы type=list каждый элемент items
   получает specs из колонок таблицы и gost из корня меты, поэтому «Стандарт»
   двоится в каждом ListItem, а не один раз на страницу. */
$items_dubl = array();
foreach ($rows as $r) {
    $d = json_decode($r->meta_value, true);
    if (!is_array($d) || empty($d['items']) || !is_array($d['items'])) continue;
    if (empty($d['gost']) || !is_array($d['gost'])) continue;
    $iz_gost = array();
    foreach ($d['gost'] as $g) $iz_gost[] = w032_norm('ГОСТ ' . $g);
    $n = 0;
    foreach ($d['items'] as $it) {
        $props = (isset($it['props']) && is_array($it['props'])) ? $it['props'] : array();
        foreach ($props as $k => $v) {
            if (mb_stripos(w032_norm($k), 'стандарт') === false) continue;
            if (in_array(w032_norm($v), $iz_gost, true)) { $n++; break; }
        }
    }
    if ($n) $items_dubl[] = sprintf('%-5d %-28s элементов ряда с дублем: %d из %d',
        (int) $r->post_id, get_post_field('post_name', (int) $r->post_id), $n, count($d['items']));
}

WP_CLI::log("страниц с _rz_product: $vsego, битых: $bityh, с характеристиками: $s_harakt, с массой: $s_massoy");

WP_CLI::log("--- «Стандарт» есть и в таблице, и в gost, значение СОВПАДАЕТ (дубль в разметке)");
if (!$dubl) WP_CLI::log('  ни одной');
foreach ($dubl as $s) WP_CLI::log('  ' . $s);

WP_CLI::log("--- ГОСТ из gost, которого НЕТ в таблице (расхождение, кодом не лечится)");
if (!$razoshlos) WP_CLI::log('  ни одного');
foreach ($razoshlos as $s) WP_CLI::log('  ' . $s);

WP_CLI::log("--- ряды марок: «Стандарт» двоится внутри ListItem");
if (!$items_dubl) WP_CLI::log('  ни одного');
foreach ($items_dubl as $s2) WP_CLI::log('  ' . $s2);

WP_CLI::log('--- выгрузка цен (ID<TAB>цена), строк: ' . count($ceny));
foreach ($ceny as $id => $c) WP_CLI::log("ЦЕНА\t$id\t" . ($c === null ? '' : $c));
