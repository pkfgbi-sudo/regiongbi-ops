<?php
/**
 * Задание 031, предполётная проверка. Только чтение.
 *
 * Содержимое главной — блоки Gutenberg, а не голый HTML: в нём есть
 * комментарии <!-- wp:… --> с JSON-атрибутами. Через rzpub.php таких страниц
 * ещё не заливали. wp_update_post прогоняет содержимое через фильтры
 * pre_post_content и content_save_pre (там живёт kses), и если kses тронет
 * комментарии блоков или кавычки в JSON, страница уедет искажённой, а откат
 * будет уже из дампа. Поэтому фильтры прогоняются заранее, вхолостую.
 *
 * Запуск: wp eval-file <файл> <пакет.json>
 */
if (!defined('WP_CLI') || !WP_CLI) { fwrite(STDERR, "только wp eval-file\n"); exit(1); }
$file = null;
foreach ($args as $a) { if (substr($a, 0, 2) !== '--') { $file = $a; break; } }
if (!$file || !file_exists($file)) { WP_CLI::error("нет файла пакета"); }
$pkg = json_decode(file_get_contents($file), true);
WP_CLI::log("пакет: " . $pkg['package'] . ", позиций " . count($pkg['items']));
WP_CLI::log("текущий пользователь WP-CLI: " . get_current_user_id()
    . ", unfiltered_html: " . (current_user_can('unfiltered_html') ? 'да' : 'нет'));
$bad = 0;
foreach ($pkg['items'] as $it) {
    $v = apply_filters('pre_post_content', $it['content']);
    $v = apply_filters('content_save_pre', $v);
    if ($v === $it['content']) continue;
    $bad++;
    WP_CLI::warning($it['url'] . ": фильтры изменили содержимое, было "
        . strlen($it['content']) . " байт, стало " . strlen($v));
    /* показать первое расхождение */
    $n = min(strlen($v), strlen($it['content']));
    for ($i = 0; $i < $n && $v[$i] === $it['content'][$i]; $i++) {}
    WP_CLI::log("    было : " . substr($it['content'], max(0, $i - 40), 120));
    WP_CLI::log("    стало: " . substr($v, max(0, $i - 40), 120));
}
WP_CLI::log($bad ? "ИСКАЖЕНО ПОЗИЦИЙ: $bad — заливать нельзя"
                 : "фильтры содержимое не трогают, все " . count($pkg['items']) . " позиций проходят как есть");
