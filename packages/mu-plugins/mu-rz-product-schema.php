<?php
/**
 * Plugin Name: РегионЖБИ — товарная микроразметка каталога
 * Description: Отдаёт schema.org Product/Offer на карточках товара и ItemList на страницах рядов марок.
 *
 * Данные берутся из мета-поля _rz_product, которое заполняет разовый скрипт
 * rz-extract-products.php (лежит в ~/regiongbi.ru/tools/). После правки цен
 * или характеристик в тексте страницы скрипт нужно прогнать заново:
 *
 *   cd ~/regiongbi.ru/public_html && wp eval-file ../tools/rz-extract-products.php
 *
 * Цена и наличие проставляются только там, где они явно указаны в тексте страницы.
 *
 * Вывод идёт фильтром rank_math/json_ld — правило 3.4 AGENTS.md: своего JSON-LD
 * рядом с графом Rank Math не ставим. Прежний вариант печатал отдельный
 * <script> на wp_head; он оставлен только как запасной путь на случай, если
 * Rank Math выключат, и сам себя глушит, когда Rank Math на месте.
 *
 * На странице ряда марок (type=list) выводятся два узла:
 *   Product  — товар самой страницы, из корня меты; offers только при цене;
 *   ItemList — соседние марки из items, как было раньше.
 */
if (!defined('ABSPATH')) exit;

const RZ_SELLER_NAME = 'ООО «СМК»';
const RZ_BRAND_NAME  = 'РегионЖБИ';

function rz_product_seller() {
    return array(
        '@type'       => 'Organization',
        'name'        => RZ_SELLER_NAME,
        'taxID'       => '7733456044',
        'telephone'   => '+7 996 097-09-80',
        'email'       => 'zakaz@regiongbi.ru',
        'address'     => array(
            '@type'           => 'PostalAddress',
            'postalCode'      => '125371',
            'addressLocality' => 'Москва',
            'streetAddress'   => 'Волоколамское шоссе, д. 116',
            'addressCountry'  => 'RU',
        ),
    );
}

/* Характеристики → PropertyValue. */
function rz_product_props($specs) {
    $out = array();
    foreach ($specs as $k => $v) {
        $out[] = array('@type' => 'PropertyValue', 'name' => $k, 'value' => $v);
    }
    return $out;
}

function rz_product_weight($kg) {
    if (!$kg) return null;
    return array('@type' => 'QuantitativeValue', 'value' => round($kg, 1), 'unitCode' => 'KGM');
}

/* Сборка одного Product. */
function rz_build_product($d, $url, $category) {
    $p = array(
        '@type' => 'Product',
        'name'  => $d['name'],
        'url'   => $url,
    );
    if (!empty($d['description'])) $p['description'] = $d['description'];
    if (!empty($d['sku'])) {
        $p['sku'] = $d['sku'];
        $p['mpn'] = $d['sku'];
    }
    if (!empty($d['image']))  $p['image']    = $d['image'];
    if (!empty($category))    $p['category'] = $category;

    $p['brand'] = array('@type' => 'Brand', 'name' => RZ_BRAND_NAME);
    $p['manufacturer'] = array('@type' => 'Organization', 'name' => RZ_SELLER_NAME);

    $props = rz_product_props(isset($d['specs']) ? $d['specs'] : array());
    if (!empty($d['gost'])) {
        foreach ($d['gost'] as $g) {
            $props[] = array('@type' => 'PropertyValue', 'name' => 'Стандарт', 'value' => 'ГОСТ ' . $g);
        }
    }
    if ($props) $p['additionalProperty'] = $props;

    $w = rz_product_weight(isset($d['weight_kg']) ? $d['weight_kg'] : null);
    if ($w) $p['weight'] = $w;

    /* Оффер только при реальной цене на странице. Иначе блок не выводим вовсе. */
    if (!empty($d['price'])) {
        $offer = array(
            '@type'                 => 'Offer',
            'price'                 => (string) $d['price'],
            'priceCurrency'         => 'RUB',
            'valueAddedTaxIncluded' => true,
            'url'                   => $url,
            'seller'                => rz_product_seller(),
            'eligibleRegion'        => array('@type' => 'Place', 'name' => 'Москва, Московская область и ЦФО'),
        );
        if (!empty($d['availability'])) $offer['availability'] = $d['availability'];
        $p['offers'] = $offer;
    }

    return $p;
}

/**
 * Узлы схемы для текущей страницы: array('Product' => ..., 'ItemList' => ...).
 * Пустой массив — если страница не товарная.
 */
function rz_product_nodes() {
    if (!is_page()) return array();

    $id  = get_queried_object_id();
    $raw = get_post_meta($id, '_rz_product', true);
    if (!$raw) return array();

    $d = json_decode($raw, true);
    if (!is_array($d) || empty($d['name'])) return array();

    $url      = get_permalink($id);
    $parent   = wp_get_post_parent_id($id);
    $category = $parent ? get_the_title($parent) : '';

    /* Товар самой страницы — и на карточке, и на странице ряда.
       На ряду его раньше не было вовсе: адрес про КС 10-9, а разметка про
       соседние марки. offers ставится только при цене в корне меты; пустую
       цену не подставляем из items. */
    $nodes = array('Product' => rz_build_product($d, $url, $category));

    if (!empty($d['type']) && $d['type'] === 'list' && !empty($d['items'])) {
        /* Страница ряда марок: плюс ItemList из соседних марок. */
        $elements = array();
        $pos = 1;
        foreach ($d['items'] as $item) {
            $sub = array(
                'type'        => 'single',
                'name'        => $item['name'],
                'sku'         => $item['name'],
                'description' => '',
                'image'       => isset($d['image']) ? $d['image'] : '',
                'specs'       => isset($item['props']) ? $item['props'] : array(),
                'weight_kg'   => isset($item['weight_kg']) ? $item['weight_kg'] : null,
                'gost'        => isset($d['gost']) ? $d['gost'] : array(),
                'price'       => null,
            );
            $elements[] = array(
                '@type'    => 'ListItem',
                'position' => $pos++,
                'item'     => rz_build_product($sub, $url, $category),
            );
        }
        $nodes['ItemList'] = array(
            '@type'           => 'ItemList',
            'name'            => $d['name'],
            'itemListOrder'   => 'https://schema.org/ItemListOrderAscending',
            'numberOfItems'   => count($elements),
            'itemListElement' => $elements,
        );
    }

    return $nodes;
}

/* Основной путь: дополняем граф Rank Math, а не ставим свой рядом.
   Приоритет 20 — раньше mu-rz-schema.php и mu-rz-schema-address.php (99):
   те ходят по узлам верхнего уровня и ищут Organization, наши узлы им не мешают. */
add_filter('rank_math/json_ld', function ($data, $jsonld) {
    if (!is_array($data)) return $data;
    foreach (rz_product_nodes() as $key => $node) {
        $data['rz' . $key] = $node;
    }
    return $data;
}, 20, 2);

/* Запасной путь. Если Rank Math на месте — молчим: два графа хуже одного. */
add_action('wp_head', function () {
    if (defined('RANK_MATH_VERSION') || defined('RANK_MATH_FILE')) return;

    $nodes = array_values(rz_product_nodes());
    if (!$nodes) return;

    $graph = (count($nodes) === 1)
        ? array_merge(array('@context' => 'https://schema.org'), $nodes[0])
        : array('@context' => 'https://schema.org', '@graph' => $nodes);

    /* Слеши не разэкранируем — так «</script>» внутри строки не сможет закрыть тег. */
    echo "\n<!-- РегионЖБИ: товарная разметка (Rank Math выключён) -->\n";
    echo '<script type="application/ld+json">' . wp_json_encode($graph, JSON_UNESCAPED_UNICODE) . "</script>\n";
}, 25);
