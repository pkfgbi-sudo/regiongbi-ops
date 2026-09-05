<?php
/**
 * Plugin Name: РегионЖБИ — блок «Смотрите также»
 * Description: Перелинковка разделов каталога фильтром the_content. Раньше блок лежал копией в содержимом 19 страниц — теперь он один на всех.
 *
 * Зачем перенесли (задание 010):
 *   — 37 карточек из 76 не имеют фотографии и рисовались пустым серым
 *     прямоугольником #eef2f0; вместо него теперь текстовая плитка с маркой;
 *   — правка вида блока была правкой девятнадцати страниц.
 *
 * КЛЮЧЕВОЕ УСЛОВИЕ. Если в содержимом страницы ещё лежит старый блок —
 * маркер <!-- rz-related --> — плагин молчит. Это позволяет вычищать
 * страницы по одной, не получая два блока подряд.
 *
 * Связи не подобраны автоматически: таблица ниже снята с того, что уже стоит
 * на сайте (packages/related-src/*.html), порядок карточек сохранён.
 *
 * Ключ таблицы — путь страницы (get_page_uri), а не последний слаг: страницы
 * каталога вложены в /catalog/, и get_page_by_path по одному слагу их не находит.
 *
 * Картинка карточки — миниатюра целевой страницы, а не зашитый путь к файлу:
 * поменяется картинка в медиатеке — блок подхватит сам.
 */
if (!defined('ABSPATH')) exit;

/** Страница-источник => четыре страницы, на которые она ссылается. Порядок значим. */
function rz_related_svyazi() {
    return array(
        'catalog/blok-protivofiltratsionnogo-ekrana' => array('catalog/fbs-bloki', 'catalog/fundamenty-pod-dorozhnye-znaki', 'catalog/otkosnye-stenki', 'catalog/lekalnye-bloki-bl'),
        'catalog/dnishcha-kolodtsev-pd'              => array('catalog/koltsa-kolodeznye-ks', 'catalog/koltsa-s-dnom-ktsd', 'catalog/opornye-plity-op', 'catalog/kryshki-kolodtsev-pp'),
        'catalog/elementy-kollektorov'               => array('catalog/plity-perekrytiya-kanalov-vp', 'catalog/neprohodnye-kanaly-nkl', 'catalog/kolodtsy-unifitsirovannye', 'catalog/lekalnye-bloki-bl'),
        'catalog/fbs-bloki'                          => array('catalog/neprohodnye-kanaly-nkl', 'catalog/plity-perekrytiya-kanalov-vp', 'catalog/opornye-plity-op', 'catalog/blok-protivofiltratsionnogo-ekrana'),
        'catalog/fundamenty-pod-dorozhnye-znaki'     => array('catalog/blok-protivofiltratsionnogo-ekrana', 'catalog/fbs-bloki', 'catalog/opornye-plity-op', 'catalog/otkosnye-stenki'),
        'catalog/kolodtsy-unifitsirovannye'          => array('catalog/koltsa-kolodeznye-ks', 'catalog/kryshki-kolodtsev-pp', 'catalog/opornye-plity-op', 'catalog/elementy-kollektorov'),
        'catalog/koltsa-kolodeznye-ks'               => array('catalog/kryshki-kolodtsev-pp', 'catalog/dnishcha-kolodtsev-pd', 'catalog/koltsa-s-dnom-ktsd', 'catalog/lyuki-chugunnye'),
        'catalog/koltsa-s-dnom-ktsd'                 => array('catalog/koltsa-kolodeznye-ks', 'catalog/dnishcha-kolodtsev-pd', 'catalog/kryshki-kolodtsev-pp', 'catalog/opornye-plity-op'),
        'catalog/kryshki-kolodtsev-pp'               => array('catalog/koltsa-kolodeznye-ks', 'catalog/lyuki-chugunnye', 'catalog/opornye-plity-op', 'catalog/dnishcha-kolodtsev-pd'),
        'catalog/lekalnye-bloki-bl'                  => array('catalog/vodopropusknye-truby-zk', 'catalog/zvenya-ploskoe-opiranie-zkp', 'catalog/portalnye-stenki-stk', 'catalog/otkosnye-stenki'),
        'catalog/lyuki-chugunnye'                    => array('catalog/kryshki-kolodtsev-pp', 'catalog/koltsa-kolodeznye-ks', 'catalog/kolodtsy-unifitsirovannye', 'catalog/opornye-plity-op'),
        'catalog/neprohodnye-kanaly-nkl'             => array('catalog/plity-perekrytiya-kanalov-vp', 'catalog/elementy-kollektorov', 'catalog/fbs-bloki', 'catalog/kolodtsy-unifitsirovannye'),
        'catalog/opornye-plity-op'                   => array('catalog/koltsa-kolodeznye-ks', 'catalog/dnishcha-kolodtsev-pd', 'catalog/kryshki-kolodtsev-pp', 'catalog/kolodtsy-unifitsirovannye'),
        'catalog/otkosnye-stenki'                    => array('catalog/portalnye-stenki-stk', 'catalog/vodopropusknye-truby-zk', 'catalog/zvenya-ploskoe-opiranie-zkp', 'catalog/lekalnye-bloki-bl'),
        'catalog/plity-perekrytiya-kanalov-vp'       => array('catalog/neprohodnye-kanaly-nkl', 'catalog/elementy-kollektorov', 'catalog/opornye-plity-op', 'catalog/fbs-bloki'),
        'catalog/portalnye-stenki-stk'               => array('catalog/otkosnye-stenki', 'catalog/vodopropusknye-truby-zk', 'catalog/zvenya-ploskoe-opiranie-zkp', 'catalog/lekalnye-bloki-bl'),
        'catalog/telefonnye-kolodtsy-kks'            => array('catalog/kolodtsy-unifitsirovannye', 'catalog/kryshki-kolodtsev-pp', 'catalog/lyuki-chugunnye', 'catalog/koltsa-kolodeznye-ks'),
        'catalog/vodopropusknye-truby-zk'            => array('catalog/zvenya-ploskoe-opiranie-zkp', 'catalog/lekalnye-bloki-bl', 'catalog/portalnye-stenki-stk', 'catalog/otkosnye-stenki'),
        'catalog/zvenya-ploskoe-opiranie-zkp'        => array('catalog/vodopropusknye-truby-zk', 'catalog/lekalnye-bloki-bl', 'catalog/otkosnye-stenki', 'catalog/portalnye-stenki-stk'),
    );
}

/** Подпись под карточкой — та же, что стояла в содержимом (задание: «остаётся прежней»). */
function rz_related_podpisi() {
    return array(
        'catalog/blok-protivofiltratsionnogo-ekrana' => 'Блоки противофильтрационного экрана БФ, БЭ',
        'catalog/dnishcha-kolodtsev-pd'              => 'Днища колодцев ПД по ГОСТ 8020-2016',
        'catalog/elementy-kollektorov'               => 'Элементы сборных коллекторов',
        'catalog/fbs-bloki'                          => 'Фундаментные блоки ФБС по ГОСТ 13579-2018',
        'catalog/fundamenty-pod-dorozhnye-znaki'     => 'Фундаменты под дорожные знаки Ф-1',
        'catalog/kolodtsy-unifitsirovannye'          => 'Колодцы унифицированные ВГ, ВС, ВД',
        'catalog/koltsa-kolodeznye-ks'               => 'Колодезные кольца КС по ГОСТ 8020-2016',
        'catalog/koltsa-s-dnom-ktsd'                 => 'Кольца колодезные с дном КЦД по ГОСТ 8020-2016',
        'catalog/kryshki-kolodtsev-pp'               => 'Крышки колодцев — плиты перекрытия ПП',
        'catalog/lekalnye-bloki-bl'                  => 'Лекальные блоки БЛ под звенья труб',
        'catalog/lyuki-chugunnye'                    => 'Люки чугунные ЛЧ и дождеприёмники по ГОСТ 3634',
        'catalog/neprohodnye-kanaly-nkl'             => 'Непроходные каналы НКЛ — лотки и плиты для теплотрасс',
        'catalog/opornye-plity-op'                   => 'Опорные плиты ОП по ГОСТ 8020-2016',
        'catalog/otkosnye-stenki'                    => 'Откосные стенки СТК, СТ',
        'catalog/plity-perekrytiya-kanalov-vp'       => 'Плиты перекрытия каналов и камер ВП',
        'catalog/portalnye-stenki-stk'               => 'Портальные стенки СТК для оголовков труб',
        'catalog/vodopropusknye-truby-zk'            => 'Звенья круглых водопропускных труб ЗК',
        'catalog/zvenya-ploskoe-opiranie-zkp'        => 'Звенья труб на плоском опирании ЗКП',
    );
}

/**
 * Марка для текстовой плитки: аббревиатура из заглавных букв названия раздела.
 * «Днища колодцев ПД по ГОСТ 8020-2016» -> «ПД», «Люки чугунные ЛЧ и …» -> «ЛЧ».
 * Обозначения стандартов за марку не считаем, иначе везде выйдет «ГОСТ».
 * Если аббревиатура не выделяется однозначно — не выдумываем и показываем
 * первые два слова названия: «Откосные стенки СТК, СТ» -> «Откосные стенки».
 */
function rz_related_marka($podpis) {
    $stop = array('ГОСТ', 'ТУ', 'СНИП', 'СП', 'РК', 'СТО', 'ЖБИ', 'НДС');
    $kand = array();
    if (preg_match_all('/[А-ЯЁA-Z]{2,}/u', $podpis, $m)) {
        foreach ($m[0] as $k) {
            if (!in_array($k, $stop, true)) $kand[$k] = 1;
        }
    }
    if (count($kand) === 1) {
        $k = array_keys($kand);
        return $k[0];
    }
    $slova = preg_split('/\s+/u', trim($podpis));
    return implode(' ', array_slice($slova, 0, 2));
}

/** Одна карточка. Пустая строка — если целевой страницы нет или она не опубликована. */
function rz_related_kartochka($put, $podpis) {
    $page = get_page_by_path($put, OBJECT, 'page');
    if (!$page || $page->post_status !== 'publish') return '';

    $url = get_permalink($page->ID);
    $img = get_the_post_thumbnail_url($page->ID, 'medium');

    if ($img) {
        $verh = '<img src="' . esc_url($img) . '" alt="' . esc_attr($podpis) . '"'
              . ' loading="lazy" decoding="async"'
              . ' style="width:100%;height:120px;object-fit:cover;'
              . 'border-radius:6px 6px 0 0;display:block" />';
    } else {
        /* Не серый прямоугольник, а плитка с маркой: бумажный тон темы,
           тонкая рамка, шрифт заголовков темы (--rz-display). */
        $verh = '<div style="height:120px;box-sizing:border-box;background:#F2F1EC;'
              . 'border:1px solid #DCD9D0;border-radius:6px 6px 0 0;'
              . 'display:flex;align-items:center;justify-content:center;'
              . 'font-family:var(--rz-display),\'Arial Narrow\',Arial,sans-serif;'
              . 'font-weight:600;font-size:30px;line-height:1;letter-spacing:.02em;'
              . 'color:#23272E;text-align:center;padding:0 10px">'
              . esc_html(rz_related_marka($podpis)) . '</div>';
    }

    return '<a href="' . esc_url($url) . '" style="display:block;border:1px solid #e3e7e5;'
         . 'border-radius:8px;overflow:hidden;text-decoration:none;color:#23272e;background:#fff">'
         . $verh
         . '<span style="display:block;padding:10px 12px;font-size:14px;line-height:1.35;'
         . 'font-weight:600">' . esc_html($podpis) . '</span></a>';
}

/** Блок целиком. Заголовок без инлайнового цвета #23483a — цвет задаёт тема. */
function rz_related_blok($puti) {
    $podpisi = rz_related_podpisi();
    $kart = '';
    foreach ($puti as $put) {
        if (empty($podpisi[$put])) continue;
        $kart .= rz_related_kartochka($put, $podpisi[$put]);
    }
    if ($kart === '') return '';

    return "\n" . '<!-- rz-related-mu --><div style="margin:40px 0 8px">'
         . '<h2 style="font-size:22px;margin:0 0 16px">Смотрите также</h2>'
         . '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));'
         . 'gap:14px">' . $kart . '</div></div>' . "\n";
}

/* Приоритет 20 — позже wpautop (10): дописанную разметку автоформатирование
   уже не трогает. */
add_filter('the_content', function ($content) {
    if (!is_page() || !is_main_query() || !in_the_loop()) return $content;

    $id = get_the_ID();
    if (!$id) return $content;

    $svyazi = rz_related_svyazi();
    $put    = get_page_uri($id);
    if (!isset($svyazi[$put])) return $content;

    /* Старый блок ещё в содержимом — второй не выводим. Смотрим сырое
       содержимое записи, а не отфильтрованное: маркер лежит именно там. */
    $syroe = get_post_field('post_content', $id);
    if (strpos($syroe, '<!-- rz-related -->') !== false) return $content;

    return $content . rz_related_blok($svyazi[$put]);
}, 20);
