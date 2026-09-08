#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Задание 025, пункт 2. Пакет kollektory-02-razdel: четыре марки в таблице
раздела /catalog/elementy-kollektorov/ становятся ссылками на свои карточки.
Строки в таблице уже есть — добавляются только ссылки, ни одна цифра не
меняется. Ссылки задания 024 (шесть марок) в содержимом уже стоят и
сохраняются.

ПВК-8 сюда не входит: в таблице раздела /catalog/kolodtsy-unifitsirovannye/
такой строки нет вовсе (там 8 позиций: ВГ-10…ВГ-20, ВС-10…ВС-15, ВД-8).
Ссылку некуда вешать, а дописывать в прайс раздела строку с размерами,
массой и ценой, которых нет ни в одной таблице сайта, — это выдумывание
данных (раздел 1 AGENTS.md). Вынесено в отчёт.

Запуск: python3 tools/w024/sobrat-razdel-kollektory-02.py [dump.json]
"""
import json, os, sys

REPO = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
DUMP = sys.argv[1] if len(sys.argv) > 1 else '/srv/regiongbi/tmp/w024/w024-posle.json'

pages = {p['slug']: p for p in json.load(open(DUMP, encoding='utf-8'))}
pkg_src = json.load(open(os.path.join(REPO, 'packages', 'kollektory-02.json'), encoding='utf-8'))

razdel = pages['elementy-kollektorov']
content = razdel['content']

# марка в таблице раздела -> слаг карточки из пакета kollektory-02
marki = {
    'КП-21': 'kp-21',
    'ДБ-29': 'db-29',
    'ДБ-39': 'db-39',
    'ДБ-49': 'db-49',
}
urls = {i['slug']: i['url'] for i in pkg_src['items']}
for slug in marki.values():
    assert slug in urls, 'в пакете нет позиции %s' % slug

log = []
for marka, slug in marki.items():
    bylo = '<tr><td>%s</td>' % marka
    stalo = '<tr><td><a href="%s">%s</a></td>' % (urls[slug], marka)
    n = content.count(bylo)
    assert n == 1, 'строка «%s» встречается %d раз' % (marka, n)
    assert content.count(stalo) == 0, 'ссылка на «%s» уже стоит' % marka
    content = content.replace(bylo, stalo)
    log.append((marka, urls[slug]))

pkg = {
    'package': 'kollektory-02-razdel',
    'primechanie': ('Задание 025, пункт 2: КП-21, ДБ-29, ДБ-39 и ДБ-49 в таблице раздела '
                    'получают ссылки на карточки пакета kollektory-02. Цифры не трогаются. '
                    'ПВК-8 не включён: строки для него в таблице раздела колодцев нет.'),
    'items': [{'url': razdel['path'], 'content': content}],
}
out = os.path.join(REPO, 'packages', 'kollektory-02-razdel.json')
with open(out, 'w', encoding='utf-8') as f:
    json.dump(pkg, f, ensure_ascii=False, indent=2)
    f.write('\n')
print('ссылок добавлено:', len(log))
for m, u in log:
    print('   ', m, '->', u)
print('длина содержимого: было', len(razdel['content']), 'стало', len(content))
