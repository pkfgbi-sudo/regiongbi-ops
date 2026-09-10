<?php
/**
 * Задание 034. Только чтение: последовательно, по одному адресу, с браузерными
 * заголовками проверяет, что каждый адрес из текстов пакета отдаёт 200.
 * Короткий ответ — неудавшийся замер, а не отрицательный результат.
 *
 * Адреса берутся из самого пакета: так список не разъедется с текстом.
 *
 * Запуск: wp eval-file <файл> <пакет.json>
 */
if (!defined('WP_CLI') || !WP_CLI) { fwrite(STDERR, "только wp eval-file\n"); exit(1); }
$file = null;
foreach ($args as $a) { if (substr($a, 0, 2) !== '--' && $a !== 'dry') { $file = $a; break; } }
if (!$file || !file_exists($file)) { WP_CLI::error("нет файла пакета"); }
$pkg = json_decode(file_get_contents($file), true);

$adresa = array();
foreach ($pkg['items'] as $it) {
    if (preg_match_all('~href="(/[^"#]*)"~', $it['content'], $m)) {
        /* Разделитель ~, а не #: внутри шаблона есть класс [^"#]. */
        foreach ($m[1] as $u) $adresa[$u][] = $it['slug'];
    }
}
ksort($adresa);
WP_CLI::log("адресов в текстах пакета " . $pkg['package'] . ": " . count($adresa));

$ua = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126 Safari/537.36';
$plohih = 0; $ne200 = 0;
foreach ($adresa as $put => $gde) {
    $ch = curl_init('https://regiongbi.ru' . $put);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_USERAGENT => $ua, CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => array('Accept: text/html,application/xhtml+xml', 'Accept-Language: ru-RU,ru'),
    ));
    $o = curl_exec($ch);
    $kod = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $loc = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    $hlen = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $err = curl_error($ch);
    curl_close($ch);
    $dlina = $o === false ? 0 : strlen(substr($o, $hlen));
    if ($kod === 0) { $plohih++; WP_CLI::warning("$put: замер не удался — $err"); continue; }
    if ($kod !== 200) { $ne200++; WP_CLI::warning("$put: код $kod" . ($loc ? " -> $loc" : '')); continue; }
    if ($dlina < 20000) { $plohih++; WP_CLI::warning("$put: код 200, но тело $dlina байт — замер сомнителен"); continue; }
    WP_CLI::log(sprintf("  %-52s 200, %6d байт   (в %s)", $put, $dlina, implode(', ', array_unique($gde))));
}
WP_CLI::log($ne200 ? "АДРЕСОВ НЕ 200: $ne200" : "все адреса отдают 200");
WP_CLI::log("неудавшихся замеров: $plohih");
