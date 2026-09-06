<?php
/**
 * rz-dump-ceny.php — выгрузка состояния страниц каталога перед правкой цен
 * (задание 019). Только чтение: ничего не пишет ни в базу, ни в файлы сайта.
 *
 * Запуск из ~/regiongbi.ru/public_html:
 *   wp eval-file ../tools/w019/rz-dump-ceny.php ../tmp/w019-dump.json
 *
 * Выгружает все опубликованные страницы: ID, slug, путь, родитель, заголовок,
 * содержимое, мету _rz_product и SEO-мету Rank Math. Разбор — на сервере,
 * чтобы не гонять wp-cli по одной странице (раздел 3.9 AGENTS.md).
 */
if (!defined('WP_CLI') || !WP_CLI) { fwrite(STDERR, "Только через wp eval-file\n"); exit(1); }

$out = null;
foreach ($args as $a) { if (substr($a, 0, 2) !== '--') { $out = $a; break; } }
if (!$out) { WP_CLI::error('укажите файл для выгрузки'); }

$ids = get_posts(array(
    'post_type' => 'page', 'post_status' => 'publish',
    'posts_per_page' => -1, 'fields' => 'ids',
));

$res = array();
foreach ($ids as $id) {
    $p = get_post($id);
    $res[] = array(
        'id'      => (int) $id,
        'slug'    => $p->post_name,
        'path'    => parse_url(get_permalink($id), PHP_URL_PATH),
        'parent'  => (int) $p->post_parent,
        'title'   => $p->post_title,
        'content' => $p->post_content,
        'rz_product' => get_post_meta($id, '_rz_product', true),
        'rm_title'   => get_post_meta($id, 'rank_math_title', true),
        'rm_desc'    => get_post_meta($id, 'rank_math_description', true),
        'package'    => get_post_meta($id, '_rz_package', true),
    );
}
file_put_contents($out, wp_json_encode($res, JSON_UNESCAPED_UNICODE));
WP_CLI::log('страниц выгружено: ' . count($res) . ', файл ' . $out . ', ' . filesize($out) . ' байт');
