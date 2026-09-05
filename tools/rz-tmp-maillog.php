<?php
/**
 * Plugin Name: РегионЖБИ — ВРЕМЕННЫЙ журнал заголовков писем (задание 017)
 * Description: Пишет в файл вне сайта дату, From, Reply-To и тему каждого уходящего письма. Ставится только на время трёх проверочных отправок задания 017 и сразу снимается. Тело письма НЕ пишется.
 *
 * Зачем. Задание требует по каждой из трёх проверочных заявок сказать, какой
 * адрес окажется в «Ответить». Почтовых ящиков на сервере нет, заглянуть в
 * пришедшее письмо нечем, а вызов фильтра из консоли показывает только
 * намерение CF7, а не итоговый заголовок письма. Единственная точка, где
 * заголовок уже собран и ещё виден изнутри сайта, — phpmailer_init.
 *
 * Ничего не меняет: только читает и дописывает строку в файл.
 */
if (!defined('ABSPATH')) exit;

add_action('phpmailer_init', function ($m) {
    $dir = dirname(ABSPATH) . '/backups/20260905-017-maillog';
    if (!is_dir($dir)) { @mkdir($dir, 0700, true); }

    $reply = array();
    if (method_exists($m, 'getReplyToAddresses')) {
        foreach ((array) $m->getReplyToAddresses() as $a) {
            $reply[] = is_array($a) ? $a[0] : $a;
        }
    }

    $stroka = date('Y-m-d H:i:s')
        . ' | From: ' . $m->From
        . ' | Reply-To: ' . (count($reply) ? implode(', ', $reply) : 'НЕТ ЗАГОЛОВКА')
        . ' | Subject: ' . $m->Subject
        . "\n";
    @file_put_contents($dir . '/maillog.txt', $stroka, FILE_APPEND);
}, 99);
