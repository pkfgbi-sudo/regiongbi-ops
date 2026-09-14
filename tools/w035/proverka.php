<?php
/**
 * Задание 035, проверка после заливки шести карточек. Только чтение.
 * Обход — последовательный, по одному адресу, с браузерными заголовками;
 * микроразметка — по сырому HTML (раздел 5 AGENTS.md).
 *
 * Проверяет по всем шести карточкам: код ответа и длину тела, один ли H1,
 * блоки «Полезное по теме» и «Рядом в ряду», Product с ценой, новый текст.
 * Отдельно по двум исправленным: семь строк в таблице ряда Ø700 и отсутствие
 * слова «единственное» у КС 7-3, «394 ₽» у КС 15-5.
 * Ещё: по одной странице на слаг, _rz_package, состав карты сайта и список
 * страниц, изменённых за сутки (чтобы видеть, что лишнего не задето).
 *
 * Запуск: wp eval-file <файл>
 */
if (!defined('WP_CLI') || !WP_CLI) { fwrite(STDERR, "только wp eval-file\n"); exit(1); }

$karty = array(
    'ks-7-3'   => array(750, 980,  'Самое лёгкое из рабочих колец ряда'),
    'ks-7-5'   => array(751, 1220, 'Полметра — удобная единица счёта'),
    'ks-15-5'  => array(761, 2890, 'на 394 ₽ дороже, чем у КС 15-9'),
    'ks-20-10' => array(769, 7100, 'Здесь привычное правило не работает'),
    'pp-10-2'  => array(799, 2484, 'разница больше, чем кажется'),
    'pp-20-2'  => array(804, 8420, 'Полторы тонны меняют логистику'),
);
$ua = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126 Safari/537.36';
$plohih = 0;

function rz_get($url, $ua) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_USERAGENT => $ua, CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => array('Accept: text/html,application/xhtml+xml', 'Accept-Language: ru-RU,ru'),
    ));
    $o = curl_exec($ch);
    $kod = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hlen = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return array($kod, $o === false ? '' : substr($o, $hlen));
}

WP_CLI::log('=== 1. шесть карточек живьём');
foreach ($karty as $slug => $d) {
    list($id, $cena, $novoe) = $d;
    list($kod, $h) = rz_get(get_permalink($id), $ua);
    if ($kod !== 200 || strlen($h) < 20000) {
        $plohih++; WP_CLI::warning("$slug: код $kod, тело " . strlen($h) . " байт — замер не удался"); continue;
    }
    $h1   = preg_match_all('#<h1[^>]*>#i', $h);
    $art  = substr_count($h, 'Полезное по теме');
    $sib  = substr_count($h, 'Рядом в ряду');
    $prod = preg_match('#"@type":"Product"#', $h);
    preg_match('#"price":"?(\d+)#', $h, $pm);
    $cena_v_html = isset($pm[1]) ? (int) $pm[1] : 0;
    $ok_cena = ($cena_v_html === $cena);
    $est_novoe = (strpos($h, $novoe) !== false);
    if (!$h1 || $h1 > 1 || !$art || !$sib || !$prod || !$ok_cena || !$est_novoe) $plohih++;
    WP_CLI::log(sprintf("  %-9s код %d, %6d байт, H1:%d, «Полезное по теме»:%d, «Рядом в ряду»:%d, Product:%d, price:%d %s",
        $slug, $kod, strlen($h), $h1, $art, $sib, $prod, $cena_v_html, $ok_cena ? 'ок' : 'НЕ ТА ЦЕНА, ждали ' . $cena));
    WP_CLI::log('             новый текст («' . $novoe . '»): ' . ($est_novoe ? 'на месте' : 'НЕТ'));

    if ($slug === 'ks-7-3') {
        $strok = preg_match_all('#<td>КС 7-[\d\-,]+ — \d+ мм</td>#u', $h);
        $edin  = (stripos($h, 'динственное кольцо ряда') !== false);
        $lyub  = (strpos($h, 'любого другого кольца') !== false);
        $mladshie = (strpos($h, 'КС 7-1,5 (150 мм, 70 кг)') !== false)
                 && (strpos($h, 'КС 7-0,1 (100 мм, 46 кг)') !== false);
        if ($strok !== 7 || $edin || $lyub || !$mladshie) $plohih++;
        WP_CLI::log(sprintf('             таблица ряда Ø700: %d строк (ждём 7); «единственное»: %s; «любого другого»: %s; два младших добора: %s',
            $strok, $edin ? 'ЕСТЬ' : 'нет', $lyub ? 'ЕСТЬ' : 'нет', $mladshie ? 'на месте' : 'НЕТ'));
    }
    if ($slug === 'ks-15-5') {
        $e394 = (strpos($h, '394') !== false);
        $e830 = (strpos($h, 'на 830 ₽ дешевле') !== false);
        $sotnya = (strpos($h, 'на сотню дороже') !== false);
        if (!$e394 || !$e830 || $sotnya) $plohih++;
        WP_CLI::log(sprintf('             «394»: %s; «на 830 ₽ дешевле»: %s; «на сотню дороже»: %s',
            $e394 ? 'есть' : 'НЕТ', $e830 ? 'есть' : 'НЕТ', $sotnya ? 'ЕСТЬ' : 'нет'));
    }
}

WP_CLI::log('');
WP_CLI::log('=== 2. по одной странице на слаг и метки пакета');
global $wpdb;
foreach ($karty as $slug => $d) {
    $n = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_name=%s AND post_type='page' AND post_status='publish'", $slug));
    if ($n !== 1) { $plohih++; WP_CLI::warning("$slug: страниц с этим слагом $n, а не 1"); }
    WP_CLI::log(sprintf('  %-9s страниц: %d  _rz_package: %s | last: %s',
        $slug, $n, get_post_meta($d[0], '_rz_package', true), get_post_meta($d[0], '_rz_package_last', true)));
}

WP_CLI::log('');
WP_CLI::log('=== 3. что изменилось в базе за последние сутки');
$rows = $wpdb->get_results("SELECT ID,post_name,post_type,post_modified FROM {$wpdb->posts}
    WHERE post_status='publish' AND post_type IN ('page','post')
      AND post_modified > DATE_SUB(NOW(), INTERVAL 1 DAY) ORDER BY post_modified");
foreach ($rows as $r) WP_CLI::log(sprintf('  #%-5d %-10s %-28s %s', $r->ID, $r->post_type, $r->post_name, $r->post_modified));
WP_CLI::log('  всего изменённых за сутки: ' . count($rows));
$lishnie = array();
foreach ($rows as $r) if (!isset($karty[$r->post_name])) $lishnie[] = $r->post_name;
if ($lishnie) { $plohih++; WP_CLI::warning('изменены посторонние: ' . implode(', ', $lishnie)); }
else WP_CLI::log('  посторонних изменений нет');

WP_CLI::log('');
WP_CLI::log('=== 4. карта сайта');
list($kod, $x) = rz_get(home_url('/sitemap_index.xml'), $ua);
preg_match_all('#<loc>([^<]+sitemap[^<]*\.xml)</loc>#', $x, $m);
WP_CLI::log('  индекс: код ' . $kod . ', карт внутри: ' . count($m[1]));
$vsego = 0;
$tela = array();          /* каждую карту забираем один раз, потом считаем по телу */
foreach ($m[1] as $u) {
    list($k2, $x2) = rz_get($u, $ua);
    $tela[] = $x2;
    $n = preg_match_all('#<loc>#', $x2);
    $vsego += $n;
    WP_CLI::log(sprintf('  %-60s код %d, адресов %d', basename($u), $k2, $n));
}
WP_CLI::log('  всего адресов в карте: ' . $vsego);
foreach (array_keys($karty) as $slug) {
    $n = 0;
    foreach ($tela as $x2) $n += substr_count($x2, '/' . $slug . '/');
    if ($n !== 1) { $plohih++; WP_CLI::warning("$slug: в карте $n раз, а не 1"); }
}
WP_CLI::log('  каждая из шести карточек в карте по одному разу');

WP_CLI::log('');
WP_CLI::log($plohih ? "ПРОБЛЕМ ПРИ ПРОВЕРКЕ: $plohih" : 'проблем при проверке: 0');
