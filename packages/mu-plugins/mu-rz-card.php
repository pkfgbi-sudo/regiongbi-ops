<?php
/**
 * Plugin Name: РегионЖБИ — шапка карточки товара
 * Description: Первый экран карточки: фото, цена, кнопка заявки, ключевые характеристики. Собирается из меты _rz_product, содержимое страниц не трогается.
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
 */
if (!defined('ABSPATH')) exit;

/** Характеристики, которые не идут в короткий список шапки. */
function rz_card_skip_specs() {
    return array('цена', 'обозначение в прайсе', 'стандарт');
}

/** Данные товара страницы или null. */
function rz_card_data($post_id) {
    $raw = get_post_meta($post_id, '_rz_product', true);
    if (!$raw) return null;
    $d = json_decode($raw, true);
    if (!is_array($d)) return null;
    if (!isset($d['type']) || $d['type'] !== 'single') return null;
    return $d;
}

/** Цена прописью для блока: «2 350 ₽» или пусто. */
function rz_card_cena($d) {
    if (empty($d['price'])) return '';
    return number_format((float) $d['price'], 0, ',', ' ') . ' ₽';
}

function rz_card_html($d) {
    $ss = 'style="';

    /* --- левая колонка: фото или заглушка с маркой --- */
    $foto = '';
    if (!empty($d['image'])) {
        $foto = '<div style="flex:0 0 260px;max-width:260px">'
              . '<img src="' . esc_url($d['image']) . '" alt="' . esc_attr($d['name']) . '"'
              . ' loading="lazy" decoding="async"'
              . ' style="width:100%;height:200px;object-fit:cover;border-radius:8px;display:block">'
              . '</div>';
    }

    /* --- цена и кнопки --- */
    $cena = rz_card_cena($d);
    $blok_ceny = '';
    if ($cena) {
        $blok_ceny =
            '<div style="margin:0 0 14px">'
          . '<span style="font-size:32px;font-weight:700;color:#23483A;line-height:1.1">' . esc_html($cena) . '</span>'
          . '<span style="font-size:15px;color:#5c6660;margin-left:8px">с НДС за штуку</span>'
          . '</div>';
    } else {
        $blok_ceny =
            '<div style="margin:0 0 14px;font-size:17px;color:#5c6660">'
          . 'Цену по этой марке считаем под объём и адрес — пришлите спецификацию.'
          . '</div>';
    }

    $knopki =
        '<div style="display:flex;flex-wrap:wrap;gap:10px;margin:0 0 14px">'
      . '<a href="/kontakty/#zayavka" data-rz-cel="zayavka"'
      . ' style="display:inline-block;padding:12px 22px;background:#23483A;color:#fff;'
      . 'border-radius:8px;text-decoration:none;font-weight:600;font-size:15px">Запросить счёт</a>'
      . '<a href="tel:+79960970980" data-rz-cel="tel"'
      . ' style="display:inline-block;padding:12px 22px;background:#fff;color:#23483A;'
      . 'border:1px solid #23483A;border-radius:8px;text-decoration:none;font-weight:600;font-size:15px">'
      . '+7 996 097-09-80</a>'
      . '</div>';

    /* --- короткий список характеристик: до пяти строк --- */
    $skip  = rz_card_skip_specs();
    $stroki = array();
    if (!empty($d['specs']) && is_array($d['specs'])) {
        foreach ($d['specs'] as $k => $v) {
            if (in_array(mb_strtolower(trim($k)), $skip, true)) continue;
            $stroki[] = '<div style="display:flex;justify-content:space-between;gap:12px;'
                      . 'padding:5px 0;border-bottom:1px dotted #cfd6d1;font-size:15px">'
                      . '<span style="color:#5c6660">' . esc_html($k) . '</span>'
                      . '<span style="font-weight:600;text-align:right">' . esc_html($v) . '</span>'
                      . '</div>';
            if (count($stroki) >= 5) break;
        }
    }
    $tablica = $stroki ? '<div style="margin:0 0 12px">' . implode('', $stroki) . '</div>' : '';

    /* --- нижняя строка: ГОСТ, обозначение в прайсе, срок --- */
    $meti = array();
    if (!empty($d['gost'])) {
        $meti[] = 'ГОСТ ' . esc_html(implode(', ', array_map('strval', $d['gost'])));
    }
    if (!empty($d['sku'])) {
        $meti[] = 'в прайсе: ' . esc_html($d['sku']);
    }
    $meti[] = 'отгрузка по Москве и области 1–2 дня';
    $meti[] = 'паспорт качества на партию';
    $podval = '<div style="font-size:13px;color:#5c6660;line-height:1.5">'
            . implode(' · ', $meti) . '</div>';

    return
        '<!-- rz-card-plagin -->'
      . '<div style="display:flex;flex-wrap:wrap;gap:22px;margin:18px 0 28px;padding:18px;'
      . 'background:#F6F7F3;border:1px solid #DBDDD6;border-radius:10px">'
      . $foto
      . '<div style="flex:1 1 300px;min-width:260px">'
      . $blok_ceny . $knopki . $tablica . $podval
      . '</div></div>';
}

/** Вставка после </h1>; если h1 нет — в самое начало. */
function rz_card_vstavit($content) {
    if (!is_singular('page') || !in_the_loop() || !is_main_query()) return $content;
    if (strpos($content, '<!-- rz-card -->') !== false) return $content;      // предохранитель
    if (strpos($content, '<!-- rz-card-plagin -->') !== false) return $content; // защита от двойного прогона

    $d = rz_card_data(get_the_ID());
    if (!$d) return $content;

    $blok = rz_card_html($d);

    $pos = stripos($content, '</h1>');
    if ($pos === false) return $blok . $content;

    return substr($content, 0, $pos + 5) . $blok . substr($content, $pos + 5);
}
add_filter('the_content', 'rz_card_vstavit', 12);
