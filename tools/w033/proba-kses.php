<?php
/**
 * Задание 033, предполётная проверка. Только чтение, в базу не пишет.
 *
 * wp_update_post гонит содержимое через pre_post_content и content_save_pre
 * (там живёт kses). rzpub.php вызывает его БЕЗ wp_slash, поэтому расхождение
 * заметить нужно до заливки, а не по дампу после. Прогоняем фильтры вхолостую
 * ровно по тому тексту, который поедет на сайт.
 *
 * Понимает оба вида пакета: страницы (content) и записи блога (replace).
 *
 * Запуск: wp eval-file <файл> <пакет.json>
 */
if (!defined('WP_CLI') || !WP_CLI) { fwrite(STDERR, "только wp eval-file\n"); exit(1); }
$file = null;
foreach ($args as $a) { if (substr($a, 0, 2) !== '--' && $a !== 'dry') { $file = $a; break; } }
if (!$file || !file_exists($file)) { WP_CLI::error("нет файла пакета"); }
$pkg = json_decode(file_get_contents($file), true);
WP_CLI::log("пакет: " . $pkg['package'] . ", позиций " . count($pkg['items']));
WP_CLI::log("текущий пользователь WP-CLI: " . get_current_user_id()
    . ", unfiltered_html: " . (current_user_can('unfiltered_html') ? 'да' : 'нет'));

function rz33_tekst($it) {
    /* Страница: content целиком. Запись блога: текущий текст с применёнными заменами. */
    if (isset($it['content']) && $it['content'] !== '') return $it['content'];
    $p = get_posts(array('name' => $it['slug'], 'post_type' => array('post', 'page'),
        'post_status' => array('publish', 'draft', 'private'), 'numberposts' => 1));
    if (!$p) return null;
    $c = $p[0]->post_content;
    foreach ((array) (isset($it['replace']) ? $it['replace'] : array()) as $pair) {
        if (!is_array($pair) || count($pair) < 2) continue;
        $c = str_replace($pair[0], $pair[1], $c);
    }
    return $c;
}

$bad = 0; $net = 0;
foreach ($pkg['items'] as $it) {
    $gde = isset($it['url']) ? $it['url'] : $it['slug'];
    $c = rz33_tekst($it);
    if ($c === null) { WP_CLI::warning("$gde: запись не найдена"); $net++; continue; }
    $v = apply_filters('pre_post_content', $c);
    $v = apply_filters('content_save_pre', $v);
    if ($v === $c) continue;
    $bad++;
    WP_CLI::warning("$gde: фильтры изменили содержимое, было " . strlen($c) . " байт, стало " . strlen($v));
    $n = min(strlen($v), strlen($c));
    for ($i = 0; $i < $n && $v[$i] === $c[$i]; $i++) {}
    WP_CLI::log("    было : " . substr($c, max(0, $i - 40), 120));
    WP_CLI::log("    стало: " . substr($v, max(0, $i - 40), 120));
}
WP_CLI::log($bad ? "ИСКАЖЕНО ПОЗИЦИЙ: $bad — заливать нельзя"
                 : "фильтры содержимое не трогают, все " . count($pkg['items']) . " позиций проходят как есть"
                   . ($net ? " (не найдено записей: $net)" : ""));
