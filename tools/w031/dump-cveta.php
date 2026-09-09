<?php
/**
 * Задание 031. Только чтение: выгружает содержимое всех опубликованных
 * страниц и записей, где в разметке есть инлайновый цвет или border-radius.
 * Пакет перекраски собирается из этой выгрузки на сервере, а не на Бегете:
 * пакет обязан лежать в репозитории до заливки.
 *
 * Запуск: wp eval-file <этот файл>
 * На выходе — одна строка base64 с JSON, чтобы кодировка и переводы строк
 * доехали без потерь через ssh.
 */
if (!defined('ABSPATH')) { echo "только через wp eval-file\n"; return; }

global $wpdb;
$rows = $wpdb->get_results("SELECT ID,post_type,post_name,post_title,post_parent,post_status,post_content
    FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ('page','post')");

$out = array();
foreach ($rows as $r) {
    $c = $r->post_content;
    $est_cvet = preg_match('/(background-color|border-color|background|border|color)\s*:\s*#[0-9a-fA-F]{3,8}/i', $c);
    $est_br   = stripos($c, 'border-radius:') !== false;
    if (!$est_cvet && !$est_br) continue;
    $out[] = array(
        'ID'        => (int) $r->ID,
        'post_type' => $r->post_type,
        'slug'      => $r->post_name,
        'title'     => $r->post_title,
        'parent'    => (int) $r->post_parent,
        'url'       => get_permalink($r->ID),
        'content'   => $c,
    );
}
echo "RZ31_BEGIN\n";
echo base64_encode(wp_json_encode($out)) . "\n";
echo "RZ31_END\n";
echo "выгружено записей: " . count($out) . "\n";
