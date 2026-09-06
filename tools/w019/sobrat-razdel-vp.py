#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Задание 020, пункт 4. Пакет vp-02-razdel: в таблице раздела
/catalog/plity-perekrytiya-kanalov-vp/ шесть марок, у которых появились свои
карточки, становятся ссылками на эти карточки. Строки в таблице уже есть —
добавляются только ссылки, ни одна цифра не меняется.

Запуск: python3 tools/w019/sobrat-razdel-vp.py [dump.json]
"""
import json, os, sys

REPO = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
DUMP = sys.argv[1] if len(sys.argv) > 1 else '/srv/regiongbi/tmp/w019/dump.json'

pages = {p['slug']: p for p in json.load(open(DUMP, encoding='utf-8'))}
vp = json.load(open(os.path.join(REPO, 'packages', 'vp-01.json'), encoding='utf-8'))

razdel = pages['plity-perekrytiya-kanalov-vp']
content = razdel['content']

# марка в таблице раздела -> слаг карточки из пакета vp-01
marki = {
    'ВП16-6': 'vp-16-6',
    'ВП19-6': 'vp-19-6',
    'ВП22-6': 'vp-22-6',
    'ВП28-12': 'vp-28-12',
    'ВП31-12': 'vp-31-12',
    'ВП46-12': 'vp-46-12',
}
urls = {i['slug']: i['url'] for i in vp['items']}
assert set(marki.values()) == set(urls), 'марки пакета и списка ссылок разошлись'

log = []
for marka, slug in marki.items():
    bylo = '<tr><td>%s</td>' % marka
    stalo = '<tr><td><a href="%s">%s</a></td>' % (urls[slug], marka)
    n = content.count(bylo)
    assert n == 1, 'строка «%s» встречается %d раз' % (marka, n)
    content = content.replace(bylo, stalo)
    log.append((marka, urls[slug]))

pkg = {
    'package': 'vp-02-razdel',
    'primechanie': ('Задание 020, пункт 4: шесть марок ВП в таблице раздела получают '
                    'ссылки на новые карточки пакета vp-01. Цифры не трогаются.'),
    'items': [{'url': razdel['path'], 'content': content}],
}
json.dump(pkg, open(os.path.join(REPO, 'packages', 'vp-02-razdel.json'), 'w', encoding='utf-8'),
          ensure_ascii=False, indent=1)
print('ссылок добавлено:', len(log))
for m, u in log:
    print('   ', m, '->', u)
print('длина содержимого: было', len(razdel['content']), 'стало', len(content))
