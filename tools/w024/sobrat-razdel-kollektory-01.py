#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Задание 024, пункт 2. Пакет kollektory-01-razdel: шесть марок в таблице
раздела /catalog/elementy-kollektorov/ становятся ссылками на свои карточки.
Строки в таблице уже есть — добавляются только ссылки, ни одна цифра не
меняется.

Приём тот же, что в tools/w023/sobrat-razdel-vp-04.py: каждая строка
проверяется на единственность перед заменой.

Запуск: python3 tools/w024/sobrat-razdel-kollektory-01.py [dump.json]
"""
import json, os, sys

REPO = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
DUMP = sys.argv[1] if len(sys.argv) > 1 else '/srv/regiongbi/tmp/w024/w024-dump.json'

pages = {p['slug']: p for p in json.load(open(DUMP, encoding='utf-8'))}
pkg_src = json.load(open(os.path.join(REPO, 'packages', 'kollektory-01.json'), encoding='utf-8'))

razdel = pages['elementy-kollektorov']
content = razdel['content']

# марка в таблице раздела -> слаг карточки из пакета kollektory-01
marki = {
    'КД-36':   'kd-36',
    'КП-12':   'kp-12',
    'КУ-21':   'ku-21',
    'КС-21 д': 'ks-21-d',
    'ДБ-21':   'db-21',
    'ДБ-24':   'db-24',
}
urls = {i['slug']: i['url'] for i in pkg_src['items']}
assert set(marki.values()) == set(urls), 'марки пакета и списка ссылок разошлись'

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
    'package': 'kollektory-01-razdel',
    'primechanie': ('Задание 024, пункт 2: шесть марок элементов коллекторов в таблице '
                    'раздела получают ссылки на карточки пакета kollektory-01. '
                    'Цифры не трогаются.'),
    'items': [{'url': razdel['path'], 'content': content}],
}
out = os.path.join(REPO, 'packages', 'kollektory-01-razdel.json')
with open(out, 'w', encoding='utf-8') as f:
    json.dump(pkg, f, ensure_ascii=False, indent=2)
    f.write('\n')
print('ссылок добавлено:', len(log))
for m, u in log:
    print('   ', m, '->', u)
print('длина содержимого: было', len(razdel['content']), 'стало', len(content))
