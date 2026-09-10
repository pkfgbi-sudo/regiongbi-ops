<?php
/**
 * Задание 033, проверка после заливки. Только чтение.
 * Обход ПО ОДНОМУ адресу со стороны Бегета (раздел 5 AGENTS.md), с
 * браузерными заголовками. Короткий или пустой ответ считается неудавшимся
 * замером, а не отрицательным результатом.
 *
 * Что проверяется:
 *   1. семнадцать схлопнутых адресов -> 301 на раздел со своим якорем;
 *   2. раздел и ФБС 24-4-6 -> 200;
 *   3. в таблице раздела 18 строк, у каждой id, ссылка одна — на fbs-24-4-6;
 *   4. по всему сайту нет ссылок на схлопнутые адреса (сырой HTML всех
 *      опубликованных страниц и записей);
 *   5. карта сайта: сколько адресов и нет ли схлопнутых;
 *   6. товарная разметка раздела и карточки ФБС 24-4-6.
 *
 * Запуск: wp eval-file <файл>
 */
if (!defined('WP_CLI') || !WP_CLI) { fwrite(STDERR, "только wp eval-file\n"); exit(1); }

$SHLOP = array(
    'fbs-12-3-6','fbs-12-4-3','fbs-12-4-6','fbs-12-5-6','fbs-12-6-6',
    'fbs-24-3-6','fbs-24-5-6','fbs-24-6-6',
    'fbs-6-3-6','fbs-6-4-3','fbs-6-4-6','fbs-6-5-6',
    'fbs-9-3-6','fbs-9-4-3','fbs-9-4-6','fbs-9-5-6','fbs-9-6-6',
);
$RAZDEL = 'https://regiongbi.ru/catalog/fbs-bloki/';
$UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126 Safari/537.36';

function rz33_get($url, $follow = false) {
    global $UA;
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => $follow, CURLOPT_USERAGENT => $UA,
        CURLOPT_HTTPHEADER => array('Accept: text/html,application/xhtml+xml', 'Accept-Language: ru-RU,ru'),
        CURLOPT_HEADER => true,
    ));
    $o = curl_exec($ch);
    $r = array(
        'kod'  => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE),
        'loc'  => (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL),
        'err'  => curl_error($ch),
    );
    $hlen = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $r['telo'] = $o === false ? '' : substr($o, $hlen);
    $r['dlina'] = strlen($r['telo']);
    return $r;
}

$plohih = 0;

/* Мету читаем в начале: после долгого обхода соединение с MySQL отваливается
   («Packets out of order», задание 031), и выборка в конце скрипта не пройдёт. */
$meta107 = get_post_meta(107, '_rz_product', true);

/* --- 1. редиректы */
WP_CLI::log("=== 1. семнадцать схлопнутых адресов");
$ok301 = 0;
foreach ($SHLOP as $s) {
    $u = $RAZDEL . $s . '/';
    $r = rz33_get($u);
    $zhdem = $RAZDEL . '#' . $s;
    if ($r['kod'] === 0) { $plohih++; WP_CLI::warning("$s: замер не удался — {$r['err']}"); continue; }
    $verno = ($r['kod'] === 301 && $r['loc'] === $zhdem);
    if ($verno) { $ok301++; } else {
        WP_CLI::warning(sprintf("%s: код %d, Location «%s», ждали 301 на «%s»", $s, $r['kod'], $r['loc'], $zhdem));
    }
}
WP_CLI::log("  301 на свой якорь: $ok301 из 17");

/* --- 2. раздел и оставшаяся карточка */
WP_CLI::log("=== 2. раздел и ФБС 24-4-6");
$razd = rz33_get($RAZDEL);
$kart = rz33_get($RAZDEL . 'fbs-24-4-6/');
foreach (array('раздел' => $razd, 'fbs-24-4-6' => $kart) as $imya => $r) {
    if ($r['kod'] !== 200 || $r['dlina'] < 20000) { $plohih++; WP_CLI::warning("$imya: код {$r['kod']}, тело {$r['dlina']} байт"); }
    else WP_CLI::log(sprintf("  %-12s код %d, тело %d байт", $imya, $r['kod'], $r['dlina']));
}

/* --- 3. таблица раздела */
WP_CLI::log("=== 3. таблица раздела");
$html = $razd['telo'];
if (preg_match('#<table\b.*?</table>#si', $html, $tm)) {
    $tbl = $tm[0];
    preg_match_all('#<tr\b([^>]*)>#si', $tbl, $trs);
    $vsego = count($trs[0]);
    $s_id  = 0; $idy = array();
    foreach ($trs[1] as $attr) {
        if (preg_match('/\bid="([^"]+)"/i', $attr, $im)) { $s_id++; $idy[] = $im[1]; }
    }
    preg_match_all('#<a\s[^>]*href="([^"]+)"#si', $tbl, $ss);
    WP_CLI::log("  строк <tr> всего: $vsego (одна из них шапка), с id: $s_id");
    WP_CLI::log("  id: " . implode(' ', $idy));
    WP_CLI::log("  ссылок в таблице: " . count($ss[1]) . " — " . implode(' ', $ss[1]));
    $net = array_diff($SHLOP, $idy);
    if ($net) WP_CLI::warning("  нет якорей у: " . implode(' ', $net));
    if (!in_array('fbs-24-4-6', $idy, true)) WP_CLI::warning("  нет якоря у fbs-24-4-6");
} else { $plohih++; WP_CLI::warning("  таблица в выводе раздела не найдена"); }

/* --- 4. ссылки по всему сайту */
WP_CLI::log("=== 4. обход всего сайта: ссылки на схлопнутые адреса");
global $wpdb;
$rows = $wpdb->get_results("SELECT ID FROM {$wpdb->posts}
    WHERE post_status='publish' AND post_type IN ('page','post') ORDER BY ID");
$urls = array();
foreach ($rows as $r) $urls[] = get_permalink($r->ID);
$n = 0; $kod200 = 0; $kod301 = 0; $nashli = array(); $plohih_obhod = 0;
foreach ($urls as $u) {
    $r = rz33_get($u);
    $n++;
    if ($r['kod'] === 301) { $kod301++; continue; }
    if ($r['kod'] !== 200 || $r['dlina'] < 5000) { $plohih_obhod++; WP_CLI::warning("замер не удался: $u — код {$r['kod']}, тело {$r['dlina']} байт"); continue; }
    $kod200++;
    foreach ($SHLOP as $s) {
        $k = preg_match_all('~href="[^"]*/' . preg_quote($s, '~') . '/"~i', $r['telo']);
        if ($k) $nashli[] = "$u -> $s x$k";
    }
}
WP_CLI::log("  адресов обойдено: $n, из них 200: $kod200, 301: $kod301, неудавшихся замеров: $plohih_obhod");
WP_CLI::log($nashli ? "  ОСТАЛИСЬ ССЫЛКИ:\n    " . implode("\n    ", $nashli)
                    : "  ссылок на схлопнутые адреса нет ни на одной странице");
$plohih += $plohih_obhod;

/* --- 5. карта сайта */
WP_CLI::log("=== 5. карта сайта");
$idx = rz33_get('https://regiongbi.ru/sitemap_index.xml');
preg_match_all('#<loc>(.*?)</loc>#si', $idx['telo'], $sm);
$vsego_loc = 0; $est_shlop = array();
foreach ($sm[1] as $karta) {
    $c = rz33_get(trim($karta));
    preg_match_all('#<loc>(.*?)</loc>#si', $c['telo'], $lm);
    WP_CLI::log("  " . basename(trim($karta)) . ": " . count($lm[1]) . " адресов");
    $vsego_loc += count($lm[1]);
    foreach ($lm[1] as $loc) {
        foreach ($SHLOP as $s) {
            if (strpos($loc, '/' . $s . '/') !== false) $est_shlop[] = trim($loc);
        }
    }
}
WP_CLI::log("  всего адресов в карте: $vsego_loc");
WP_CLI::log($est_shlop ? "  В КАРТЕ ОСТАЛИСЬ: " . implode(' ', $est_shlop) : "  схлопнутых адресов в карте нет");

/* --- 6. товарная разметка */
WP_CLI::log("=== 6. товарная разметка (по сырому HTML, раздел 5 AGENTS.md)");
foreach (array('раздел' => $razd['telo'], 'fbs-24-4-6' => $kart['telo']) as $imya => $h) {
    preg_match_all('#"@type":"?([A-Za-z]+)#', $h, $tm2);
    $tipy = array_count_values($tm2[1]);
    $sp = array();
    foreach ($tipy as $t => $k) $sp[] = $t . ' x' . $k;
    WP_CLI::log("  $imya: " . ($sp ? implode(' ', $sp) : 'узлов нет'));
}
WP_CLI::log("  мета _rz_product у раздела #107: " . ($meta107 === '' ? 'пусто (её там никогда и не было)' : strlen($meta107) . ' байт'));

WP_CLI::log(str_repeat('-', 72));
WP_CLI::log($plohih ? "НЕУДАВШИХСЯ ЗАМЕРОВ: $plohih" : "неудавшихся замеров: 0");
