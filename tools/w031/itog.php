<?php
/**
 * Задание 031, итоговые проверки. Только чтение. Запуск на Бегете:
 *   wp eval-file <файл>
 *
 * 1) карта сайта: сколько адресов и нет ли в ней /home/
 * 2) /home/ и главная: коды ответов и Location
 * 3) токены в выводе главной: новые из темы на месте, разведённые из
 *    mu-regionzhbi-setup.php печатаются под своими именами
 */
if (!defined('WP_CLI') || !WP_CLI) { fwrite(STDERR, "только wp eval-file\n"); exit(1); }
$ua = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126 Safari/537.36';

function rz31_get($u, $ua, $sled = false) {
    $ch = curl_init($u);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25,
        CURLOPT_FOLLOWLOCATION => $sled, CURLOPT_USERAGENT => $ua, CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => array('Accept: text/html,application/xhtml+xml', 'Accept-Language: ru-RU,ru'),
    ));
    $o = curl_exec($ch);
    $r = array(
        'kod'  => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE),
        'zag'  => substr((string) $o, 0, (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE)),
        'telo' => substr((string) $o, (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE)),
    );
    curl_close($ch);
    return $r;
}

WP_CLI::log("=== 1. карта сайта");
$idx = rz31_get(home_url('/sitemap_index.xml'), $ua);
preg_match_all('#<loc>([^<]+)</loc>#', $idx['telo'], $m);
$karty = $m[1];
WP_CLI::log("  индекс: код {$idx['kod']}, подкарт " . count($karty));
$vsego = 0; $est_home = array();
foreach ($karty as $k) {
    $s = rz31_get($k, $ua);
    preg_match_all('#<loc>([^<]+)</loc>#', $s['telo'], $mm);
    $vsego += count($mm[1]);
    foreach ($mm[1] as $u) if (preg_match('#/home/?$#', $u)) $est_home[] = $u;
    WP_CLI::log(sprintf("    %-60s код %d, адресов %d", $k, $s['kod'], count($mm[1])));
    usleep(300000);
}
WP_CLI::log("  всего адресов в карте: $vsego");
WP_CLI::log("  /home/ в карте: " . ($est_home ? 'ЕСТЬ — ' . implode(', ', $est_home) : 'нет'));

WP_CLI::log("=== 2. /home/ и главная");
foreach (array('/home/', '/') as $p) {
    $r = rz31_get(home_url($p), $ua);
    preg_match('/^location:\s*(\S+)/mi', $r['zag'], $mm);
    WP_CLI::log(sprintf("  %-8s код %d  тело %d байт  Location: %s",
        $p, $r['kod'], strlen($r['telo']), isset($mm[1]) ? trim($mm[1]) : '—'));
    usleep(300000);
}
$g = rz31_get(home_url('/home/'), $ua, true);
WP_CLI::log("  /home/ по цепочке: код {$g['kod']}, тело " . strlen($g['telo']) . " байт, H1: "
    . (preg_match('#<h1[^>]*>(.*?)</h1>#si', $g['telo'], $mm) ? trim(strip_tags($mm[1])) : '—'));

WP_CLI::log("=== 3. токены в выводе главной");
$r = rz31_get(home_url('/'), $ua);
foreach (array('--rz-paper:#F4F4F1', '--rz-ink:#2A2F33', '--rz-line:#DEDEDA', '--rz-tint:#F6F6F3',
               '--rz-accent:#C8791A', '--rz-accent-fg:#17191A',
               '--rzs-line:#DBDDD6', '--rzs-tint:#F6F7F3',
               '--rz-pine:#23483A', 'var(--rzs-line)', 'var(--rzs-tint)') as $t) {
    WP_CLI::log(sprintf("  %-24s %d", $t, substr_count($r['telo'], $t)));
}
foreach (array('--rz-line:#DBDDD6', '--rz-tint:#F6F7F3') as $t) {
    WP_CLI::log(sprintf("  %-24s %d  (старые имена из setup — должно быть 0)", $t, substr_count($r['telo'], $t)));
}
