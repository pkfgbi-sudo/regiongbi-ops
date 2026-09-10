<?php
/**
 * Задание 033. Только чтение. Выгружает всё, что нужно для схлопывания
 * карточек ФБС в раздел:
 *   1) раздел /catalog/fbs-bloki/ целиком (содержимое таблицы),
 *   2) 18 карточек ряда ФБС: ID, слаг, URL, длина содержимого, есть ли
 *      _rz_product и фотографии,
 *   3) все страницы и записи, в разметке которых встречается ссылка на любой
 *      из 17 схлопываемых слагов — иначе внутренние ссылки не найти,
 *   4) сколько всего опубликованных страниц и записей (для карты сайта).
 *
 * Запуск: wp eval-file <этот файл>
 * Выход — одна строка base64 с JSON: кодировка и переводы строк доезжают
 * через ssh без потерь.
 */
if (!defined('ABSPATH')) { echo "только через wp eval-file\n"; return; }

$shlop = array(
  'fbs-12-3-6','fbs-12-4-3','fbs-12-4-6','fbs-12-5-6','fbs-12-6-6',
  'fbs-24-3-6','fbs-24-5-6','fbs-24-6-6',
  'fbs-6-3-6','fbs-6-4-3','fbs-6-4-6','fbs-6-5-6',
  'fbs-9-3-6','fbs-9-4-3','fbs-9-4-6','fbs-9-5-6','fbs-9-6-6',
);
$ostayotsya = 'fbs-24-4-6';

global $wpdb;
$out = array('shlop' => $shlop, 'ostayotsya' => $ostayotsya);

/* 1. раздел */
$razdel = $wpdb->get_row("SELECT ID,post_name,post_title,post_content,post_status,post_type
    FROM {$wpdb->posts} WHERE post_name='fbs-bloki' AND post_status='publish' LIMIT 1");
$out['razdel'] = $razdel ? array(
    'ID' => (int) $razdel->ID, 'slug' => $razdel->post_name, 'title' => $razdel->post_title,
    'type' => $razdel->post_type, 'url' => get_permalink($razdel->ID),
    'content' => $razdel->post_content,
    'rz_product' => get_post_meta($razdel->ID, '_rz_product', true),
) : null;

/* 2. карточки ряда */
$kartochki = array();
foreach (array_merge($shlop, array($ostayotsya)) as $s) {
    $r = $wpdb->get_row($wpdb->prepare("SELECT ID,post_name,post_title,post_content,post_status,post_type,post_parent
        FROM {$wpdb->posts} WHERE post_name=%s AND post_status='publish' LIMIT 1", $s));
    if (!$r) { $kartochki[$s] = null; continue; }
    $prod = get_post_meta($r->ID, '_rz_product', true);
    $kartochki[$s] = array(
        'ID' => (int) $r->ID, 'slug' => $r->post_name, 'title' => $r->post_title,
        'type' => $r->post_type, 'parent' => (int) $r->post_parent,
        'url' => get_permalink($r->ID),
        'dlina_soderzhimogo' => strlen($r->post_content),
        'rz_product_est' => ($prod !== '' && $prod !== null),
        'rz_product_tip' => gettype($prod),
        'thumb' => (int) get_post_thumbnail_id($r->ID),
        'kartinok_v_tekste' => preg_match_all('/<img\b/i', $r->post_content),
    );
}
$out['kartochki'] = $kartochki;

/* 3. внутренние ссылки на схлопываемые адреса — по всей базе */
$vse = $wpdb->get_results("SELECT ID,post_type,post_name,post_title,post_content
    FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ('page','post')");
$out['vsego_opublikovano'] = count($vse);
$ssylki = array();
foreach ($vse as $r) {
    $nayd = array();
    foreach ($shlop as $s) {
        // ссылка вида href="…/fbs-12-3-6/" либо href="…/fbs-12-3-6"
        // разделитель ~, а не # — внутри шаблона есть класс [#?]
        $n = preg_match_all('~href="[^"]*/' . preg_quote($s, '~') . '/?(?:[#?][^"]*)?"~i', $r->post_content);
        if ($n) $nayd[$s] = $n;
    }
    // упоминания слага вне href (голый адрес текстом) считаем отдельно
    $golye = array();
    foreach ($shlop as $s) {
        $vsego = preg_match_all('~' . preg_quote($s, '~') . '~i', $r->post_content);
        $v_href = isset($nayd[$s]) ? $nayd[$s] : 0;
        if ($vsego > $v_href) $golye[$s] = $vsego - $v_href;
    }
    if ($golye) $nayd['__vne_href'] = $golye;
    if ($nayd) $ssylki[] = array(
        'ID' => (int) $r->ID, 'type' => $r->post_type, 'slug' => $r->post_name,
        'title' => $r->post_title, 'nayd' => $nayd,
    );
}
$out['ssylki_v_soderzhimom'] = $ssylki;

/* 4. меню и виджеты — ссылки бывают и вне содержимого */
$menu_hits = array();
$menus = wp_get_nav_menus();
foreach ($menus as $m) {
    foreach ((array) wp_get_nav_menu_items($m->term_id) as $it) {
        foreach ($shlop as $s) {
            if (strpos((string) $it->url, '/' . $s) !== false) {
                $menu_hits[] = array('menu' => $m->name, 'item' => $it->title, 'url' => $it->url);
            }
        }
    }
}
$out['menu'] = $menu_hits;

echo "RZ33_BEGIN\n";
echo base64_encode(wp_json_encode($out)) . "\n";
echo "RZ33_END\n";
echo "карточек найдено: " . count(array_filter($kartochki)) . " из 18\n";
echo "страниц со ссылками на схлопываемые: " . count($ssylki) . "\n";
echo "опубликовано страниц и записей: " . count($vse) . "\n";
