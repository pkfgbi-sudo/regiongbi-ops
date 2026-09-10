#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Задание 033. Собирает два пакета для схлопывания карточек ФБС в раздел.

Вход  — выгрузка tools/w033/vygruzka-do.json (снята dump-ssylki.php на Бегете,
        только чтение).
Выход — packages/fbs-svod-01.json (страницы, публикатор rzpub.php)
        packages/fbs-svod-02.json (записи блога, публикатор rz-blog.php)

Что делает:
  1. Раздел 107: у семнадцати схлопываемых марок в таблице СНИМАЕТСЯ ссылка,
     марка остаётся текстом. Ссылка на fbs-24-4-6 остаётся.
  2. Страницы 204 и 640, записи 401/684/708: ссылки на семнадцать адресов
     переводятся на раздел с якорем /catalog/fbs-bloki/#<слаг>.

Содержимое самих семнадцати карточек не трогается — задание это прямо
запрещает, поэтому их в пакетах нет.

Ожидаемые числа зашиты ниже. Не сошлось — пакет не пишется: расхождение
означает, что содержимое на сайте не то, по которому собирали.
"""
import json, os, re, sys

KOREN = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
VYGR  = os.path.join(KOREN, 'tools', 'w033', 'vygruzka-do.json')

SHLOP = [
    'fbs-12-3-6','fbs-12-4-3','fbs-12-4-6','fbs-12-5-6','fbs-12-6-6',
    'fbs-24-3-6','fbs-24-5-6','fbs-24-6-6',
    'fbs-6-3-6','fbs-6-4-3','fbs-6-4-6','fbs-6-5-6',
    'fbs-9-3-6','fbs-9-4-3','fbs-9-4-6','fbs-9-5-6','fbs-9-6-6',
]
OSTAYOTSYA = 'fbs-24-4-6'
RAZDEL = '/catalog/fbs-bloki/'

# сколько ссылок на схлопываемые адреса ждём в каждом документе
ZHDEM = {'107': 17, '204': 17, '640': 3, '401': 14, '684': 5, '708': 3}

# Фраза таблицы раздела: после снятия ссылок «нажмите на марку» становится
# неправдой — кликабельна одна марка из восемнадцати. Меняем ровно эту фразу,
# цены, размеры и массы не трогаем.
FRAZA_BYLO  = 'Нажмите на марку — откроется карточка с полными характеристиками.'
FRAZA_STALO = ('Размеры, масса и цена каждой марки — в таблице ниже; '
               'по ходовой марке ФБС 24-4-6 есть отдельная карточка.')


def yakor(slug):
    return RAZDEL + '#' + slug


def snyat_ssylki_v_tablitse(html):
    """Раздел: <td><a href="/catalog/fbs-bloki/<слаг>/">ФБС X</a></td> -> <td>ФБС X</td>"""
    snyato = 0
    for s in SHLOP:
        shablon = re.compile(
            r'<td><a href="' + re.escape(RAZDEL + s + '/') + r'">([^<]+)</a></td>')
        html, n = shablon.subn(lambda m: '<td>' + m.group(1) + '</td>', html)
        snyato += n
    return html, snyato


def perevesti_na_yakor(html):
    """Везде, кроме раздела: href на карточку -> href на раздел с якорем."""
    perevedeno = 0
    for s in SHLOP:
        bylo = 'href="' + RAZDEL + s + '/"'
        stalo = 'href="' + yakor(s) + '"'
        n = html.count(bylo)
        if n:
            html = html.replace(bylo, stalo)
            perevedeno += n
    return html, perevedeno


def proverit(html, gde):
    """После правки ссылок на схлопываемые карточки остаться не должно."""
    ost = []
    for s in SHLOP:
        n = len(re.findall(r'href="[^"]*' + re.escape('/' + s) + r'/?"', html))
        if n:
            ost.append('%s x%d' % (s, n))
    if ost:
        sys.exit('%s: остались ссылки на схлопнутые: %s' % (gde, ', '.join(ost)))
    if ('href="' + RAZDEL + OSTAYOTSYA + '/"') not in html and gde == 'раздел 107':
        sys.exit('раздел 107: пропала ссылка на %s' % OSTAYOTSYA)


def main():
    d = json.load(open(VYGR, encoding='utf-8'))
    itog = []

    # --- 1. раздел
    r = d['107']
    c = r['content']
    naydeno = sum(c.count('href="' + RAZDEL + s + '/"') for s in SHLOP)
    if naydeno != ZHDEM['107']:
        sys.exit('раздел 107: ссылок на схлопываемые %d, ждали %d' % (naydeno, ZHDEM['107']))
    c2, snyato = snyat_ssylki_v_tablitse(c)
    if snyato != 17:
        sys.exit('раздел 107: снято ссылок %d, ждали 17 — строки таблицы не той формы' % snyato)
    if c2.count(FRAZA_BYLO) != 1:
        sys.exit('раздел 107: фраза про «нажмите на марку» найдена %d раз, ждали 1'
                 % c2.count(FRAZA_BYLO))
    c2 = c2.replace(FRAZA_BYLO, FRAZA_STALO)
    proverit(c2, 'раздел 107')
    itog.append(('107', r, c2, snyato))

    # --- 2. страницы и записи со ссылками
    for key in ('204', '640', '401', '684', '708'):
        x = d[key]
        c = x['content']
        naydeno = sum(c.count('href="' + RAZDEL + s + '/"') for s in SHLOP)
        if naydeno != ZHDEM[key]:
            sys.exit('%s %s: ссылок %d, ждали %d' % (key, x['slug'], naydeno, ZHDEM[key]))
        c2, perevedeno = perevesti_na_yakor(c)
        if perevedeno != naydeno:
            sys.exit('%s: переведено %d из %d' % (key, perevedeno, naydeno))
        proverit(c2, '%s %s' % (key, x['slug']))
        itog.append((key, x, c2, perevedeno))

    # --- пакет страниц
    stranicy = []
    zapisi = []
    for key, x, c2, n in itog:
        if x['type'] == 'page':
            stranicy.append({
                'url': '/' + x['url'].split('regiongbi.ru/', 1)[1],
                'slug': x['slug'],
                'content': c2,
            })
        else:
            # для записей — точечные замены: идемпотентно и не трогает остальной текст
            pary = []
            for s in SHLOP:
                bylo = 'href="' + RAZDEL + s + '/"'
                if x['content'].count(bylo):
                    pary.append([bylo, 'href="' + yakor(s) + '"'])
            zapisi.append({'slug': x['slug'], 'replace': pary})

    p1 = {'package': 'fbs-svod-01',
          'opisanie': 'Задание 033: раздел ФБС — снятие ссылок с 17 схлопнутых марок; '
                      'страницы 204 и 640 — ссылки на раздел с якорем',
          'items': stranicy}
    p2 = {'package': 'fbs-svod-02',
          'opisanie': 'Задание 033: три записи блога — ссылки на 17 схлопнутых карточек '
                      'переведены на раздел с якорем',
          'items': zapisi}

    for imya, pkg in (('fbs-svod-01', p1), ('fbs-svod-02', p2)):
        put = os.path.join(KOREN, 'packages', imya + '.json')
        with open(put, 'w', encoding='utf-8') as f:
            json.dump(pkg, f, ensure_ascii=False, indent=1)
            f.write('\n')
        print('записан %s: позиций %d' % (put, len(pkg['items'])))

    print('--- сводка')
    for key, x, c2, n in itog:
        print('  %-4s %-32s %-5s ссылок обработано %d, байт %d -> %d'
              % (key, x['slug'], x['type'], n, len(x['content']), len(c2)))


main()
