<?php
/**
 * Plugin Name: РегионЖБИ — цели Метрики и метка источника в письме заявки
 * Description: Отправляет в Метрику четыре цели (zayavka, tel, mail, price) и дописывает к письму о заявке страницу, источник и московское время.
 *
 * Задание 008. Две задачи.
 *
 * Первая. В счётчике нет целей: обращения не считаются, и работу сайта
 * оценивать можно только по показам. Скрипт целей висит на wp_footer, а не на
 * wp_head: в wp_head вывод буферизует mu-rz-og.php, и второй вывод в том же
 * хуке ломает страницу. В футере скрипт заведомо ниже кода счётчика, который
 * печатает mu-regionzhbi-setup.php в wp_head.
 *
 * Вторая. Письмо о заявке приходит без источника: через месяц уже не
 * восстановить, органика это или прямой заход. Блок дописывается фильтром
 * wpcf7_mail_components — шаблон письма в админке не трогаем: правка в
 * админке теряется при первой же переделке формы, фильтр переживает всё.
 */
if (!defined('ABSPATH')) exit;

/*
 * Номер счётчика — одной строкой здесь и больше нигде в файле.
 *
 * У regiongbi.ru (ООО «СМК») счётчик свой: 112302967 (сменён 05.09.2026,
 * задание 012; прежний счётчик создан на неизвестном аккаунте и
 * неуправляем). Счётчик сайта
 * ООО ПО «ЖБИ-ТОРГ» (94045872) сюда не подставляется никогда: общий счётчик
 * склеивает два сайта в аффилиат-группу, и в выдаче остаётся один.
 *
 * Если номер уже объявлен в mu-regionzhbi-setup.php — берём оттуда, чтобы
 * счётчик и цели не разъехались при смене номера.
 */
define('RZ_CELI_METRIKA_ID', defined('REGIONZHBI_METRIKA_ID') ? (int) REGIONZHBI_METRIKA_ID : 112302967);

/* Cookie с utm-метками: кладёт скрипт при заходе с меткой, читает фильтр письма. */
const RZ_CELI_UTM_COOKIE = 'rz_utm';
const RZ_CELI_UTM_DAYS   = 30;

/* ------------------------------------------------------------------ 1. цели */

/**
 * Четыре цели, идентификаторы совпадают с теми, что заводятся в кабинете
 * Метрики как «JavaScript-событие»:
 *
 *   zayavka — успешная отправка формы Contact Form 7 (событие wpcf7mailsent,
 *             оно приходит только когда письмо реально ушло, в отличие от submit);
 *   tel     — клик по ссылке tel:;
 *   mail    — клик по ссылке mailto:;
 *   price   — клик по ссылке на файл прайса.
 *
 * Клики ловятся делегированием на document: часть ссылок появляется
 * динамически, навешивать обработчик на каждую ссылку бесполезно.
 */
add_action('wp_footer', function () {
    $id = (int) RZ_CELI_METRIKA_ID;
    if (!$id) return;
    $cookie = RZ_CELI_UTM_COOKIE;
    $maxage = RZ_CELI_UTM_DAYS * 86400;
    ?>
<!-- РегионЖБИ: цели Метрики -->
<script type="text/javascript">
(function(){
  var RZ_ID = <?php echo $id; ?>;

  /* Счётчик мог не догрузиться — тогда просто ничего не отправляем,
     страница от этого падать в консоль не должна. */
  function rzGoal(name){
    if (typeof ym === 'function') { ym(RZ_ID, 'reachGoal', name); }
  }

  /* utm-метки первого захода кладём в cookie на <?php echo (int) RZ_CELI_UTM_DAYS; ?> дней:
     форма уходит ajax'ом, и на сервере метки иначе уже не увидеть. */
  try {
    var q = window.location.search || '', keys = ['utm_source','utm_medium','utm_campaign','utm_term','utm_content'], got = [];
    if (q.length > 1) {
      var p = new URLSearchParams(q);
      for (var i = 0; i < keys.length; i++) {
        var v = p.get(keys[i]);
        if (v) { got.push(keys[i] + '=' + v); }
      }
    }
    if (got.length) {
      document.cookie = '<?php echo $cookie; ?>=' + encodeURIComponent(got.join('&').slice(0, 300)) +
        '; path=/; max-age=<?php echo (int) $maxage; ?>; samesite=lax';
    }
  } catch (e) {}

  /* Заявка: событие приходит на document только при успешной отправке письма. */
  document.addEventListener('wpcf7mailsent', function(){ rzGoal('zayavka'); }, false);

  /* Ссылка на файл прайса: либо класс pr-dl, либо «прайс» в адресе и расширение файла. */
  var RZ_PRICE = /(prajs|price|прайс)[^\/]*\.(pdf|xlsx?|docx?|csv|zip)(\?|#|$)/i;

  document.addEventListener('click', function(e){
    var t = e.target, a = (t && t.closest) ? t.closest('a') : null;
    if (!a) return;
    var href = a.getAttribute('href') || '';
    if (href.indexOf('tel:') === 0)         { rzGoal('tel');  return; }
    if (href.indexOf('mailto:') === 0)      { rzGoal('mail'); return; }
    var cls = (typeof a.className === 'string') ? a.className : '';
    if (cls.indexOf('pr-dl') > -1 || RZ_PRICE.test(a.href || href)) { rzGoal('price'); }
  }, false);
})();
</script>
<!-- /РегионЖБИ: цели Метрики -->
<?php
}, 20);

/* --------------------------------------------------- 2. метка источника в письме */

/** Строка из запроса, обрезанная и очищенная от переводов строк. */
function rz_celi_clean($value, $limit = 300) {
    $value = is_string($value) ? wp_unslash($value) : '';
    $value = str_replace(array("\r", "\n"), ' ', $value);
    $value = sanitize_text_field($value);
    if (function_exists('mb_substr')) return mb_substr($value, 0, $limit);
    return substr($value, 0, $limit);
}

/** Страница, с которой ушла форма. Форма отправляется ajax'ом, реферер — она и есть. */
function rz_celi_page() {
    $ref = isset($_SERVER['HTTP_REFERER']) ? rz_celi_clean($_SERVER['HTTP_REFERER'], 500) : '';
    return $ref !== '' ? $ref : 'не определена — браузер не передал реферер';
}

/** Источник: utm-метки из cookie, которую положил скрипт целей. */
function rz_celi_source() {
    $raw = isset($_COOKIE[RZ_CELI_UTM_COOKIE]) ? rz_celi_clean(rawurldecode($_COOKIE[RZ_CELI_UTM_COOKIE])) : '';
    return $raw !== '' ? $raw : 'прямой заход или органика (utm-меток не было)';
}

/** Московское время, независимо от того, что стоит в настройках сайта. */
function rz_celi_when() {
    return wp_date('d.m.Y H:i', null, new DateTimeZone('Europe/Moscow')) . ' МСК';
}

add_filter('wpcf7_mail_components', function ($components, $contact_form = null, $mail = null) {
    if (!is_array($components) || !isset($components['body'])) return $components;

    /* Только основное письмо администратору. Автоответ клиенту (mail_2)
       служебными строками не засоряем. */
    if (is_object($mail) && method_exists($mail, 'name') && $mail->name() !== 'mail') {
        return $components;
    }

    $lines = array(
        'Страница: ' . rz_celi_page(),
        'Источник: ' . rz_celi_source(),
        'Дата и время: ' . rz_celi_when(),
    );

    $html = isset($components['content_type']) && stripos($components['content_type'], 'html') !== false;
    if ($html) {
        $components['body'] .= "<br><br>—<br>" . implode("<br>", array_map('esc_html', $lines));
    } else {
        $components['body'] .= "\n\n—\n" . implode("\n", $lines);
    }

    return $components;
}, 10, 3);
