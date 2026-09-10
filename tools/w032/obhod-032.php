<?php
/**
 * Задание 032. Обход сайта ПО ОДНОМУ адресу со стороны Бегета (раздел 5
 * AGENTS.md), только чтение. Считает по сырому HTML, а не разбором графа.
 *
 * Что считает:
 *   1. элементы, под которые попадает [style*="border-radius:8px"] —
 *      всего и в разбивке по тегу (<a>, <img>, прочее); то же для
 *      "border-radius:6px 6px 0 0";
 *   2. блок «Смотрите также» (<!-- rz-related-mu -->) и число карточек в нём;
 *   3. сколько раз в JSON-LD страницы встречается PropertyValue «Стандарт»
 *      (кириллица в графе записана \u-последовательностями — раскрываем);
 *   4. коды ответов и длину тела: короткий ответ — неудавшийся замер.
 *
 * Запуск: wp eval-file obhod-032.php [all]
 *         «all» позиционным аргументом: WP-CLI съедает двойное тире.
 */
if (!defined('WP_CLI') || !WP_CLI) { fwrite(STDERR, "только wp eval-file\n"); exit(1); }
$all = in_array('all', $args, true);

global $wpdb;
$urls = array();
if ($all) {
    $rows = $wpdb->get_results("SELECT ID FROM {$wpdb->posts}
        WHERE post_status='publish' AND post_type IN ('page','post') ORDER BY ID");
    foreach ($rows as $r) $urls[] = get_permalink($r->ID);
} else {
    foreach (array(599,600,754,764,802,820,846,1473,1589,102,110) as $id) $urls[] = get_permalink($id);
}

$iskomye = array('border-radius:8px', 'border-radius:6px 6px 0 0');
$ua = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126 Safari/537.36';

$n = 0; $ok = 0; $plohih = 0; $redir = 0;
$sel = array(); $sel_gde = array(); $sel_teg = array(); $primer = array();
$blokov = 0; $kartochek = 0;
$standart = array();          // url => сколько раз «Стандарт» в графе
$standart_2plus = array();

function w032_razdec($s) {
    return preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($m) {
        return mb_convert_encoding(pack('n', hexdec($m[1])), 'UTF-8', 'UTF-16BE');
    }, $s);
}

foreach ($urls as $u) {
    $ch = curl_init($u);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_USERAGENT => $ua,
        CURLOPT_HTTPHEADER => array('Accept: text/html,application/xhtml+xml', 'Accept-Language: ru-RU,ru'),
        CURLOPT_HEADER => true,
    ));
    $otvet = curl_exec($ch);
    $kod  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hlen = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $n++;
    $telo = substr((string) $otvet, $hlen);

    if ($kod >= 300 && $kod < 400) { $redir++; continue; }
    if ($kod !== 200 || strlen($telo) < 5000) {
        $plohih++;
        WP_CLI::warning("замер не удался: $u код $kod тело " . strlen($telo) . " байт");
        continue;
    }
    $ok++;

    /* 1. инлайновые стили вместе с тегом-владельцем */
    if (preg_match_all('/<([a-zA-Z][a-zA-Z0-9]*)\b[^>]*?\sstyle="([^"]*)"/', $telo, $m, PREG_SET_ORDER)) {
        foreach ($m as $one) {
            $teg = strtolower($one[1]);
            $st  = $one[2];
            foreach ($iskomye as $z) {
                if (strpos($st, $z) === false) continue;
                $sel[$z] = (isset($sel[$z]) ? $sel[$z] : 0) + 1;
                $sel_gde[$z][$u] = true;
                $sel_teg[$z][$teg] = (isset($sel_teg[$z][$teg]) ? $sel_teg[$z][$teg] : 0) + 1;
                if (!isset($primer[$z])) $primer[$z] = "<$teg … style=\"" . substr($st, 0, 130) . '"';
            }
        }
    }

    /* 2. блок «Смотрите также» */
    if (strpos($telo, '<!-- rz-related-mu -->') !== false) {
        $blokov++;
        if (preg_match('/<!-- rz-related-mu -->(.*?)<\/div><\/div>/s', $telo, $mb)) {
            $kartochek += substr_count($mb[1], '<a href=');
        }
    }

    /* 3. «Стандарт» в JSON-LD */
    $skol = 0;
    if (preg_match_all('#<script[^>]*application/ld\+json[^>]*>(.*?)</script>#si', $telo, $mj)) {
        foreach ($mj[1] as $j) {
            $skol += substr_count(w032_razdec($j), '"name":"Стандарт"');
        }
    }
    if ($skol > 0) {
        $standart[$u] = $skol;
        if ($skol > 1) $standart_2plus[$u] = $skol;
    }
    usleep(200000);
}

WP_CLI::log("адресов запрошено: $n, ответили 200: $ok, редиректов: $redir, неудавшихся замеров: $plohih");

WP_CLI::log('--- инлайновые скругления');
foreach ($iskomye as $z) {
    $c = isset($sel[$z]) ? $sel[$z] : 0;
    WP_CLI::log(sprintf('  [style*="%s"] элементов %d на %d адресах', $z, $c, $c ? count($sel_gde[$z]) : 0));
    if ($c) {
        $po = array();
        foreach ($sel_teg[$z] as $t => $k) $po[] = "<$t> $k";
        WP_CLI::log('      по тегам: ' . implode(', ', $po));
        WP_CLI::log('      пример: ' . $primer[$z]);
    }
}

WP_CLI::log("--- блок «Смотрите также»: страниц $blokov, карточек в них $kartochek");

WP_CLI::log('--- PropertyValue «Стандарт» в JSON-LD: страниц с ним ' . count($standart)
    . ', из них больше одного раза: ' . count($standart_2plus));
foreach ($standart_2plus as $u => $k) WP_CLI::log("  $k × $u");
