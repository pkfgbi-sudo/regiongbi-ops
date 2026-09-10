<?php
/**
 * Задание 032, проверка вживую. Только чтение, по одному адресу, с
 * браузерными заголовками, разбор по СЫРОМУ HTML (раздел 5 AGENTS.md).
 *
 * По каждому адресу печатает:
 *   — код и длину тела;
 *   — все PropertyValue из узла rzProduct, по порядку (кириллица в графе
 *     записана \u-последовательностями, раскрываем);
 *   — разметку карточки блока «Смотрите также», если он на странице есть;
 *   — есть ли в <style id="rz-theme"> токен --rz-ten-rgb и тени через него.
 *
 * Запуск: wp eval-file proverka.php
 */
if (!defined('WP_CLI') || !WP_CLI) { fwrite(STDERR, "только wp eval-file\n"); exit(1); }

$ids = array(650, 647, 640, 820, 654, 601, 599, 102);
$ua = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126 Safari/537.36';

function w032_razdec($s) {
    return preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($m) {
        return mb_convert_encoding(pack('n', hexdec($m[1])), 'UTF-8', 'UTF-16BE');
    }, $s);
}

$tema_pokazana = false;
foreach ($ids as $id) {
    $u = get_permalink($id);
    $ch = curl_init($u);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_USERAGENT => $ua,
        CURLOPT_HTTPHEADER => array('Accept: text/html,application/xhtml+xml', 'Accept-Language: ru-RU,ru'),
    ));
    $telo = (string) curl_exec($ch);
    $kod  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    WP_CLI::log(sprintf('=== #%d %s  код %d, тело %d байт', $id, $u, $kod, strlen($telo)));
    if ($kod !== 200 || strlen($telo) < 5000) { WP_CLI::warning('замер не удался'); continue; }

    $graf = w032_razdec($telo);
    if (preg_match_all('/"@type":"PropertyValue","name":"(.*?)","value":"(.*?)"/', $graf, $m, PREG_SET_ORDER)) {
        $vsego = count($m);
        $st = 0;
        foreach ($m as $one) if ($one[1] === 'Стандарт') $st++;
        WP_CLI::log("    PropertyValue всего: $vsego, из них «Стандарт»: $st");
        foreach (array_slice($m, 0, 14) as $one) WP_CLI::log('      ' . $one[1] . ' = ' . $one[2]);
        if ($vsego > 14) WP_CLI::log('      … и ещё ' . ($vsego - 14));
    } else {
        WP_CLI::log('    PropertyValue: ни одного');
    }

    if (preg_match('/<!-- rz-related-mu -->.*?(<a href=.*?<\/a>)/s', $telo, $mk)) {
        WP_CLI::log('    карточка «Смотрите также»: ' . substr($mk[1], 0, 400));
    }

    if (!$tema_pokazana && preg_match('/--rz-ten-rgb:([^;]*);/', $telo, $mt)) {
        $tema_pokazana = true;
        WP_CLI::log('    токен тени в <style id="rz-theme">: --rz-ten-rgb:' . trim($mt[1]));
        preg_match_all('/box-shadow:[^;!}]*/', $telo, $ms);
        foreach (array_unique($ms[0]) as $sh) {
            if (strpos($sh, '--rz-ten-rgb') !== false || strpos($sh, 'rgba(22,25,26') !== false) {
                WP_CLI::log('      ' . trim($sh));
            }
        }
        WP_CLI::log('      осталось rgba(22,25,26 в HTML: ' . substr_count($telo, 'rgba(22,25,26'));
    }
    usleep(300000);
}
