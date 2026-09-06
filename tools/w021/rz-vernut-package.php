<?php
/**
 * rz-vernut-package.php — вернуть мету _rz_package, затёртую публикатором.
 *
 * rzpub.php в конце каждой позиции безусловно пишет _rz_package = имя пакета.
 * Для пакета ceny-02, который правит только цену в уже существующих страницах,
 * это враньё: страницы заведены пакетами sosedi-01 и related-18-02, и отметка
 * должна остаться прежней. Значения ниже сняты выгрузкой до заливки
 * (tmp/w021/rz-package-do.json).
 *
 * Запуск из public_html:  wp eval-file ../tools/w021/rz-vernut-package.php
 *                         wp eval-file ../tools/w021/rz-vernut-package.php dry
 */
if (!defined('WP_CLI') || !WP_CLI) { fwrite(STDERR, "Только через wp eval-file\n"); exit(1); }
$dry = in_array('dry', $args, true);

$bylo = array(
    101 => 'related-18-02', 110 => 'related-18-02',
    797 => 'sosedi-01', 798 => 'sosedi-01', 799 => 'sosedi-01', 801 => 'sosedi-01',
    802 => 'sosedi-01', 803 => 'sosedi-01', 804 => 'sosedi-01', 805 => 'sosedi-01',
    806 => 'sosedi-01', 807 => 'sosedi-01', 810 => 'sosedi-01', 811 => 'sosedi-01',
    818 => 'sosedi-01', 820 => 'sosedi-01', 823 => 'sosedi-01',
);

$vernuli = $propustili = 0;
foreach ($bylo as $id => $imya) {
    $seychas = get_post_meta($id, '_rz_package', true);
    if ($seychas === $imya) { $propustili++; continue; }
    if ($seychas !== 'ceny-02') {
        WP_CLI::warning("#$id: сейчас «$seychas», а не «ceny-02» — не трогаю");
        $propustili++; continue;
    }
    if (!$dry) { update_post_meta($id, '_rz_package', $imya); }
    WP_CLI::log(sprintf('%s #%d  ceny-02 -> %s', $dry ? 'ВЕРНУТЬ ' : 'вернули ', $id, $imya));
    $vernuli++;
}
WP_CLI::log(sprintf('Возвращено: %d, оставлено как есть: %d', $vernuli, $propustili));
