<?php
/**
 * Разовый скрипт: вытаскивает данные товара из контента карточек каталога
 * и складывает их в мета-поле _rz_product. Дальше их читает mu-rz-product-schema.php.
 *
 * Запуск:  wp eval-file rz-extract-products.php
 * Повторный запуск безопасен — просто перезаписывает мету.
 *
 * Страницы каталога делятся на два вида:
 *   single — одна марка, таблица «Параметр | Значение»       → schema Product
 *   list   — ряд марок, таблица «Марка | размеры | масса …» → schema ItemList из Product
 * Цену ставим только там, где она явно написана в тексте. Ничего не додумываем.
 */

$PRODUCTS = array(600, 622, 621, 618, 616, 601, 599, 640, 647, 648, 649, 650, 654);

/* Список ID можно передать при запуске, через запятую и без пробелов:
 *   wp eval-file ../tools/rz-extract-products.php 1503,1504,1505
 * Без аргумента берётся зашитый список выше — прежнее поведение не меняется.
 * Понадобилось в задании 020: у новых карточек ВП мета _rz_product не появится,
 * пока их ID не окажется в списке, а список зашит в файл. */
if (isset($args) && is_array($args)) {
    foreach ($args as $rz_a) {
        if (preg_match('/^\d+(,\d+)*$/', $rz_a)) {
            $PRODUCTS = array_map('intval', explode(',', $rz_a));
            break;
        }
    }
}

/* Марка кириллицей → латинская основа имени файла: «ФБС 24-4-6» → «fbs-24-4-6». */
function rz_translit($s) {
    $map = array(
        'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh','з'=>'z',
        'и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r',
        'с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'c','ч'=>'ch','ш'=>'sh','щ'=>'sch',
        'ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
    );
    $s = mb_strtolower(trim($s));
    $s = strtr($s, $map);
    $s = preg_replace('/[\s_\.]+/u', '-', $s);
    return trim(preg_replace('/-+/', '-', $s), '-');
}

/**
 * Ищем фотографию именно этого изделия — по марке в имени файла.
 * Чужое фото из категории не подставляем: в schema.org image обязано изображать
 * тот товар, который размечен, иначе разметка врёт. Нет совпадения — нет картинки.
 */
function rz_product_image($sku) {
    if ($sku === '') return '';
    $needle = rz_translit($sku);
    if (strlen($needle) < 4) return '';

    $atts = get_posts(array(
        'post_type'      => 'attachment',
        'post_mime_type' => 'image',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ));
    foreach ($atts as $aid) {
        $file = get_attached_file($aid);
        if (!$file) continue;
        $base = strtolower(pathinfo($file, PATHINFO_FILENAME));
        if (strpos($base, $needle) !== false) {
            return wp_get_attachment_url($aid);
        }
    }
    return '';
}

/* Число в килограммах из пары «ключ => значение». Единица бывает и в ключе («Масса, т»). */
function rz_kg($key, $val) {
    $val = trim($val);
    if (preg_match('/([\d]+(?:[.,][\d]+)?)\s*кг/u', $val, $m)) return (float) str_replace(',', '.', $m[1]);
    if (preg_match('/([\d]+(?:[.,][\d]+)?)\s*т/u', $val, $m))  return (float) str_replace(',', '.', $m[1]) * 1000;
    if (preg_match('/^[~≈]?\s*([\d]+(?:[.,][\d]+)?)$/u', $val, $m)) {
        $n = (float) str_replace(',', '.', $m[1]);
        if (preg_match('/,\s*кг/u', $key)) return $n;
        if (preg_match('/,\s*т/u', $key))  return $n * 1000;
    }
    return null;
}

function rz_weight_from_specs($specs) {
    foreach ($specs as $k => $v) {
        if (mb_stripos($k, 'масса') === false && mb_stripos($k, 'вес') === false) continue;
        $kg = rz_kg($k, $v);
        if ($kg) return $kg;
    }
    return null;
}

/* Таблица «Параметр | Значение» — характеристики одного изделия. */
function rz_single_specs($content) {
    if (!preg_match_all('/<table>(.*?)<\/table>/su', $content, $tables)) return array();
    foreach ($tables[1] as $t) {
        if (mb_strpos($t, 'Параметр') === false) continue;
        $specs = array();
        if (preg_match_all('/<tr>(.*?)<\/tr>/su', $t, $rows)) {
            foreach ($rows[1] as $row) {
                if (!preg_match_all('/<td>(.*?)<\/td>/su', $row, $cells)) continue;
                if (count($cells[1]) < 2) continue;
                $k = trim(wp_strip_all_tags($cells[1][0]));
                $v = trim(wp_strip_all_tags($cells[1][1]));
                if ($k === '' || $v === '') continue;
                $specs[$k] = $v;
            }
        }
        if ($specs) return $specs;
    }
    return array();
}

/* Таблица «Марка | … » — ряд марок. Возвращает список изделий. */
function rz_list_items($content) {
    if (!preg_match_all('/<table>(.*?)<\/table>/su', $content, $tables)) return array();
    foreach ($tables[1] as $t) {
        if (mb_strpos($t, 'Марка') === false && mb_strpos($t, 'Тип') === false) continue;
        if (!preg_match('/<thead>(.*?)<\/thead>/su', $t, $th)) continue;
        preg_match_all('/<th>(.*?)<\/th>/su', $th[1], $heads);
        $cols = array_map(function ($x) { return trim(wp_strip_all_tags($x)); }, $heads[1]);
        if (count($cols) < 2) continue;

        $items = array();
        if (!preg_match_all('/<tr>(.*?)<\/tr>/su', $t, $rows)) continue;
        foreach ($rows[1] as $row) {
            if (!preg_match_all('/<td>(.*?)<\/td>/su', $row, $cells)) continue;
            $name = trim(wp_strip_all_tags($cells[1][0]));
            if ($name === '') continue;
            $props = array();
            $kg = null;
            foreach ($cells[1] as $i => $cell) {
                if ($i === 0 || !isset($cols[$i])) continue;
                if (mb_stripos($cols[$i], 'источник') !== false) continue;
                $v = trim(wp_strip_all_tags($cell));
                if ($v === '') continue;
                $props[$cols[$i]] = $v;
                if ($kg === null && (mb_stripos($cols[$i], 'масса') !== false || mb_stripos($cols[$i], 'вес') !== false)) {
                    $kg = rz_kg($cols[$i], $v);
                }
            }
            $items[] = array('name' => $name, 'props' => $props, 'weight_kg' => $kg);
        }
        if (count($items) >= 2) return $items;
    }
    return array();
}

$report = array();

foreach ($PRODUCTS as $id) {
    $p = get_post($id);
    if (!$p) continue;
    $c = $p->post_content;

    $h1 = '';
    if (preg_match('/<h1>(.*?)<\/h1>/su', $c, $m)) $h1 = trim(wp_strip_all_tags($m[1]));
    $name = trim($h1 !== '' ? preg_split('/[:—]/u', $h1)[0] : $p->post_title);

    /* Марка изделия. Скобки вырезаем: «Ходовые скобы (ГС, СК, МН-1)» — это группа, не марка. */
    $sku = '';
    $for_sku = trim(preg_replace('/\(.*?\)/u', '', $name));
    if (preg_match('/([А-ЯЁ]{2,4}[рp]?[\s\-]?[\d]+(?:[\-\.][\d]+){0,2}[А-ЯЁ]?)/u', $for_sku, $m)) $sku = trim($m[1]);

    $specs = rz_single_specs($c);
    $items = $specs ? array() : rz_list_items($c);
    $type  = $items ? 'list' : 'single';

    /* Цена — только из явной конструкции «Цена … N ₽». */
    $price = null;
    if (preg_match('/Цена[^.]{0,140}?([\d][\d\s ]{2,8})\s*₽/u', $c, $m)) {
        $price = (int) preg_replace('/\D/', '', $m[1]);
    }

    /* Наличие заявляем, только если текст прямо говорит про отгрузку со склада. */
    $availability = ($price && preg_match('/держим под отгрузку|есть в наличии|со склада/u', $c))
        ? 'https://schema.org/InStock' : null;

    $gost = array();
    if (preg_match_all('/ГОСТ\s?([\d][\d\.\-]*)/u', $c, $gm)) $gost = array_values(array_unique($gm[1]));

    $desc = get_post_meta($id, 'rank_math_description', true);
    if (!$desc) {
        $plain = trim(wp_strip_all_tags(preg_replace('/<h1>.*?<\/h1>/su', '', $c)));
        $desc = mb_substr($plain, 0, 300);
    }

    $data = array(
        'type'         => $type,
        'name'         => $name,
        'sku'          => $sku,
        'description'  => $desc,
        'image'        => rz_product_image($sku),
        'price'        => $price,
        'availability' => $availability,
        'gost'         => array_slice($gost, 0, 3),
        'specs'        => array_slice($specs, 0, 12, true),
        'weight_kg'    => rz_weight_from_specs($specs),
        'items'        => array_slice($items, 0, 20),
    );

    update_post_meta($id, '_rz_product', wp_json_encode($data, JSON_UNESCAPED_UNICODE));

    $report[] = sprintf(
        "%-4s %-6s %-12s цена:%-8s масса:%-9s %s",
        $id,
        $type,
        $sku ?: '—',
        $price ? $price . '₽' : '—',
        $data['weight_kg'] ? round($data['weight_kg']) . 'кг' : '—',
        $type === 'list'
            ? 'марок в ряду: ' . count($items)
            : 'характеристик: ' . count($specs) . ($data['image'] ? ', фото есть' : ', ФОТО НЕТ')
    );
}

echo implode("\n", $report) . "\n\nОбработано: " . count($report) . "\n";
