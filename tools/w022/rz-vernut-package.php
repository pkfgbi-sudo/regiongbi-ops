<?php
/**
 * rz-vernut-package.php (задание 022) — вернуть мету _rz_package, затёртую
 * публикатором.
 *
 * rzpub.php в конце каждой позиции безусловно пишет _rz_package = имя пакета.
 * Пакет vp-03 правит одну фразу в двух карточках, заведённых пакетом vp-01,
 * и отметка «каким пакетом страница заведена» должна остаться прежней.
 * Значения сняты выгрузкой до заливки (tmp/w022/dump.json).
 *
 * Запуск из public_html:  wp eval-file ../tools/w022/rz-vernut-package.php
 *                         wp eval-file ../tools/w022/rz-vernut-package.php dry
 */
if (!defined('WP_CLI') || !WP_CLI) { fwrite(STDERR, "Только через wp eval-file\n"); exit(1); }
$dry = in_array('dry', $args, true);

$bylo = array(
    1533 => 'vp-01',   // /catalog/plity-perekrytiya-kanalov-vp/vp-22-6/
    1534 => 'vp-01',   // /catalog/plity-perekrytiya-kanalov-vp/vp-28-12/
);

$vernuli = $propustili = 0;
foreach ($bylo as $id => $imya) {
    $seychas = get_post_meta($id, '_rz_package', true);
    if ($seychas === $imya) { $propustili++; continue; }
    if ($seychas !== 'vp-03') {
        WP_CLI::warning("#$id: сейчас «$seychas», а не «vp-03» — не трогаю");
        $propustili++; continue;
    }
    if (!$dry) { update_post_meta($id, '_rz_package', $imya); }
    WP_CLI::log(sprintf('%s #%d  vp-03 -> %s', $dry ? 'ВЕРНУТЬ ' : 'вернули ', $id, $imya));
    $vernuli++;
}
WP_CLI::log(sprintf('Возвращено: %d, оставлено как есть: %d', $vernuli, $propustili));
