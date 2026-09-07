#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Задание 023, пункт 2. Пакет vp-04-razdel: пять марок второй партии в таблице
раздела /catalog/plity-perekrytiya-kanalov-vp/ становятся ссылками на свои
карточки. Строки в таблице уже есть — добавляются только ссылки, ни одна
цифра не меняется.

Тот же приём, что и в tools/w019/sobrat-razdel-vp.py (задание 020): каждая
строка проверяется на единственность перед заменой. Марки в таблице записаны
с разными пробелами («ВП 25-6», но «ВП37-12») — берём как есть.

Запуск: python3 tools/w023/sobrat-razdel-vp-04.py [dump.json]
"""
import json, os, sys

REPO = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
DUMP = sys.argv[1] if len(sys.argv) > 1 else '/srv/regiongbi/tmp/w023/dump.json'

pages = {p['slug']: p for p in json.load(open(DUMP, encoding='utf-8'))}
vp = json.load(open(os.path.join(REPO, 'packages', 'vp-03.json'), encoding='utf-8'))

razdel = pages['plity-perekrytiya-kanalov-vp']
content = razdel['content']

# марка в таблице раздела -> слаг карточки из пакета vp-03
marki = {
    'ВП 25-6':  'vp-25-6',
    'ВП 28-6':  'vp-28-6',
    'ВП 25-12': 'vp-25-12',
    'ВП37-12':  'vp-37-12',
    'ВП40-12':  'vp-40-12',
}
urls = {i['slug']: i['url'] for i in vp['items']}
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
    'package': 'vp-04-razdel',
    'primechanie': ('Задание 023, пункт 2: пять марок ВП второй партии в таблице раздела '
                    'получают ссылки на карточки пакета vp-03. Цифры не трогаются.'),
    'items': [{'url': razdel['path'], 'content': content}],
}
json.dump(pkg, open(os.path.join(REPO, 'packages', 'vp-04-razdel.json'), 'w', encoding='utf-8'),
          ensure_ascii=False, indent=1)
print('ссылок добавлено:', len(log))
for m, u in log:
    print('   ', m, '->', u)
print('длина содержимого: было', len(razdel['content']), 'стало', len(content))
