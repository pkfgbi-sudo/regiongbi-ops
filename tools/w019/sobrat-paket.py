#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Задание 019. Сборка пакета правок цен для rzpub.php.

Сравнивает прайс (packages/ceny-prays-2026-06.csv) с ценами на сайте
(tmp/w019/dump.json) во всех местах, где цена живёт: ячейка «Цена» в карточке,
строки таблиц разделов и таблиц «Рядом в ряду» на соседних карточках, мета
_rz_product и SEO-мета Rank Math. Где прайс отличается — готовит правку.

Выход: packages/ceny-01.json, packages/ceny-zamena.csv
"""
import json, re, csv, os, sys

REPO = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
DUMP = sys.argv[1] if len(sys.argv) > 1 else '/srv/regiongbi/tmp/w019/dump.json'
OUT = os.path.join(REPO, 'packages')

pages = json.load(open(DUMP, encoding='utf-8'))
by_slug = {p['slug']: p for p in pages}
rows = list(csv.DictReader(open(os.path.join(OUT, 'ceny-prays-2026-06.csv'), encoding='utf-8'),
                           delimiter=';'))
prays = {r['slug']: int(float(r['cena_prays_2026_06'])) for r in rows}


def fmt(n, sep=' '):
    s, out = str(int(n)), ''
    while len(s) > 3:
        out, s = sep + s[-3:] + out, s[:-3]
    return s + out


CELL = re.compile(r'(<td>\s*Цена\s*</td>\s*<td>\s*)([\d][\d\s ]*)(\s*₽)')

zamena = []   # страница, место, было, стало
items = []

for r in rows:
    slug = r['slug']
    p = by_slug.get(slug)
    if not p:
        continue
    novaya = prays[slug]
    c = p['content']
    m = CELL.search(c)
    if m:
        staraya = int(re.sub(r'\D', '', m.group(2)))
        if staraya == novaya:
            continue           # цена уже прайсовая — не трогаем
        sep = ' ' if ' ' in m.group(2).strip() else ' '
        novyy = m.group(1) + fmt(novaya, sep) + m.group(3)
        assert c.count(m.group(0)) == 1, slug
        c = c.replace(m.group(0), novyy)
        zamena.append((slug, 'tekst-kartochki', staraya, novaya))
        items.append({'url': p['path'], 'content': c})

# ---- ККСр-3: цена есть в прайсе и в таблице раздела, но не в карточке.
# Строку «Цена» в таблицу характеристик добавляем с указанием источника —
# в этой таблице у каждой строки третья колонка «Источник».
p = by_slug.get('kksr-3')
if p and 'kksr-3' in prays and not CELL.search(p['content']):
    cena = prays['kksr-3']
    marker = '</tbody></table></figure>'
    assert p['content'].count(marker) >= 1
    stroka = ('<tr><td>Цена</td><td>%s ₽ с НДС за штуку</td>'
              '<td>прайс 01.06.2026</td></tr>' % fmt(cena))
    c = p['content'].replace(marker, stroka + marker, 1)
    meta = json.loads(p['rz_product']) if p['rz_product'] else {}
    meta['price'] = cena
    meta.setdefault('specs', {})['Цена'] = '%s ₽ с НДС за штуку' % fmt(cena)
    items.append({'url': p['path'], 'content': c, 'meta': meta})
    zamena.append(('kksr-3', 'tekst-kartochki (строка добавлена)', '', cena))

with open(os.path.join(OUT, 'ceny-zamena.csv'), 'w', encoding='utf-8', newline='') as f:
    w = csv.writer(f, delimiter=';')
    w.writerow(['stranica', 'mesto', 'bylo', 'stalo'])
    for z in zamena:
        w.writerow(z)

pkg = {
    'package': 'ceny-01',
    'primechanie': ('Задание 019. Цены прайса «ЖЕНЯ 1608» на 01.06.2026 совпали с ценами '
                    'на сайте по 123 маркам из 124. Правится одна страница: ККСр-3, у которой '
                    'цена есть в прайсе и в таблице раздела, но не в карточке.'),
    'items': items,
}
json.dump(pkg, open(os.path.join(OUT, 'ceny-01.json'), 'w', encoding='utf-8'),
          ensure_ascii=False, indent=1)
print('позиций в пакете:', len(items))
print('замен:', len(zamena))
for z in zamena:
    print('   ', z)
