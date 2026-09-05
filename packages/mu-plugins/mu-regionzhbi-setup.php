<?php
/**
 * Plugin Name: РегионЖБИ — оформление и аналитика
 * Description: Бренд-стили (зелёный/графит), Яндекс.Метрика и Organization-schema. Работает как mu-plugin.
 * ПОЛОЖИТЬ в wp-content/mu-plugins/ (создать папку, если нет). Включается автоматически, без активации.
 */
if (!defined('ABSPATH')) exit;

// Номер счётчика Яндекс.Метрики. 0 = отключено.
// 05.09.2026 номер сменён на 112302967 (задание 012): прежний счётчик
// создан на неизвестном аккаунте, настройки отдают 403, целей в нём не завести.
// Номер объявлен здесь один раз; mu-rz-celi.php берёт его отсюда через
// REGIONZHBI_METRIKA_ID, чтобы счётчик и цели не разъехались.
if (!defined('REGIONZHBI_METRIKA_ID')) define('REGIONZHBI_METRIKA_ID', 112302967);
// ← ВПИШИ телефон и e-mail нового сайта (отдельные от zhbipro.ru)
if (!defined('REGIONZHBI_PHONE')) define('REGIONZHBI_PHONE', '+7 996 097-09-80');
if (!defined('REGIONZHBI_EMAIL')) define('REGIONZHBI_EMAIL', 'zakaz@regiongbi.ru');

/* 1. Бренд-стили контента (зелёный/графит) */
add_action('wp_head', function () { ?>
<style id="regionzhbi-brand">
:root{--rz-graphite:#23272E;--rz-pine:#23483A;--rz-pine-br:#2F6B52;--rz-line:#DBDDD6;--rz-tint:#F6F7F3}
.entry-content h2,.wp-block-post-content h2{font-weight:800;letter-spacing:-.01em;margin:2rem 0 .8rem}
.entry-content a{color:var(--rz-pine-br)}.entry-content a:hover{color:var(--rz-pine)}
.entry-content ul{list-style:none;padding-left:0}
.entry-content ul li{position:relative;padding-left:20px;margin:.4rem 0}
.entry-content ul li::before{content:"";position:absolute;left:0;top:.62em;width:7px;height:7px;border-radius:50%;background:var(--rz-pine-br)}
.entry-content table,.wp-block-table table{width:100%;border-collapse:collapse;font-size:.94rem}
.entry-content table thead th,.wp-block-table thead th{background:var(--rz-graphite);color:#fff;text-align:left;padding:12px 14px;font-size:.72rem;letter-spacing:.05em;text-transform:uppercase;font-weight:600}
.entry-content table td,.wp-block-table td{padding:11px 14px;border-top:1px solid var(--rz-line);font-variant-numeric:tabular-nums}
.entry-content table tbody tr:nth-child(even){background:var(--rz-tint)}
.entry-content table td:first-child{font-weight:600}
.wp-block-table{overflow-x:auto;border:1px solid var(--rz-line);border-radius:10px}
.entry-content details{background:#fff;border:1px solid var(--rz-line);border-radius:8px;padding:2px 16px;margin:.6rem 0}
.entry-content summary{cursor:pointer;font-weight:600;padding:13px 28px 13px 0;position:relative;list-style:none}
.entry-content summary::-webkit-details-marker{display:none}
.entry-content summary::after{content:"+";position:absolute;right:2px;top:9px;color:var(--rz-pine-br);font-size:1.35rem;font-weight:700}
.entry-content details[open] summary::after{content:"\2013"}
.entry-content details p{margin:0 0 14px;color:#4A4F49}
.wp-block-button__link,.wp-element-button{background:var(--rz-pine);border-radius:6px;font-weight:700}
.wp-block-button.is-style-outline .wp-block-button__link{color:var(--rz-pine);border-color:var(--rz-pine)}
</style>
<?php }, 20);


/* 3. Яндекс.Метрика + цели на клики (мессенджер/прайс)
 *
 * Цель call на клик по tel: убрана 05.09.2026 (задание 012): тот же клик
 * уже шлёт цель tel из mu-rz-celi.php, и в новом счётчике 112302967 цели
 * call нет. Два события на один клик — повод гадать через полгода. */
add_action('wp_head', function () {
    $id = (int) REGIONZHBI_METRIKA_ID;
    if (!$id) return; ?>
<!-- Yandex.Metrika -->
<script type="text/javascript">
(function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};m[i].l=1*new Date();
for(var j=0;j<document.scripts.length;j++){if(document.scripts[j].src===r){return;}}
k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})
(window,document,"script","https://mc.yandex.ru/metrika/tag.js","ym");
ym(<?php echo $id; ?>,"init",{clickmap:true,trackLinks:true,accurateTrackBounce:true,webvisor:true});
document.addEventListener('click',function(e){var a=e.target.closest('a');if(!a)return;
if(a.href&&(a.href.indexOf('wa.me')>-1||a.href.indexOf('t.me')>-1))ym(<?php echo $id; ?>,'reachGoal','messenger');
if((a.className&&a.className.indexOf('pr-dl')>-1)||/prajs|price/i.test(a.href||''))ym(<?php echo $id; ?>,'reachGoal','price');});
</script>
<noscript><div><img src="https://mc.yandex.ru/watch/<?php echo $id; ?>" style="position:absolute;left:-9999px;" alt="" /></div></noscript>
<!-- /Yandex.Metrika -->
<?php }, 22);
