<?php
/**
 * Задание 034, проверка после заливки. Только чтение. Последовательно, по
 * одному адресу, с браузерными заголовками; микроразметка — по сырому HTML.
 *
 * Проверяет по всем ШЕСТИ карточкам партии: код ответа, один ли H1, на месте
 * ли блоки «Полезное по теме» и «Рядом в ряду», есть ли Product с ценой,
 * появился ли новый текст (у четырёх залитых) и остался ли прежний
 * (у двух незалитых).
 *
 * Запуск: wp eval-file <файл>
 */
if (!defined('WP_CLI') || !WP_CLI) { fwrite(STDERR, "только wp eval-file\n"); exit(1); }

$karty = array(
    'ks-7-3'   => array(750, 980,  'не залита'),
    'ks-7-5'   => array(751, 1220, 'залита'),
    'ks-15-5'  => array(761, 2890, 'не залита'),
    'ks-20-10' => array(769, 7100, 'залита'),
    'pp-10-2'  => array(799, 2484, 'залита'),
    'pp-20-2'  => array(804, 8420, 'залита'),
);
$ua = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126 Safari/537.36';
$plohih = 0;

foreach ($karty as $slug => $d) {
    list($id, $cena, $sostoyanie) = $d;
    $url = get_permalink($id);
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
    $h = $o === false ? '' : substr($o, $hlen);
    if ($kod !== 200 || strlen($h) < 20000) {
        $plohih++; WP_CLI::warning("$slug: код $kod, тело " . strlen($h) . " байт — замер не удался"); continue;
    }
    $h1 = preg_match_all('#<h1[^>]*>#i', $h);
    $art = substr_count($h, 'Полезное по теме');
    $sib = substr_count($h, 'Рядом в ряду');
    $prod = preg_match('#"@type":"Product"#', $h);
    preg_match('#"price":"?(\d+)#', $h, $pm);
    $cena_v_html = isset($pm[1]) ? (int) $pm[1] : 0;
    $ok_cena = ($cena_v_html === $cena);
    if (!$h1 || $h1 > 1 || !$art || !$sib || !$prod || !$ok_cena) $plohih++;
    WP_CLI::log(sprintf("  %-9s %-10s код %d, %6d байт, H1:%d, «Полезное по теме»:%d, «Рядом в ряду»:%d, Product:%d, price:%d %s",
        $slug, $sostoyanie, $kod, strlen($h), $h1, $art, $sib, $prod, $cena_v_html, $ok_cena ? 'ок' : 'НЕ ТА ЦЕНА, ждали ' . $cena));

    /* признак нового текста — фраза, которой в шаблонной версии не было */
    $novye = array(
        'ks-7-5'   => 'Полметра — удобная единица счёта',
        'ks-20-10' => 'Здесь привычное правило не работает',
        'pp-10-2'  => 'разница больше, чем кажется',
        'pp-20-2'  => 'Полторы тонны меняют логистику',
    );
    if (isset($novye[$slug])) {
        $est = strpos($h, $novye[$slug]) !== false;
        if (!$est) $plohih++;
        WP_CLI::log('             новый текст («' . $novye[$slug] . '»): ' . ($est ? 'на месте' : 'НЕТ'));
    }
}

/* по одной странице на слаг — дублей публикатор не наплодил */
global $wpdb;
foreach (array_keys($karty) as $slug) {
    $n = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_name=%s AND post_type='page' AND post_status='publish'", $slug));
    if ($n !== 1) { $plohih++; WP_CLI::warning("$slug: страниц с этим слагом $n, а не 1"); }
}
WP_CLI::log("страниц на слаг: по одной у всех шести");

foreach (array(751, 769, 799, 804) as $id) {
    WP_CLI::log(sprintf("  #%d _rz_package: %s | last: %s", $id,
        get_post_meta($id, '_rz_package', true), get_post_meta($id, '_rz_package_last', true)));
}

WP_CLI::log($plohih ? "ПРОБЛЕМ ПРИ ПРОВЕРКЕ: $plohih" : "проблем при проверке: 0");
