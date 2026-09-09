<?php
/**
 * Plugin Name: РегионЖБИ — SEO-фиксы
 * Схема Organization (Rank Math), подтверждение прав Вебмастера, редиректы, дубли.
 */
if (!defined('ABSPATH')) exit;

/* 0. Подтверждение прав Яндекс.Вебмастера */
add_action('wp_head', function () {
    echo '<meta name="yandex-verification" content="188d899d0824449b" />' . "\n";
}, 1);

/* 1. Дополняем Organization в @graph Rank Math (телефон, почта, весь ЦФО) */
add_filter('rank_math/json_ld', function ($data, $jsonld) {
    $cfo = [
        'Москва', 'Московская область', 'Белгородская область', 'Брянская область',
        'Владимирская область', 'Воронежская область', 'Ивановская область', 'Калужская область',
        'Костромская область', 'Курская область', 'Липецкая область', 'Орловская область',
        'Рязанская область', 'Смоленская область', 'Тамбовская область', 'Тверская область',
        'Тульская область', 'Ярославская область',
    ];
    foreach ($data as $k => $item) {
        if (empty($item['@type'])) continue;
        $type = (array) $item['@type'];
        if (array_intersect($type, ['Organization', 'LocalBusiness', 'Corporation'])) {
            $data[$k]['telephone'] = '+7 996 097-09-80';
            $data[$k]['email'] = 'zakaz@regiongbi.ru';
            $data[$k]['areaServed'] = $cfo;
        }
    }
    return $data;
}, 99, 2);

/* 2. /zayavka/ → форма заявки */
add_action('template_redirect', function () {
    if (is_404() && trim($_SERVER['REQUEST_URI'], '/') === 'zayavka') {
        wp_redirect(home_url('/kontakty/#zayavka'), 301);
        exit;
    }
});

/* 3. Рубрика «Блог» дублирует /blog/ — закрыта от индексации.
 *
 * Раньше теги robots и canonical печатались здесь напрямую, из-за чего на
 * странице рубрики оказывалось по два конфликтующих тега: свой «noindex» и
 * рядом «index, follow» от Rank Math, плюс два разных canonical.
 * Теперь noindex и canonical заданы в мета-полях термина (rank_math_robots,
 * rank_math_canonical_url) — Rank Math выводит один корректный набор тегов
 * и заодно сам исключает рубрику из карты сайта. */

/* 4. /home/ — вторая живая копия главной, 301 на главную (задание 031).
 *
 * Страница «home» (ID 1157) отдавала 200 по /home/ с тем же H1, тем же
 * содержимым и своей канонической ссылкой на себя при robots: index, follow.
 * Это дубль содержимого — ровно то, за что Яндекс уже исключил у нас 28
 * страниц. В карту сайта она не входит, но открыта и индексируема.
 *
 * Именно редирект, а не удаление: редирект обратим, удаление нет, и удаление
 * страниц — решение владельца. Ищем по слагу, а не по ID: ID при пересоздании
 * страницы сменится, и правило начнёт молча промахиваться. Сверка с
 * page_on_front обязательна — если когда-нибудь главной назначат саму «home»,
 * без этой проверки получится вечный редирект страницы на себя.
 */
add_action('template_redirect', function () {
    if (!is_page()) return;
    $p = get_queried_object();
    if (!($p instanceof WP_Post) || $p->post_name !== 'home') return;
    if ((int) $p->ID === (int) get_option('page_on_front')) return;
    wp_redirect(home_url('/'), 301);
    exit;
});

/* И убрать /home/ из карты сайта. Задание считало, что её там нет; на деле она
 * лежала в page-sitemap1.xml (замер 09.09.2026: 248 адресов в трёх подкартах,
 * /home/ среди них). Оставить в карте адрес, который отдаёт 301, — значит
 * самим звать робота на редирект.
 *
 * Через фильтр, а не через мета-поле rank_math_robots: правка в файле
 * откатывается вместе с файлом и не трогает базу. Rank Math ждёт от
 * rank_math/sitemap/entry массив с ключом loc и выбрасывает запись, если
 * вернуть пустое (class-post-type.php: if (empty($url)) continue).
 */
add_filter('rank_math/sitemap/entry', function ($url, $type, $object) {
    if ($type !== 'post' || !is_object($object) || empty($object->post_name)) return $url;
    if ($object->post_name !== 'home') return $url;
    if ((int) $object->ID === (int) get_option('page_on_front')) return $url;
    return array();
}, 10, 3);
