<?php
/**
 * Задание 034. Только чтение. Выгружает:
 *   1) содержимое шести переписываемых карточек и их _rz_product — из них
 *      переносятся блоки rz:articles и rz:siblings, которых нет в пакете;
 *   2) содержимое разделов, по таблицам которых сверяются числа пакета;
 *   3) существование и статус двенадцати адресов, на которые ссылаются тексты.
 *
 * Запуск: wp eval-file <файл>
 */
if (!defined('ABSPATH')) { echo "только через wp eval-file\n"; return; }

$slugs = array('ks-7-3', 'ks-7-5', 'ks-15-5', 'ks-20-10', 'pp-10-2', 'pp-20-2');
$razdely = array('koltsa-kolodeznye-ks', 'kryshki-kolodtsev-pp', 'dnishcha-kolodtsev-pd');
$adresa = array(
    'catalog/dnishcha-kolodtsev-pd/pn-7', 'catalog/dnishcha-kolodtsev-pd/pn-15',
    'catalog/dnishcha-kolodtsev-pd/pn-20', 'catalog/koltsa-kolodeznye-ks/ks-7-10',
    'catalog/koltsa-kolodeznye-ks/ks-10-9', 'catalog/koltsa-kolodeznye-ks/ks-15-10',
    'catalog/koltsa-kolodeznye-ks/ks-20-10', 'catalog/kryshki-kolodtsev-pp/pp-15-2',
    'catalog/kryshki-kolodtsev-pp/pp-20-2', 'catalog/kryshki-kolodtsev-pp/pk-15',
    'catalog/kolodtsy-unifitsirovannye/vg-15', 'catalog/lestnicy-dlya-kolodcev',
);

$out = array('kartochki' => array(), 'razdely' => array(), 'adresa' => array());

foreach ($slugs as $s) {
    $p = get_posts(array('name' => $s, 'post_type' => 'page',
        'post_status' => array('publish', 'draft', 'private'), 'numberposts' => 1));
    if (!$p) { $out['kartochki'][$s] = null; continue; }
    $p = $p[0];
    $out['kartochki'][$s] = array(
        'ID' => (int) $p->ID, 'slug' => $p->post_name, 'title' => $p->post_title,
        'status' => $p->post_status, 'url' => get_permalink($p->ID),
        'content' => $p->post_content,
        'rz_product' => get_post_meta($p->ID, '_rz_product', true),
        'rank_math_title' => get_post_meta($p->ID, 'rank_math_title', true),
        'rank_math_description' => get_post_meta($p->ID, 'rank_math_description', true),
    );
}

foreach ($razdely as $s) {
    $p = get_posts(array('name' => $s, 'post_type' => 'page',
        'post_status' => array('publish'), 'numberposts' => 1));
    $out['razdely'][$s] = $p ? array('ID' => (int) $p[0]->ID, 'content' => $p[0]->post_content) : null;
}

foreach ($adresa as $put) {
    $p = get_page_by_path($put, OBJECT, 'page');
    $out['adresa'][$put] = $p ? array('ID' => (int) $p->ID, 'status' => $p->post_status,
        'url' => get_permalink($p->ID)) : null;
}

/* Шаблонность: все предложения длиннее 40 знаков по всем опубликованным
   страницам и записям — чтобы посчитать её до и после одной меркой. */
global $wpdb;
$rows = $wpdb->get_results("SELECT ID,post_type,post_name,post_content FROM {$wpdb->posts}
    WHERE post_status='publish' AND post_type IN ('page','post')");
$out['vsego_opublikovano'] = count($rows);
$korpus = array();
foreach ($rows as $r) $korpus[$r->post_name] = wp_strip_all_tags($r->post_content);
$out['korpus'] = $korpus;

echo "RZ34_BEGIN\n";
echo base64_encode(wp_json_encode($out)) . "\n";
echo "RZ34_END\n";
echo "карточек: " . count(array_filter($out['kartochki'])) . " из " . count($slugs) . "\n";
echo "разделов: " . count(array_filter($out['razdely'])) . " из " . count($razdely) . "\n";
echo "адресов найдено: " . count(array_filter($out['adresa'])) . " из " . count($adresa) . "\n";
