<?php
/**
 * rz-blog.php — идемпотентный публикатор пакетов для ЗАПИСЕЙ блога regiongbi.ru
 *
 * Зачем отдельный файл. rzpub.php работает только с post_type=page: он ищет
 * страницу через get_page_by_path(..., 'page'). Записи блога (post_type=post)
 * он не видит и никогда не видел — из-за этого пакет по «манипулятору»
 * 31.08.2026 прошёл по 129 страницам и не тронул ни одной записи.
 *
 * Запуск из ~/regiongbi.ru/public_html:
 *   wp eval-file ../tools/rz-blog.php ../packages/blog-01.json dry
 *   wp eval-file ../tools/rz-blog.php ../packages/blog-01.json
 *
 * Формат пакета:
 * {"package":"blog-01","items":[
 *   {"slug":"...", "content":"<p>…</p>", "post_title":"…",
 *    "rank_math_title":"…", "rank_math_description":"…"},
 *   {"slug":"...", "replace":[["было","стало"], ...]}
 * ]}
 *
 * content   — полная замена текста записи.
 * replace   — точечные замены. Идемпотентно: если «было» уже не встречается,
 *             позиция считается выполненной ранее и пропускается без ошибки.
 * block     — {"id":"cards","html":"…"}: врезка в конец текста между маркерами
 *             <!--rz:cards--> … <!--/rz:cards-->. Повторный прогон ЗАМЕНЯЕТ
 *             блок, а не добавляет второй. Остальной текст записи не трогается.
 * Порядок применения: content → block → replace.
 * Slug не меняется никогда, новые записи не создаются — только правка своих.
 */

if (!defined('WP_CLI') || !WP_CLI) {
    fwrite(STDERR, "Запускать только через wp eval-file\n");
    exit(1);
}

$argv_all = $args;
$dry  = in_array('dry', $argv_all, true) || getenv('RZ_DRY');
$file = null;
foreach ($argv_all as $a) {
    if (substr($a, 0, 2) !== '--' && $a !== 'dry') { $file = $a; break; }
}
if (!$file || !file_exists($file)) {
    WP_CLI::error("Не найден файл пакета. Пример: wp eval-file ../tools/rz-blog.php ../packages/blog-01.json");
}

$pkg = json_decode(file_get_contents($file), true);
if (!$pkg || empty($pkg['items'])) {
    WP_CLI::error("Пакет не разобран или пуст: $file");
}

$name = isset($pkg['package']) ? $pkg['package'] : basename($file);
WP_CLI::log("Пакет: $name");
WP_CLI::log("Позиций: " . count($pkg['items']) . ($dry ? "  [ПРОБНЫЙ ПРОГОН, ничего не пишем]" : ""));
WP_CLI::log(str_repeat('-', 72));

/** Запись блога по slug или null */
function rz_blog_post($slug) {
    $p = get_posts(array(
        'name' => $slug, 'post_type' => 'post',
        'post_status' => array('publish', 'draft', 'private'),
        'numberposts' => 1,
    ));
    return $p ? $p[0] : null;
}

$changed = $same = $failed = 0;

foreach ($pkg['items'] as $it) {
    $slug = isset($it['slug']) ? $it['slug'] : '';
    if ($slug === '') { $failed++; WP_CLI::warning("Позиция без slug — пропущена"); continue; }

    $post = rz_blog_post($slug);
    if (!$post) { $failed++; WP_CLI::warning("[$slug] запись не найдена"); continue; }

    $before  = $post->post_content;
    $content = $before;
    $notes   = array();

    if (isset($it['content']) && $it['content'] !== '') {
        $content = $it['content'];
        $notes[] = 'текст';
    }

    if (!empty($it['block']['id']) && isset($it['block']['html'])) {
        $bid = $it['block']['id'];
        $q   = preg_quote($bid, '#');
        $content = preg_replace("#\s*<!--rz:$q-->.*?<!--/rz:$q-->\s*#su", "\n\n", $content);
        $content = rtrim($content) . "\n\n<!--rz:$bid-->\n"
                 . $it['block']['html'] . "\n<!--/rz:$bid-->";
        $notes[] = "блок $bid";
    }

    if (!empty($it['replace']) && is_array($it['replace'])) {
        $done = 0; $skip = 0;
        foreach ($it['replace'] as $pair) {
            if (!is_array($pair) || count($pair) < 2) continue;
            list($from, $to) = $pair;
            if ($from === '' || strpos($content, $from) === false) { $skip++; continue; }
            $content = str_replace($from, $to, $content);
            $done++;
        }
        if ($done) $notes[] = "замен: $done";
        if ($skip) $notes[] = "уже было: $skip";
    }

    $meta_notes = array();
    if (!empty($it['rank_math_title']))       $meta_notes[] = 'title';
    if (!empty($it['rank_math_description'])) $meta_notes[] = 'description';

    $need_content = ($content !== $before);
    $need_title   = (!empty($it['post_title']) && $it['post_title'] !== $post->post_title);

    if (!$need_content && !$need_title && !$meta_notes) {
        $same++;
        WP_CLI::log(sprintf("без изменений  %-46s #%d", $slug, $post->ID));
        continue;
    }

    if ($dry) {
        WP_CLI::log(sprintf("ИЗМЕНИТЬ       %-46s #%d  %s", $slug, $post->ID,
            implode(', ', array_merge($notes, $meta_notes))));
        $changed++;
        continue;
    }

    if ($need_content || $need_title) {
        $data = array('ID' => $post->ID);
        if ($need_content) $data['post_content'] = $content;
        if ($need_title)   $data['post_title']   = $it['post_title'];
        $res = wp_update_post(wp_slash($data), true);
        if (is_wp_error($res)) {
            WP_CLI::warning("[$slug] ошибка обновления: " . $res->get_error_message());
            $failed++; continue;
        }
    }
    if (!empty($it['rank_math_title'])) {
        update_post_meta($post->ID, 'rank_math_title', $it['rank_math_title']);
    }
    if (!empty($it['rank_math_description'])) {
        update_post_meta($post->ID, 'rank_math_description', $it['rank_math_description']);
    }
    update_post_meta($post->ID, '_rz_package', $name);

    WP_CLI::log(sprintf("изменено       %-46s #%d  %s", $slug, $post->ID,
        implode(', ', array_merge($notes, $meta_notes))));
    $changed++;
}

WP_CLI::log(str_repeat('-', 72));
WP_CLI::log(sprintf("Изменено: %d   Без изменений: %d   Ошибок: %d", $changed, $same, $failed));

/* Контроль: запрещённые обещания по всем записям блога */
$bad = array('манипул', 'разгружаем', 'круглосуточ');
$hits = 0;
foreach (get_posts(array('post_type' => 'post', 'post_status' => 'publish',
                         'posts_per_page' => -1, 'fields' => 'ids')) as $pid) {
    $c = mb_strtolower(get_post($pid)->post_content);
    foreach ($bad as $b) {
        $n = mb_substr_count($c, $b);
        if ($n) { $hits += $n; WP_CLI::log("  осталось «$b» ×$n — " . get_post_field('post_name', $pid)); }
    }
}
WP_CLI::log("Запрещённых формулировок в записях блога: $hits");

if (!$dry) {
    if (function_exists('cache_enabler_clear_complete_cache')) {
        cache_enabler_clear_complete_cache();
        WP_CLI::log("Кэш Cache Enabler сброшен");
    }
}
WP_CLI::success("Готово: $name");
