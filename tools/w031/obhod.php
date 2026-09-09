<?php
/**
 * Задание 031. Обход сайта ПО ОДНОМУ адресу со стороны Бегета (раздел 5
 * AGENTS.md). Только чтение.
 *
 * Считает не «встречается ли строка в теле» — так находятся сами селекторы
 * заплаток и правила темы в <style> внутри head, — а «есть ли на странице
 * элемент, под который селектор-заплатка попадёт». Для этого из сырого HTML
 * вырезаются атрибуты style="…" и подстрока ищется только в них, ровно как
 * это делает CSS-селектор [style*="…"] (регистр учитывается, как в CSS).
 *
 * Отдельно: остатки старой палитры в инлайновых стилях (регистронезависимо,
 * по свойствам) и коды ответов. Короткий ответ — неудавшийся замер, а не
 * отрицательный результат.
 *
 * Запуск: wp eval-file <файл> [all]   — «all» позиционным аргументом, а не
 *         --all: WP-CLI разбирает двойное тире как свой флаг и падает.
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
    foreach (array(101,102,104,105,106,107,108,109,110,111,113,119,301,407,1157) as $id) {
        $urls[] = get_permalink($id);
    }
}
$urls[] = home_url('/home/');

/* подстроки ровно из селекторов-заплаток mu-rz-theme.php */
$zapl = array(
    'background-color:#23483a', 'color:#23483a', 'background-color:#2f6b52',
    'background-color:#1B1E22', 'color:#6c726b', 'border-radius:16px', 'border-radius:8px',
);
/* остатки старой палитры — по свойствам, регистр не важен */
$hex = array('#23483a','#23272e','#1b1e22','#2f6b52','#9fbbad','#c6cbc6','#b7cec1','#6c726b','#f6f7f3');
$ua = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126 Safari/537.36';

$sel = array(); $sel_gde = array(); $primer = array();
$svod = array(); $svod_primer = array();
$plohih = 0; $n = 0; $ok = 0; $redir = array(); $inline_vsego = 0;

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
    $zag  = substr((string) $otvet, 0, $hlen);
    $telo = substr((string) $otvet, $hlen);
    if ($kod >= 300 && $kod < 400) {
        preg_match('/^location:\s*(\S+)/mi', $zag, $m);
        $redir[] = "$u  код $kod  ->  " . (isset($m[1]) ? trim($m[1]) : '?');
        continue;
    }
    if ($kod !== 200 || strlen($telo) < 5000) {
        $plohih++;
        WP_CLI::warning("замер не удался: $u код $kod тело " . strlen($telo) . " байт");
        continue;
    }
    $ok++;
    preg_match_all('/\sstyle="([^"]*)"/', $telo, $m);
    $stili = $m[1];
    $inline_vsego += count($stili);

    foreach ($stili as $s) {
        foreach ($zapl as $z) {
            if (strpos($s, $z) !== false) {
                $sel[$z] = (isset($sel[$z]) ? $sel[$z] : 0) + 1;
                $sel_gde[$z][$u] = true;
                if (!isset($primer[$z])) $primer[$z] = $s;
            }
        }
        foreach ($hex as $h) {
            if (preg_match('/(background-color|border-color|background|border|color)\s*:\s*'
                . preg_quote($h, '/') . '\b/i', $s)) {
                $svod[$h] = (isset($svod[$h]) ? $svod[$h] : 0) + 1;
                $svod_gde[$h][$u] = true;
                if (!isset($svod_primer[$h])) $svod_primer[$h] = $s;
            }
        }
    }
    usleep(200000);
}

WP_CLI::log("адресов запрошено: $n, ответили 200: $ok, редиректов: " . count($redir)
    . ", неудавшихся замеров: $plohih, инлайновых стилей осмотрено: $inline_vsego");
foreach ($redir as $r) WP_CLI::log("редирект: $r");

WP_CLI::log("--- остатки старой палитры В ИНЛАЙНОВЫХ СТИЛЯХ");
if (!$svod) { WP_CLI::log("  ни одного"); }
foreach ($hex as $h) {
    if (empty($svod[$h])) continue;
    WP_CLI::log(sprintf("  %-10s элементов %d на %d адресах", $h, $svod[$h], count($svod_gde[$h])));
    WP_CLI::log("      пример: " . substr($svod_primer[$h], 0, 150));
}

WP_CLI::log("--- под какие селекторы-заплатки ещё есть элементы");
foreach ($zapl as $z) {
    $c = isset($sel[$z]) ? $sel[$z] : 0;
    WP_CLI::log(sprintf('  [style*="%s"] %s элементов %d на %d адресах',
        $z, str_repeat(' ', max(0, 26 - strlen($z))), $c, $c ? count($sel_gde[$z]) : 0));
    if ($c) WP_CLI::log("      пример: " . substr($primer[$z], 0, 150));
}
