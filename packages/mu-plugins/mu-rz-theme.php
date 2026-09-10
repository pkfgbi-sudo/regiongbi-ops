<?php
/**
 * Plugin Name: РегионЖБИ — тема «инженерный каталог»
 * Description: Палитра, типографика и стили таблиц, карточек и кнопок.
 *
 * ЗАЧЕМ. Палитра Blocksy на сайте осталась стандартной синей
 * (--theme-palette-color-1:#2872fa): шапка, меню, кнопки, формы и пагинация
 * синие, а ссылки внутри текста зелёные из mu-regionzhbi-setup.php. Получается
 * два разных сайта в одном окне — отсюда и ощущение шаблона.
 *
 * ЧТО ДЕЛАЕМ. Переопределяем переменные темы, а не воюем с её селекторами:
 * Blocksy сам перекрашивает всю обвязку. Дальше — типографика и таблица.
 * Таблица здесь главный рабочий инструмент: снабженец сравнивает марку, массу
 * и цену. Поэтому цифры моноширинные и с табличными знаками — колонки
 * выстраиваются столбиком, а не пляшут.
 *
 * ЧЕГО НЕ ДЕЛАЕМ. Не трогаем тему и плагины, не грузим внешних скриптов,
 * не добавляем ни одной картинки. Только CSS, инлайном, без лишнего запроса.
 *
 * ПОРЯДОК ЗАГРУЗКИ. mu-плагины подключаются по алфавиту, поэтому
 * mu-regionzhbi-setup.php идёт раньше mu-rz-theme.php. Наши правила печатаются
 * в wp_head с приоритетом 999 — позже и темы, и брендовых стилей, — и при
 * равной специфичности выигрывают. Ничего не удаляем: старые правила остаются
 * и работают там, где мы их не перекрыли.
 */
if (!defined('ABSPATH')) exit;

/**
 * Веб-шрифты. true — грузим Oswald, Golos Text и IBM Plex Mono с Google Fonts.
 * false — обходимся системными: сайт остаётся читаемым и целым, теряется только
 * характер начертаний.
 *
 * Со временем шрифты стоит положить к себе: это снимает зависимость от чужого
 * домена и убирает два обращения наружу на первой загрузке. Файлы кладутся
 * в wp-content/mu-plugins/rz-fonts/, и здесь остаётся заменить одну функцию.
 */
const RZ_THEME_FONTS = true;

/* ------------------------------------------------------------------ шрифты */

add_action('wp_head', function () {
    if (!RZ_THEME_FONTS) return;
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?'
       . 'family=Oswald:wght@500;600'
       . '&family=Golos+Text:wght@400;500;600'
       . '&family=IBM+Plex+Mono:wght@400;500'
       . '&display=swap">' . "\n";
}, 2);

/* -------------------------------------------------- таблицы: обёртка прокрутки
 *
 * Таблицы марок в контенте вставлены голым <table>, без блока Gutenberg. На
 * телефоне ряд из четырёх колонок в 375 точек не помещается: браузер сжимает
 * колонки, «Ø1000 × 890» переносится в три строки и таблица перестаёт читаться.
 *
 * Оборачиваем каждую такую таблицу в прокручиваемый контейнер: колонки
 * сохраняют ширину, а палец двигает таблицу вбок. Правим только вывод, в базе
 * содержимое страницы остаётся прежним — пакеты публикации это не ломает.
 */
add_filter('the_content', function ($html) {
    if (is_admin() || is_feed() || strpos($html, '<table') === false) return $html;

    // Таблицы-блоки Gutenberg уже завёрнуты темой — прячем их от замены.
    $saved = array();
    $html = preg_replace_callback('#<figure[^>]*wp-block-table.*?</figure>#si',
        function ($m) use (&$saved) {
            $k = '<!--rztbl' . count($saved) . '-->';
            $saved[$k] = $m[0];
            return $k;
        }, $html);

    // Таблицу с шапкой (ряд марок) на телефоне держим широкой и прокручиваем.
    // Таблицу характеристик — «подпись/значение» — сжимать можно, она в две
    // колонки и помещается; лишняя прокрутка там только мешает.
    $html = preg_replace_callback('#<table\b.*?</table>#si', function ($m) {
        $wide = preg_match('#<th[\s>]|<thead[\s>]#i', $m[0]) ? ' rz-tw--wide' : '';
        return '<div class="rz-tw' . $wide . '">' . $m[0] . '</div>';
    }, $html);

    return $saved ? strtr($html, $saved) : $html;
}, 20);

/* -------------------------------------------------------------------- стили */

add_action('wp_head', function () {
    $display = RZ_THEME_FONTS
        ? '"Oswald","Arial Narrow","Helvetica Neue Condensed",Arial,sans-serif'
        : '"Arial Narrow","Helvetica Neue Condensed",Arial,sans-serif';
    $body = RZ_THEME_FONTS
        ? '"Golos Text","Segoe UI",system-ui,-apple-system,sans-serif'
        : '"Segoe UI",system-ui,-apple-system,"Helvetica Neue",Arial,sans-serif';
    $mono = RZ_THEME_FONTS
        ? '"IBM Plex Mono",ui-monospace,"SF Mono",Consolas,"Liberation Mono",monospace'
        : 'ui-monospace,"SF Mono",Consolas,"Liberation Mono",monospace';

    $css = <<<CSS
/* ------------------------------------------------ палитра и переменные */
:root{
  --rz-paper:#F4F4F1;      /* фон страницы, цвет бумаги */
  --rz-surface:#FFFFFF;    /* карточки и таблицы */
  --rz-tint:#F6F6F3;       /* шапки таблиц, подложки */
  --rz-ink:#2A2F33;        /* графит: заголовки, шапка, вторичные кнопки */
  --rz-ink-2:#3C4145;      /* текст абзацев */
  --rz-muted:#676C71;      /* подписи, служебное */
  --rz-line:#DEDEDA;       /* рамки */
  --rz-line-2:#E9E9E5;     /* внутренние линии таблиц */

  /* База теней: те же числа, что --rz-ink #2A2F33, но списком — rgba() берёт
     из переменной три числа, а не цвет. Тени собираются только через него,
     чтобы смена палитры не оставляла теней от прошлой. Задание 032. */
  --rz-ten-rgb:42,47,51;

  /* Янтарь разведён на четыре токена по ролям, и это не украшательство.
     Белый текст на #C8791A даёт контраст 3,38 при норме 4,5 — то есть на
     янтарной заливке писать белым нельзя вообще нигде. Поэтому:
       --rz-accent    заливка главного действия, текст на ней только тёмный;
       --rz-accent-h  та же заливка при наведении — СВЕТЛЕЕ, не темнее;
       --rz-accent-fg текст и значки НА янтарной заливке (5,21 на акценте);
       --rz-accent-t  янтарь как цвет текста на светлом фоне (6,31 на белом).
     Путать --rz-accent и --rz-accent-t нельзя: первый на тексте нечитаем,
     второй на заливке выглядит грязью. */
  --rz-accent:#C8791A;     /* янтарь: цвет каски и спецтехники, только на действии */
  --rz-accent-h:#D68A2C;   /* наведение: светлее заливки, 6,33 с тёмным текстом */
  --rz-accent-fg:#17191A;  /* текст на янтарной заливке */
  --rz-accent-t:#8A5310;   /* янтарь-текст на бумаге и белом */

  --rz-display:$display;
  --rz-body:$body;
  --rz-mono:$mono;

  /* Переменные Blocksy: перекрашивают шапку, меню, кнопки, формы, пагинацию.
     Ссылки делаем цветом текста, а акцент оставляем для главного действия и
     наведения — янтарь в каждой ссылке превращает страницу в разноцветную
     кашу и вдобавок плохо читается мелким кеглем. */
  --theme-palette-color-1:#2A2F33;
  --theme-palette-color-2:#C8791A;
  --theme-palette-color-3:#3C4145;
  --theme-palette-color-4:#2A2F33;
  --theme-palette-color-5:#DEDEDA;
  --theme-palette-color-6:#F4F4F1;
  --theme-palette-color-7:#F6F6F3;
  --theme-palette-color-8:#FFFFFF;

  --theme-font-family:var(--rz-body);
  --theme-font-size:16px;
  --theme-line-height:1.62;
  --theme-button-font-size:15px;
  --theme-button-font-weight:600;
  --theme-button-min-height:46px;
  --theme-button-padding:13px 24px;
  --theme-border-color:var(--rz-line);
}

/* ------------------------------------------------------------- основа */
body{
  background:var(--rz-paper);
  font-family:var(--rz-body);
  color:var(--rz-ink-2);
  -webkit-font-smoothing:antialiased;
}
.ct-container,.ct-container-narrow{max-width:1290px}

/* --------------------------------------------------------- типографика */
h1,h2,h3,h4,
.entry-title,.page-title{
  font-family:var(--rz-display);
  color:var(--rz-ink);
  letter-spacing:.01em;
}
h1,.entry-title{font-weight:600;font-size:clamp(30px,3.6vw,46px);line-height:1.06;margin:0 0 .5em}
.entry-content h2{font-weight:600;font-size:clamp(23px,2.4vw,31px);line-height:1.14;margin:2.1em 0 .6em;letter-spacing:.01em}
.entry-content h3{font-weight:500;font-size:clamp(19px,1.8vw,22px);line-height:1.25;margin:1.6em 0 .45em}
.entry-content p{color:var(--rz-ink-2)}
.entry-content a{color:var(--rz-ink);text-decoration:none;border-bottom:1px solid var(--rz-line)}
.entry-content a:hover{color:var(--rz-accent-t);border-bottom-color:var(--rz-accent-t)}

/* Маркер списка — точка размерной линии, а не жирный кружок. */
.entry-content ul{list-style:none;padding-left:0}
.entry-content ul li{position:relative;padding-left:19px;margin:.35rem 0}
.entry-content ul li::before{
  content:"";position:absolute;left:0;top:.66em;
  width:6px;height:6px;border-radius:50%;background:var(--rz-accent)
}
.entry-content ol{padding-left:1.25em}
.entry-content ol li{margin:.35rem 0}
.entry-content hr{border:0;border-top:1px dashed var(--rz-line);margin:2.2em 0}

/* ------------------------------------------------------------ таблицы */
/* Главное на сайте. Цифры моноширинные и табличные: колонки массы и цены
   выстраиваются столбиком, взгляд идёт вниз, а не спотыкается. */
.entry-content table,.wp-block-table table{
  width:100%;border-collapse:collapse;background:var(--rz-surface);
  font-size:14.5px;line-height:1.45
}
.wp-block-table,.rz-tw{
  overflow-x:auto;border:1px solid var(--rz-line);border-radius:0;
  background:var(--rz-surface)
}
.rz-tw{margin:1.3em 0}
.rz-tw table{margin:0}
.entry-content table thead th,.wp-block-table thead th{
  background:var(--rz-tint);color:var(--rz-muted);
  font-family:var(--rz-mono);font-size:10px;font-weight:400;
  letter-spacing:.16em;text-transform:uppercase;
  text-align:left;padding:12px 14px;border-bottom:1px solid var(--rz-line);
  white-space:nowrap
}
.entry-content table td,.wp-block-table td{
  padding:11px 14px;border-top:1px solid var(--rz-line-2);
  font-variant-numeric:tabular-nums;font-feature-settings:"tnum" 1;
  color:var(--rz-ink-2)
}
/* На сайте два разных вида таблиц, и путать их нельзя.

   1) Таблица характеристик: две колонки, шапки нет. Слева подпись
      («Наружный диаметр»), справа значение. Главное здесь — значение,
      поэтому подпись приглушаем, а цифру делаем моноширинной и тёмной.

   2) Таблица ряда марок: есть thead («МАРКА | ВЫСОТА | ЦЕНА»). Здесь
      главное — марка, она и держит строку узким гротеском.

   Различаем по наличию thead: `thead + tbody` — соседний селектор,
   :has() не нужен, работает и в старых браузерах. */

/* 1) характеристики — по умолчанию */
.entry-content table td:first-child,.wp-block-table td:first-child{
  font-family:var(--rz-body);font-weight:400;font-size:15px;
  color:var(--rz-muted);width:44%
}
.entry-content table td:first-child+td,.wp-block-table td:first-child+td{
  font-family:var(--rz-mono);font-weight:500;font-size:14px;color:var(--rz-ink)
}

/* 2) ряд марок — таблица с шапкой */
.entry-content thead+tbody td:first-child,.wp-block-table thead+tbody td:first-child{
  font-family:var(--rz-display);font-weight:500;font-size:16px;
  color:var(--rz-ink);width:auto
}
.entry-content thead+tbody td:first-child+td,.wp-block-table thead+tbody td:first-child+td{
  font-family:var(--rz-mono);font-weight:400;font-size:13.5px;color:var(--rz-ink-2)
}
.entry-content table td:not(:first-child){font-family:var(--rz-mono);font-size:13.5px}

.entry-content table td:first-child a{border-bottom:0}
.entry-content table td:first-child a:hover{border-bottom:1px solid var(--rz-accent-t)}
/* Полосатости нет: ряды разделяет тонкая линия, как в спецификации. */
.entry-content table tbody tr:nth-child(even){background:transparent}
.entry-content table tbody tr:hover{background:var(--rz-tint)}

/* --------------------------------------------------- частые вопросы */
.entry-content details{
  background:var(--rz-surface);border:1px solid var(--rz-line);border-radius:0;
  padding:0 18px;margin:.5rem 0
}
.entry-content summary{
  cursor:pointer;font-family:var(--rz-display);font-weight:500;font-size:17px;
  color:var(--rz-ink);padding:14px 30px 14px 0;position:relative;list-style:none
}
.entry-content summary::-webkit-details-marker{display:none}
.entry-content summary::after{
  content:"+";position:absolute;right:0;top:11px;
  color:var(--rz-accent-t);font-size:1.4rem;font-weight:400;line-height:1
}
.entry-content details[open] summary::after{content:"\\2013"}
.entry-content details p{margin:0 0 15px;color:var(--rz-ink-2);font-size:15px}

/* Блок «Частые вопросы» из h3+p — тот же вид, что у details. */
.entry-content h3+p{margin-top:.2em}

/* ------------------------------------------------------------- кнопки */
.wp-block-button__link,.wp-element-button,
.ct-button,button[type=submit],input[type=submit],.wpcf7-submit{
  background:var(--rz-ink);color:#fff;border:0;border-radius:0;
  font-family:var(--rz-body);font-weight:600;font-size:15px;
  padding:14px 26px;letter-spacing:0;transition:background .15s
}
.wp-block-button__link:hover,.wp-element-button:hover,
.ct-button:hover,button[type=submit]:hover,input[type=submit]:hover,.wpcf7-submit:hover{
  background:var(--rz-accent);color:var(--rz-accent-fg)
}

/* Главное действие — янтарное сразу, а не только под курсором: на телефоне
   наведения нет вовсе, и раньше акцент там не показывался ни разу.
   Кнопка отправки заявки — единственная такая на странице; прайс, пагинация
   и кнопки в тексте остаются графитовыми, чтобы янтарь не обесценился.

   Селектор длиннее, чем хочется: правило выше ловит .wpcf7-submit ещё и как
   input[type=submit] (специфичность 0-1-1), и голого класса 0-1-0 не хватило
   бы, чтобы его перебить. */
.wpcf7-submit,
input[type=submit].wpcf7-submit,
button[type=submit].wpcf7-submit{
  background:var(--rz-accent);color:var(--rz-accent-fg)
}
.wpcf7-submit:hover,
input[type=submit].wpcf7-submit:hover,
button[type=submit].wpcf7-submit:hover{
  background:var(--rz-accent-h);color:var(--rz-accent-fg)
}

/* Кнопка «Запросить счёт» в шапке карточки (mu-rz-card.php). Заливка и цвет
   текста заданы там инлайном — их инлайн и держит; сюда вынесено только
   наведение, которого у инлайнового стиля быть не может. Отсюда !important:
   иначе инлайновая заливка не сдвинется. */
.entry-content a.rz-card-cta:hover{
  background:var(--rz-accent-h)!important;border-color:var(--rz-accent-h)!important;
  color:var(--rz-accent-fg)!important
}
.wp-block-button.is-style-outline .wp-block-button__link{
  background:transparent;color:var(--rz-ink);border:1px solid var(--rz-ink)
}
.wp-block-button.is-style-outline .wp-block-button__link:hover{
  background:var(--rz-ink);color:#fff
}

/* -------------------------------------------------------------- формы */
input[type=text],input[type=email],input[type=tel],textarea,select{
  border:1px solid var(--rz-line);border-radius:0;background:var(--rz-surface);
  font-family:var(--rz-body);font-size:15px;padding:12px 14px
}
input[type=text]:focus,input[type=email]:focus,input[type=tel]:focus,textarea:focus{
  border-color:var(--rz-ink);outline:0
}

/* ------------------------------------------------------- обвязка темы */
.site-header,.ct-header{background:var(--rz-surface);border-bottom:1px solid var(--rz-line)}
.ct-header [data-id="menu"] .menu > li > a{
  font-family:var(--rz-display);font-weight:500;font-size:15.5px;letter-spacing:.02em
}
.site-footer,.ct-footer{background:var(--rz-surface);border-top:1px solid var(--rz-line)}
/* H1 на /blog/ добавляет mu-rz-blog-h1.php прямо в <main>, мимо контейнера
   темы: заголовок прижимался к левому краю окна и к шапке. Ставим его на одну
   вертикаль с карточками статей. */
main.site-main > h1.page-title{max-width:1290px;margin:36px auto 24px;padding:0 20px}

.ct-breadcrumbs{font-family:var(--rz-mono);font-size:12.5px;color:var(--rz-muted)}
.ct-breadcrumbs a{color:var(--rz-muted)}
.ct-breadcrumbs a:hover{color:var(--rz-accent-t)}

/* Девять правил-заплаток на !important, гасивших прошлую палитру в разметке
 * страниц, сняты заданиями 031 и 032: цвета переписаны в содержимом,
 * скругления убраны в самих плагинах, которые их печатали. Осталось одно
 * правило, и оно не заплатка, а часть палитры: скруглений в ней нет вовсе. */
.entry-content .wp-block-button__link{border-radius:0!important}

/* Нижняя строка футера Blocksy — «Оформление разработал CreativeThemes».
   Под ней идёт наш собственный подвал с реквизитами, получается два подвала
   подряд. Скрываем строку, но сам <footer> оставляем: в нём микроразметка
   WPFooter. Насовсем текст убирается в настройках темы, это дело владельца. */
.ct-footer [data-row="bottom"]{display:none}

/* Наш подвал из mu-rz-footer.php — див без id и класса со своими инлайновыми
   стилями. Инлайн бьётся только !important; это не небрежность, а
   единственный способ не трогать соседний плагин. */
body>div:not([id]):not([class]){
  background:var(--rz-ink)!important;color:#9AA09C!important;
  font-family:var(--rz-body)!important;font-size:13.5px!important;
  line-height:1.75!important;padding:30px 16px!important;
  border-top:3px solid var(--rz-accent)!important
}
body>div:not([id]):not([class]) strong{
  font-family:var(--rz-display)!important;font-weight:500!important;
  font-size:16px!important;letter-spacing:.02em!important
}
body>div:not([id]):not([class]) a{border-bottom:1px solid rgba(255,255,255,.25)!important}

/* Плавающая кнопка телефона — тоже инлайн из mu-regionzhbi-setup.php.
   Зелёная «пилюля» была из прошлой палитры; делаем её строгой. */
body>a[href^="tel:"]{
  background:var(--rz-ink)!important;border-radius:2px!important;
  font-family:var(--rz-body)!important;font-weight:600!important;
  font-size:14.5px!important;padding:13px 20px!important;
  letter-spacing:.01em!important;
  box-shadow:0 6px 20px rgba(var(--rz-ten-rgb),.18)!important
}
body>a[href^="tel:"]:hover{background:var(--rz-accent)!important;color:var(--rz-accent-fg)!important}

/* ------------------------------------------------------------ телефон */
@media (max-width:767px){
  .entry-content table,.wp-block-table table{font-size:13.5px}
  .entry-content table td,.wp-block-table td{padding:10px 11px}
  .entry-content table td:first-child{font-size:14px}
  .entry-content thead+tbody td:first-child{font-size:15px}
  .entry-content table td:not(:first-child){font-size:12.5px}
  .wp-block-button__link,.wp-element-button,.wpcf7-submit{
    display:block;width:100%;text-align:center;min-height:52px
  }
  .entry-content h2{margin-top:1.7em}

  /* Таблица марок шире экрана — держим колонки читаемыми и даём прокрутку
     пальцем вбок вместо сжатия в три строки на ячейку. Таблицы характеристик
     этого не получают: две колонки помещаются, прокрутка там лишняя. */
  .rz-tw--wide table,.wp-block-table table{min-width:540px}

  /* Плавающая «пилюля» телефона на узком экране закрывала текст. Делаем из неё
     нижнюю полосу во всю ширину — она и заметнее, и ничего не перекрывает:
     под неё отведено место отступом body. */
  body{padding-bottom:56px}
  body>a[href^="tel:"]{
    left:0!important;right:0!important;bottom:0!important;
    border-radius:0!important;text-align:center!important;
    padding:16px 12px!important;font-size:15px!important;
    border-top:2px solid var(--rz-accent)!important;
    box-shadow:0 -2px 14px rgba(var(--rz-ten-rgb),.16)!important
  }
}
CSS;

    echo "\n<style id=\"rz-theme\">\n" . $css . "\n</style>\n";
}, 999);
