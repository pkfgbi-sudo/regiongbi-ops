<?php
/**
 * Задание 033. Только чтение. Выгружает полное содержимое страниц и записей,
 * в которых лежат ссылки на схлопываемые карточки ФБС и которые сами не
 * схлопываются: раздел 107, страница 204, карточка 640 (остаётся) и три
 * записи блога. Содержимое семнадцати схлопываемых карточек не трогаем —
 * задание это прямо запрещает, поэтому их здесь нет.
 *
 * Запуск: wp eval-file <файл>
 */
if (!defined('ABSPATH')) { echo "только через wp eval-file\n"; return; }
$ids = array(107, 204, 640, 401, 684, 708);
$out = array();
foreach ($ids as $id) {
    $p = get_post($id);
    if (!$p) { $out[$id] = null; continue; }
    $out[$id] = array(
        'ID' => (int) $p->ID, 'type' => $p->post_type, 'slug' => $p->post_name,
        'title' => $p->post_title, 'status' => $p->post_status,
        'url' => get_permalink($p->ID), 'content' => $p->post_content,
    );
}
echo "RZ33S_BEGIN\n";
echo base64_encode(wp_json_encode($out)) . "\n";
echo "RZ33S_END\n";
echo "выгружено: " . count(array_filter($out)) . " из " . count($ids) . "\n";
