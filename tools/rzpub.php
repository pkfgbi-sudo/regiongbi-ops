<?php
/**
 * rzpub.php — идемпотентный публикатор пакетов regiongbi.ru
 *
 * Запуск из ~/regiongbi.ru/public_html:
 *   wp eval-file ../tools/rzpub.php ../packages/ks-range-02.json
 *   wp eval-file ../tools/rzpub.php ../packages/ks-range-02.json dry
 *
 * Формат пакета: {"package": "...", "roditel_slug": "...", "items": [
 *   {"url": "/catalog/.../ks-15-9/", "parent": "/catalog/.../", "slug": "ks-15-9",
 *    "post_title": "...", "title": "...", "rank_math_title": "...",
 *    "rank_math_description": "...", "content": "<p>...</p>",
 *    "meta": {"price": 4160, ...}}
 * ]}
 *
 * Страница ищется по пути из url. Найдена — обновляется, не найдена — создаётся.
 * Повторный прогон не плодит дубли. Slug не меняется никогда.
 *
 * Задание 020 (создание карточек ВП) добавило три поля, которых пакет 06.09.2026
 * не досчитался:
 *   title        — то же, что post_title. Читаются оба, post_title главнее.
 *   slug         — имя страницы явно. Раньше бралось только из хвоста url;
 *                  теперь, если поле есть и с хвостом url расходится, работа
 *                  по этой позиции прекращается: расхождение означает, что
 *                  страница ляжет не по тому адресу, который проверяли.
 *   roditel_slug — родитель для всех позиций пакета, по слагу, а не по url.
 *                  Позиционный "parent" по-прежнему главнее.
 */

if (!defined('WP_CLI') || !WP_CLI) {
    fwrite(STDERR, "Запускать только через wp eval-file\n");
    exit(1);
}

$argv_all = $args;
$dry = in_array('dry', $argv_all, true) || getenv('RZ_DRY');
$file = null;
foreach ($argv_all as $a) {
    if (substr($a, 0, 2) !== '--') { $file = $a; break; }
}

if (!$file || !file_exists($file)) {
    WP_CLI::error("Не найден файл пакета. Пример: wp eval-file ../tools/rzpub.php ../packages/ks-range-02.json");
}

$pkg = json_decode(file_get_contents($file), true);
if (!$pkg || empty($pkg['items'])) {
    WP_CLI::error("Пакет не разобран или пуст: $file");
}

$name  = isset($pkg['package']) ? $pkg['package'] : basename($file);
$items = $pkg['items'];
$roditel_slug = isset($pkg['roditel_slug']) ? trim($pkg['roditel_slug'], '/') : '';

WP_CLI::log("Пакет: $name");
WP_CLI::log("Позиций: " . count($items) . ($dry ? "  [ПРОБНЫЙ ПРОГОН, ничего не пишем]" : ""));
WP_CLI::log(str_repeat('-', 72));

/**
 * Марка кириллицей -> латинская основа имени файла: «ФБС 24-4-6» -> «fbs-24-4-6».
 * Скопировано из tools/rz-extract-products.php, чтобы логика поиска фото была одна.
 */
function rz_pub_translit($s) {
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
 * Фото именно этого изделия — по марке в имени файла. Чужое фото из категории
 * не подставляем: в schema.org image обязано изображать размеченный товар.
 * Список вложений читается один раз на весь прогон.
 */
function rz_pub_image($sku) {
    static $files = null;
    if ($sku === '') return '';
    $needle = rz_pub_translit($sku);
    if (strlen($needle) < 4) return '';
    if ($files === null) {
        $files = array();
        $atts = get_posts(array(
            'post_type' => 'attachment', 'post_mime_type' => 'image',
            'posts_per_page' => -1, 'fields' => 'ids',
        ));
        foreach ($atts as $aid) {
            $f = get_attached_file($aid);
            if ($f) $files[strtolower(pathinfo($f, PATHINFO_FILENAME))] = wp_get_attachment_url($aid);
        }
    }
    foreach ($files as $base => $url) {
        if (strpos($base, $needle) !== false) return $url;
    }
    return '';
}

/** Слаг страницы -> объект страницы или null. Слаг не привязан к месту в дереве. */
function rz_page_by_slug($slug) {
    $p = get_posts(array(
        'name' => $slug, 'post_type' => 'page',
        'post_status' => array('publish', 'draft', 'private'),
        'posts_per_page' => 1,
    ));
    return $p ? $p[0] : null;
}

/** Путь вида /catalog/a/b/ -> объект страницы или null */
function rz_page_by_url($url) {
    $path = trim(parse_url($url, PHP_URL_PATH), '/');
    if ($path === '') {
        $front = (int) get_option('page_on_front');
        return $front ? get_post($front) : null;
    }
    $p = get_page_by_path($path, OBJECT, 'page');
    return $p ?: null;
}

$created = $updated = $skipped = $failed = 0;

foreach ($items as $it) {
    $url = isset($it['url']) ? $it['url'] : null;
    if (!$url) { $failed++; WP_CLI::warning("Позиция без url — пропущена"); continue; }

    $path  = trim(parse_url($url, PHP_URL_PATH), '/');
    $slug  = $path === '' ? '' : basename($path);
    if (!empty($it['slug']) && $it['slug'] !== $slug) {
        WP_CLI::warning("[$url] slug в пакете «{$it['slug']}» не совпадает с хвостом url «$slug» — позиция пропущена");
        $failed++; continue;
    }
    $page  = rz_page_by_url($url);

    // родитель: позиционный parent по url, иначе общий roditel_slug пакета
    $parent_id = 0;
    if (!empty($it['parent'])) {
        $pp = rz_page_by_url($it['parent']);
        if ($pp) {
            $parent_id = $pp->ID;
        } else {
            WP_CLI::warning("[$url] родитель не найден: {$it['parent']}");
        }
    } elseif ($roditel_slug !== '') {
        $pp = rz_page_by_slug($roditel_slug);
        if ($pp) {
            $parent_id = $pp->ID;
        } else {
            WP_CLI::warning("[$url] родитель пакета не найден по слагу: $roditel_slug");
        }
    }

    $data = array(
        'post_type'   => 'page',
        'post_status' => 'publish',
    );
    if (!empty($it['post_title']))  { $data['post_title'] = $it['post_title']; }
    elseif (!empty($it['title']))   { $data['post_title'] = $it['title']; }
    if (isset($it['content']))     { $data['post_content'] = $it['content']; }

    // Блок, который вклеивается в существующий контент, а не заменяет его целиком.
    // Нужен для страниц, где есть ценные куски (расшифровка марки, вопросы),
    // которых у нас в пакете нет. Повторный прогон заменяет блок, а не плодит его.
    if (!empty($it['block']['id']) && isset($it['block']['html'])) {
        $bid  = $it['block']['id'];
        $q    = preg_quote($bid, '#');
        $base = $page ? $page->post_content : (isset($it['content']) ? $it['content'] : '');
        $base = preg_replace("#\s*<!--rz:$q-->.*?<!--/rz:$q-->\s*#su", "\n\n", $base);
        $data['post_content'] = rtrim($base) . "\n\n<!--rz:$bid-->\n"
            . $it['block']['html'] . "\n<!--/rz:$bid-->";
    }
    if ($parent_id)                { $data['post_parent'] = $parent_id; }

    if ($page) {
        $data['ID'] = $page->ID;
        // главную страницу родителем не трогаем
        if ($path === '') { unset($data['post_parent']); }
        if ($dry) {
            WP_CLI::log(sprintf("ОБНОВИТЬ  %-52s #%d", $url, $page->ID));
            $updated++;
        } else {
            $res = wp_update_post($data, true);
            if (is_wp_error($res)) {
                WP_CLI::warning("[$url] ошибка обновления: " . $res->get_error_message());
                $failed++; continue;
            }
            $id = $page->ID;
            WP_CLI::log(sprintf("обновлено %-52s #%d", $url, $id));
            $updated++;
        }
        $id = $page->ID;
    } else {
        if ($path === '') { WP_CLI::warning("Главная не найдена — пропуск"); $failed++; continue; }
        $data['post_name'] = $slug;
        if ($dry) {
            WP_CLI::log(sprintf("СОЗДАТЬ   %-52s (slug %s)", $url, $slug));
            $created++;
            continue;
        }
        $id = wp_insert_post($data, true);
        if (is_wp_error($id)) {
            WP_CLI::warning("[$url] ошибка создания: " . $id->get_error_message());
            $failed++; continue;
        }
        WP_CLI::log(sprintf("создано   %-52s #%d", $url, $id));
        $created++;
    }

    if ($dry) { continue; }

    // SEO-мета Rank Math
    if (!empty($it['rank_math_title'])) {
        update_post_meta($id, 'rank_math_title', $it['rank_math_title']);
    }
    if (!empty($it['rank_math_description'])) {
        update_post_meta($id, 'rank_math_description', $it['rank_math_description']);
    }

    // Данные товарной микроразметки.
    // ВАЖНО: mu-rz-product-schema.php делает json_decode($raw, true) — он ждёт
    // СТРОКУ JSON. Массив PHP роняет json_decode фатальной ошибкой и уносит
    // с собой всю страницу (наступали на это 30.08.2026, 113 страниц в 500).
    // wp_slash — потому что update_post_meta снимает слеши, а они нужны JSON.
    if (!empty($it['meta']) && is_array($it['meta']) && !empty($it['meta']['name'])) {
        $meta = $it['meta'];
        if (empty($meta['image'])) {
            // своя фотография, если она нашлась в медиатеке по марке
            $meta['image'] = rz_pub_image(isset($meta['sku']) ? $meta['sku'] : '');
        }
        $json = wp_json_encode($meta, JSON_UNESCAPED_UNICODE);
        update_post_meta($id, '_rz_product', wp_slash($json));
    }
    // мусор промежуточной ревизии того же дня
    delete_post_meta($id, '_rz_product_src');

    // отметка, каким пакетом страница заведена — для последующих ревизий
    update_post_meta($id, '_rz_package', $name);
}

WP_CLI::log(str_repeat('-', 72));
WP_CLI::log(sprintf("Создано: %d   Обновлено: %d   Ошибок: %d", $created, $updated, $failed));

if (!$dry) {
    // сброс страничного кэша
    if (function_exists('cache_enabler_clear_complete_cache')) {
        cache_enabler_clear_complete_cache();
        WP_CLI::log("Кэш Cache Enabler сброшен");
    }
    WP_CLI::log("Дальше: wp eval-file ../tools/rz-extract-products.php");
}

WP_CLI::success("Готово: $name");
