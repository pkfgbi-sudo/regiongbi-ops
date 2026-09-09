#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Задание 031, пункт 1. Собирает packages/cveta-01.json из выгрузки
tools/w031/dump-cveta.php (см. отчёт: файл берётся с Бегета, пакет обязан
лежать в репозитории до заливки — deploy.sh делает reset --hard origin/main).

Правки только внутри атрибутов style="…" плюс, на главной и её дубле /home/,
внутри JSON-атрибутов блоков Gutenberg — иначе редактор объявит блоки битыми:
разметка и атрибуты обязаны совпадать.

Каждое «было» считается. Если счёт по правилу разошёлся с ожидаемым из
задания — скрипт печатает расхождение и выходит с ошибкой, а не подгоняет.

Запуск: python3 tools/w031/sobrat-cveta-01.py <дамп.json> packages/cveta-01.json
"""
import json
import re
import sys
from collections import Counter

# --- замены цвета: (свойство, было) -> стало. Свойство берётся как есть из
#     объявления, сравнение регистронезависимое.
ZAMENY = {
    ('background-color', '#23483a'): '#2A2F33',
    ('background-color', '#23272e'): '#2A2F33',
    ('background-color', '#1b1e22'): '#2A2F33',
    ('background-color', '#2f6b52'): '#C8791A',
    ('color', '#23483a'): '#2A2F33',
    ('color', '#9fbbad'): '#B9BDBA',
    ('color', '#c6cbc6'): '#B9BDBA',
    ('color', '#b7cec1'): '#B9BDBA',
    ('color', '#6c726b'): '#9AA09C',
}
# --- объявления, которые убираются целиком
UBRAT = {('background', '#f6f7f3')}          # старый --rz-tint
UBRAT_SVOYSTVO = {'border-radius'}           # в палитре «инженерный каталог» скруглений нет

# Кнопка, у которой заливка была #2f6b52: текст на янтаре должен быть тёмным,
# инлайнового color у неё нет вовсе — его добавляем.
KNOPKA_FON = '#2f6b52'
KNOPKA_TEXT = '#17191A'

# Ожидаемые счётчики из задания. border-radius: 63 на двенадцати страницах со
# цветом плюс 16 на трёх страницах, которых скан задания 030 не видел (в их
# разметке нет ни одного «#», а скан отбирал страницы по нему).
OZHIDANIE = {
    'background-color #23483a': 4,
    'color #23483a': 10,
    'background-color #23272e': 2,
    'background-color #1b1e22': 2,
    'background-color #2f6b52': 2,
    'color #9fbbad': 8,
    'color #c6cbc6': 2,
    'color #b7cec1': 2,
    'color #6c726b': 2,
    'background #f6f7f3': 14,
    'border-radius': 79,
    'кнопке добавлен color': 2,
}

# --- правки JSON-атрибутов блоков. Только главная и /home/.
#     (было, стало, сколько раз ждём на одной странице)
BLOKI = [
    ('"customOverlayColor":"#1B1E22"', '"customOverlayColor":"#2A2F33"', 1),
    ('"color":{"text":"#c6cbc6"}', '"color":{"text":"#B9BDBA"}', 1),
    ('"color":{"text":"#9fbbad"}', '"color":{"text":"#B9BDBA"}', 4),
    ('"color":{"text":"#b7cec1"}', '"color":{"text":"#B9BDBA"}', 1),
    ('"color":{"text":"#6c726b"}', '"color":{"text":"#9AA09C"}', 1),
    ('"color":{"background":"#23272e"}', '"color":{"background":"#2A2F33"}', 1),
    # зелёная кнопка: заливка на янтарь, скругление убрано, добавлен цвет текста
    ('{"backgroundColor":"","style":{"color":{"background":"#2f6b52"},"border":{"radius":"5px"}}}',
     '{"backgroundColor":"","style":{"color":{"background":"#C8791A","text":"#17191A"}}}', 1),
    # класс: цвет текста у кнопки появился, Gutenberg помечает это has-text-color.
    # Сам style правится ниже общим разбором, здесь только класс.
    ('<a class="wp-block-button__link has-background" href="/kontakty/#zayavka"'
     ' style="border-radius:5px;background-color:#2f6b52">',
     '<a class="wp-block-button__link has-text-color has-background" href="/kontakty/#zayavka"'
     ' style="border-radius:5px;background-color:#2f6b52">', 1),
    # вторая кнопка того же ряда — контурная, у неё только скругление
    ('{"className":"is-style-outline","style":{"border":{"radius":"5px"}}}',
     '{"className":"is-style-outline"}', 1),
    # плашка «Нужен расчёт под объект?»
    ('"color":{"background":"#23483a"},"border":{"radius":"16px"}',
     '"color":{"background":"#2A2F33"}', 1),
    # белая кнопка на этой плашке: белый фон оставляем, зелёный текст — нет
    ('"color":{"background":"#ffffff","text":"#23483a"},"border":{"radius":"6px"}',
     '"color":{"background":"#ffffff","text":"#2A2F33"}', 1),
    # группа-полоса каталога
    ('"color":{"background":"#23483a"}', '"color":{"background":"#2A2F33"}', 1),
]
S_BLOKAMI = ('glavnaya', 'home')

RX_STYLE = re.compile(r'\sstyle="([^"]*)"')


def pravka_stilya(znach, schet, gde):
    """Одно значение атрибута style -> новое значение. Считает срабатывания."""
    knopka = re.search(r'background-color\s*:\s*' + KNOPKA_FON, znach, re.I) is not None
    out = []
    for decl in znach.split(';'):
        if decl.strip() == '':
            continue
        if ':' not in decl:
            out.append(decl)
            continue
        prop, val = decl.split(':', 1)
        p = prop.strip().lower()
        v = val.strip().lower()

        if p in UBRAT_SVOYSTVO:
            schet['border-radius'] += 1
            schet[gde + ' | border-radius'] += 1
            continue
        if (p, v) in UBRAT:
            schet[p + ' ' + v] += 1
            schet[gde + ' | ' + p + ' ' + v] += 1
            continue
        if (p, v) in ZAMENY:
            schet[p + ' ' + v] += 1
            schet[gde + ' | ' + p + ' ' + v] += 1
            novoe = prop + ':' + ZAMENY[(p, v)]
            # цвет текста кнопки идёт перед заливкой — так же, как Gutenberg
            # сериализует остальные кнопки на этой странице
            if knopka and p == 'background-color':
                out.append('color:' + KNOPKA_TEXT)
                schet['кнопке добавлен color'] += 1
                schet[gde + ' | кнопке добавлен color'] += 1
            out.append(novoe)
            continue
        out.append(decl)
    return ';'.join(out)


def main():
    dump_f, out_f = sys.argv[1], sys.argv[2]
    dump = json.load(open(dump_f, encoding='utf-8'))
    schet = Counter()
    items = []
    otchet = []

    for r in sorted(dump, key=lambda x: x['ID']):
        if r['post_type'] != 'page':
            print('ПРОПУСК: не страница —', r['slug'], r['post_type'])
            continue
        c0 = r['content']
        c = c0
        gde = r['slug']

        if gde in S_BLOKAMI:
            for bylo, stalo, zhdem in BLOKI:
                n = c.count(bylo)
                if n != zhdem:
                    print('РАСХОЖДЕНИЕ в блоках %s: «%s» встречается %d, ждали %d'
                          % (gde, bylo[:60], n, zhdem))
                    sys.exit(1)
                c = c.replace(bylo, stalo)
                schet['блоки ' + gde] += n

        def repl(m):
            novoe = pravka_stilya(m.group(1), schet, gde)
            return '' if novoe == '' else ' style="' + novoe + '"'

        c = RX_STYLE.sub(repl, c)

        if c == c0:
            print('без изменений:', gde)
            continue
        # путь, а не полный адрес: публикатор ищет страницу по пути
        url = re.sub(r'^https?://[^/]+', '', r['url'])
        items.append({'url': url, 'content': c})
        otchet.append((gde, r['ID'], url, len(c0), len(c)))

    # --- сверка счётчиков с заданием
    sboy = False
    print('\n--- счётчик правил (всего по пакету)')
    for k in sorted(OZHIDANIE):
        est, zhdem = schet[k], OZHIDANIE[k]
        znak = 'ок' if est == zhdem else 'РАСХОЖДЕНИЕ'
        if est != zhdem:
            sboy = True
        print('  %-28s %4d  ждали %4d  %s' % (k, est, zhdem, znak))
    print('\n--- по страницам')
    for gde, pid, url, a, b in otchet:
        pravila = sorted(k.split(' | ', 1)[1] + '×' + str(v)
                         for k, v in schet.items() if k.startswith(gde + ' | '))
        print('  %-32s #%-5d %-46s %6d -> %-6d  %s'
              % (gde, pid, url, a, b, ', '.join(pravila)))

    if sboy:
        print('\nСЧЁТ НЕ СОШЁЛСЯ — пакет не записан.')
        sys.exit(1)

    pkg = {
        'package': 'cveta-01',
        'primechanie': (
            'Задание 031. Перекраска инлайновых цветов старой зелёной палитры и '
            'снятие border-radius в содержимом. Тексты, заголовки и структура не '
            'тронуты: правки только внутри style="…" и, на главной и её дубле '
            '/home/, внутри JSON-атрибутов блоков Gutenberg — чтобы разметка и '
            'атрибуты остались согласованы и редактор не считал блоки битыми. '
            'Собрано tools/w031/sobrat-cveta-01.py из выгрузки '
            'tools/w031/dump-cveta.php.'),
        'items': items,
    }
    with open(out_f, 'w', encoding='utf-8') as f:
        json.dump(pkg, f, ensure_ascii=False, indent=1)
        f.write('\n')
    print('\nзаписано %s, позиций %d' % (out_f, len(items)))


main()
