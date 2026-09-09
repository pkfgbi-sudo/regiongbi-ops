<?php
/**
 * rz-dump-029.php — выгрузка состояния перед правками задания 029.
 * Только чтение. Выгружает и страницы, и записи блога: пункт 5 задания
 * требует пройти все 22 записи, а публикатор записей их по одной не отдаёт.
 *
 * Запуск из ~/regiongbi.ru/public_html:
 *   wp eval-file ../tools/w029/rz-dump-029.php ../tmp/w029-do.json
 */
if (!defined('WP_CLI') || !WP_CLI) { fwrite(STDERR, "Только через wp eval-file\n"); exit(1); }

$out = null;
foreach ($args as $a) { if (substr($a, 0, 2) !== '--') { $out = $a; break; } }
if (!$out) { WP_CLI::error('укажите файл для выгрузки'); }

$res = array('pages' => array(), 'posts' => array());

foreach (array('page' => 'pages', 'post' => 'posts') as $tip => $klyuch) {
    $ids = get_posts(array(
        'post_type' => $tip, 'post_status' => 'publish',
        'posts_per_page' => -1, 'fields' => 'ids',
    ));
    foreach ($ids as $id) {
        $p = get_post($id);
        $res[$klyuch][] = array(
            'id'      => (int) $id,
            'slug'    => $p->post_name,
            'path'    => parse_url(get_permalink($id), PHP_URL_PATH),
            'title'   => $p->post_title,
            'content' => $p->post_content,
            'rm_title'   => get_post_meta($id, 'rank_math_title', true),
            'rm_desc'    => get_post_meta($id, 'rank_math_description', true),
            'rz_product' => get_post_meta($id, '_rz_product', true),
        );
    }
}
file_put_contents($out, wp_json_encode($res, JSON_UNESCAPED_UNICODE));
WP_CLI::log('страниц: ' . count($res['pages']) . ', записей: ' . count($res['posts'])
    . ', файл ' . $out . ', ' . filesize($out) . ' байт');
