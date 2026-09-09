<?php
/**
 * Plugin Name: РегионЖБИ — шапка карточки товара
 * Description: Первый экран карточки: крошки, фото, цена, кнопка заявки, ключевые характеристики. Собирается из меты _rz_product, содержимое страниц не трогается.
 *
 * Зачем (задание 028). Сейчас первый экран карточки — заголовок и два абзаца
 * текста. Ни цены, ни кнопки, ни фотографии: чтобы узнать цену, посетитель
 * должен долистать до середины таблицы характеристик. У конкурентов цена и
 * кнопка стоят в первом экране справа от фото — и это единственное, что в их
 * оформлении бесспорно лучше нашего.
 *
 * Почему плагином, а не правкой 200 страниц: данные уже лежат в мете
 * _rz_product (её заполняет tools/rz-extract-products.php). Блок собирается
 * из неё на лету — правка вида блока остаётся правкой одного файла.
 *
 * КЛЮЧЕВОЕ УСЛОВИЕ. Если в содержимом страницы есть маркер <!-- rz-card -->,
 * плагин молчит: это позволяет при необходимости зашить свой блок на отдельной
 * странице и не получить два подряд.
 *
 * Блок встаёт СРАЗУ ПОСЛЕ </h1>, а не перед контентом: заголовок должен
 * остаться первым элементом документа.
 *
 * Приоритет 12 — позже wpautop (10) и раньше mu-rz-theme.php (20, обёртка
 * таблиц) и mu-rz-related.php (20, блок «Смотрите также»). В блоке нет
 * <table>, поэтому обёртка прокрутки его не касается.
 *
 * Класс rz-card-cta на главной кнопке — точка сцепки с mu-rz-theme.php:
 * заливка и цвет текста заданы здесь инлайном, а наведение живёт там, потому
 * что инлайновый стиль :hover не умеет.
 *
 * Разметку блок не трогает вовсе: mu-rz-product-schema.php читает ту же мету
 * _rz_product, а mu-rz-faq.php разбирает СЫРОЕ $post->post_content, куда
 * фильтр the_content не достаёт.
 */
if (!defined('ABSPATH')) exit;

/** Характеристики, которые не идут в короткий список шапки: они уже в подвале блока. */
function rz_card_skip_specs() {
    return array('цена', 'обозначение в прайсе', 'стандарт');
}

/* Цвета берём из переменных mu-rz-theme.php, запасные значения — те же самые.
 * Свой набор цветов рядом с темой сайта смотрелся бы чужим блоком. */
if (!defined('RZ_CARD_INK'))     define('RZ_CARD_INK',     'var(--rz-ink,#2A2F33)');
if (!defined('RZ_CARD_MUTED'))   define('RZ_CARD_MUTED',   'var(--rz-muted,#676C71)');
if (!defined('RZ_CARD_LINE'))    define('RZ_CARD_LINE',    'var(--rz-line,#DEDEDA)');
if (!defined('RZ_CARD_LINE2'))   define('RZ_CARD_LINE2',   'var(--rz-line-2,#E9E9E5)');
if (!defined('RZ_CARD_TINT'))    define('RZ_CARD_TINT',    'var(--rz-tint,#F6F6F3)');
if (!defined('RZ_CARD_SURFACE')) define('RZ_CARD_SURFACE', 'var(--rz-surface,#FFFFFF)');
if (!defined('RZ_CARD_ACCENT'))  define('RZ_CARD_ACCENT',  'var(--rz-accent,#C8791A)');
/* Текст на янтарной заливке — только тёмный: белый даёт на #C8791A контраст
   3,38 при норме 4,5. Задание 030. */
if (!defined('RZ_CARD_ACCENT_FG')) define('RZ_CARD_ACCENT_FG', 'var(--rz-accent-fg,#17191A)');

/** Данные товара страницы или null. Блок ставим только там, где есть что показать. */
function rz_card_data($post_id) {
    $raw = get_post_meta($post_id, '_rz_product', true);
    if (!$raw || !is_string($raw)) return null;
    $d = json_decode($raw, true);
    if (!is_array($d)) return null;
    if (!isset($d['type']) || $d['type'] !== 'single') return null;

    /* Ни цены, ни характеристик, ни фото — блок был бы пустой рамкой с кнопками.
       Таких страниц две (kl, vl-2): это групповые страницы без своей марки. */
    if (empty($d['price']) && empty($d['specs']) && empty($d['image'])) return null;
    return $d;
}

/** Цена прописью для блока: «2 350 ₽» или пусто. */
function rz_card_cena($d) {
    if (empty($d['price'])) return '';
    /* Разделитель и пробел перед знаком — неразрывные: «2 350 ₽» не должно
       разрываться переносом строки на узком экране. */
    return number_format((float) $d['price'], 0, ',', "\xc2\xa0") . "\xc2\xa0₽";
}

/**
 * Подпись под ценой берём из текста самой страницы, а не придумываем.
 * На 159 карточках из 164 написано «с НДС за штуку», ещё на двух — «с НДС»
 * без единицы (ВГ-15, ВД-8). Чего на странице не написано — того не пишем.
 */
function rz_card_podpis_ceny($post_id) {
    $c = (string) get_post_field('post_content', $post_id);
    $ndc = (mb_strpos($c, 'с НДС') !== false);
    $sht = (mb_strpos($c, 'за штуку') !== false);
    if ($ndc && $sht) return 'с НДС за штуку';
    if ($ndc) return 'с НДС';
    if ($sht) return 'за штуку';
    return '';
}

/**
 * Хлебные крошки. Rank Math их умеет и в настройках включены
 * (breadcrumbs: on), но Blocksy их не выводит: в HTML карточек есть только
 * CSS для .ct-breadcrumbs и BreadcrumbList в JSON-LD, самой строки нет.
 * Выводим сами — по заданию 028, перед шапкой.
 */
function rz_card_kroshki() {
    if (!function_exists('rank_math_the_breadcrumbs')) return '';
    ob_start();
    rank_math_the_breadcrumbs();
    $b = trim((string) ob_get_clean());
    if ($b === '') return '';
    return '<div class="rz-card-kroshki" style="font-size:12.5px;line-height:1.5;'
         . 'color:' . RZ_CARD_MUTED . ';margin:0 0 12px">' . $b . '</div>';
}

function rz_card_html($d, $post_id) {
    /* --- левая колонка: фотография этого изделия, если она есть ---
       Обрезки нет: object-fit:cover срезал бы у кольца верхний торец, по
       которому на фото и видно толщину стенки. Первый экран — картинку не
       откладываем в lazy. */
    $foto = '';
    if (!empty($d['image'])) {
        $foto = '<div style="flex:0 0 260px;max-width:100%">'
              . '<img src="' . esc_url($d['image']) . '" alt="' . esc_attr($d['name']) . '"'
              . ' decoding="async"'
              . ' style="width:100%;height:auto;border-radius:8px;display:block;'
              . 'background:' . RZ_CARD_SURFACE . '">'
              . '</div>';
    }

    /* --- цена --- */
    $cena = rz_card_cena($d);
    if ($cena) {
        $podpis = rz_card_podpis_ceny($post_id);
        $blok_ceny =
            '<div style="margin:0 0 14px">'
          . '<span style="font-size:32px;font-weight:700;line-height:1.1;color:' . RZ_CARD_INK . '">'
          . esc_html($cena) . '</span>'
          . ($podpis
              ? '<span style="font-size:15px;color:' . RZ_CARD_MUTED . ';margin-left:8px">'
                . esc_html($podpis) . '</span>'
              : '')
          . '</div>';
    } else {
        $blok_ceny =
            '<div style="margin:0 0 14px;font-size:17px;color:' . RZ_CARD_MUTED . '">'
          . 'Цену по этой марке считаем под объём и адрес — пришлите спецификацию.'
          . '</div>';
    }

    /* --- кнопки ---
       Цель tel в mu-rz-celi.php ловится делегированием по href^="tel:" — эта
       кнопка попадает в неё сама, привязывать нечего. Цель zayavka там же
       означает успешно отправленную форму (wpcf7mailsent), а не клик по
       ссылке на неё: вешать её на клик значило бы испортить существующую
       цель. Атрибуты data-rz-cel оставлены как разметка намерения, цели под
       «переход к форме» в кабинете Метрики нет — см. отчёт 028. */
    $knopki =
        '<div style="display:flex;flex-wrap:wrap;gap:10px;margin:0 0 16px">'
      . '<a href="/kontakty/#zayavka" data-rz-cel="zayavka" class="rz-card-cta"'
      . ' style="flex:1 1 auto;text-align:center;padding:12px 22px;background:' . RZ_CARD_ACCENT . ';'
      . 'color:' . RZ_CARD_ACCENT_FG . ';border:1px solid ' . RZ_CARD_ACCENT . ';border-radius:8px;'
      . 'text-decoration:none;font-weight:600;font-size:15px">Запросить счёт</a>'
      . '<a href="tel:+79960970980" data-rz-cel="tel"'
      . ' style="flex:1 1 auto;text-align:center;padding:12px 22px;background:' . RZ_CARD_SURFACE . ';'
      . 'color:' . RZ_CARD_INK . ';border:1px solid ' . RZ_CARD_LINE . ';border-radius:8px;'
      . 'text-decoration:none;font-weight:600;font-size:15px">+7 996 097-09-80</a>'
      . '</div>';

    /* --- короткий список характеристик: до пяти строк --- */
    $skip   = rz_card_skip_specs();
    $stroki = array();
    if (!empty($d['specs']) && is_array($d['specs'])) {
        foreach ($d['specs'] as $k => $v) {
            if (in_array(mb_strtolower(trim($k)), $skip, true)) continue;
            $stroki[] = '<div style="display:flex;justify-content:space-between;gap:12px;'
                      . 'padding:5px 0;border-bottom:1px solid ' . RZ_CARD_LINE2 . ';font-size:15px">'
                      . '<span style="color:' . RZ_CARD_MUTED . '">' . esc_html($k) . '</span>'
                      . '<span style="font-weight:600;text-align:right;color:' . RZ_CARD_INK . '">'
                      . esc_html($v) . '</span>'
                      . '</div>';
            if (count($stroki) >= 5) break;
        }
    }
    $tablica = $stroki ? '<div style="margin:0 0 12px">' . implode('', $stroki) . '</div>' : '';

    /* --- нижняя строка: ГОСТ, обозначение в прайсе, срок отгрузки --- */
    $meti = array();
    if (!empty($d['gost']) && is_array($d['gost'])) {
        $meti[] = 'ГОСТ ' . esc_html(implode(', ', array_map('strval', $d['gost'])));
    }
    if (!empty($d['sku'])) {
        $meti[] = 'в прайсе: ' . esc_html($d['sku']);
    }
    $meti[] = 'отгрузка по Москве и области 1–2 дня';
    $meti[] = 'паспорт качества на партию';
    $podval = '<div style="font-size:13px;line-height:1.5;color:' . RZ_CARD_MUTED . '">'
            . implode(' · ', $meti) . '</div>';

    return
        '<!-- rz-card-plagin -->'
      . '<div class="rz-card" style="display:flex;flex-wrap:wrap;gap:22px;margin:18px 0 28px;'
      . 'padding:18px;background:' . RZ_CARD_TINT . ';border:1px solid ' . RZ_CARD_LINE . ';'
      . 'border-radius:10px">'
      . $foto
      . '<div style="flex:1 1 320px;min-width:min(100%,260px)">'
      . $blok_ceny . $knopki . $tablica . $podval
      . '</div></div>';
}

/** Вставка после </h1>; если h1 нет — в самое начало. */
function rz_card_vstavit($content) {
    if (!is_singular('page') || !in_the_loop() || !is_main_query()) return $content;
    if (strpos($content, '<!-- rz-card -->') !== false) return $content;        // предохранитель
    if (strpos($content, '<!-- rz-card-plagin -->') !== false) return $content; // защита от двойного прогона

    $id = get_the_ID();
    if (!$id) return $content;

    $d = rz_card_data($id);
    if (!$d) return $content;

    $blok = rz_card_kroshki() . rz_card_html($d, $id);

    $pos = stripos($content, '</h1>');
    if ($pos === false) return $blok . $content;

    return substr($content, 0, $pos + 5) . $blok . substr($content, $pos + 5);
}
add_filter('the_content', 'rz_card_vstavit', 12);
