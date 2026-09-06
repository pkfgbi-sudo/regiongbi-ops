#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Задание 019. Разбор цен на сайте и сборка пакета правок.

Вход:
  tmp/w019/dump.json                  — выгрузка 204 страниц (rz-dump-ceny.php)
  packages/ceny-prays-2026-06.csv     — прайс «ЖЕНЯ 1608» на 01.06.2026

Выход:
  packages/ceny-do.csv      — состояние ДО правки, точка отката
  packages/ceny-mesta.csv   — все места, где живёт цена каждой марки
  packages/ceny-zamena.csv  — журнал замен: страница, место, было -> стало
  packages/ceny-01.json     — пакет для rzpub.php (replace + meta)

Ничего не пишет на сайт: только считает и складывает файлы.
"""
import json, re, csv, sys, os, collections

REPO = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
DUMP = sys.argv[1] if len(sys.argv) > 1 else '/srv/regiongbi/tmp/w019/dump.json'
PRICES = os.path.join(REPO, 'packages', 'ceny-prays-2026-06.csv')
OUT = os.path.join(REPO, 'packages')

NBSP = ' '


def fmt(n, sep=' '):
    """8420 -> «8 420» тем же разделителем разрядов, что стоял в исходном месте."""
    s = str(int(n))
    out = ''
    while len(s) > 3:
        out = sep + s[-3:] + out
        s = s[:-3]
    return s + out


def num(s):
    """«8 420 ₽» -> 8420; None, если числа нет."""
    if s is None:
        return None
    m = re.search(r'\d[\d\s' + NBSP + r' ]*', s)
    if not m:
        return None
    d = re.sub(r'\D', '', m.group(0))
    return int(d) if d else None


pages = json.load(open(DUMP, encoding='utf-8'))
by_slug = {p['slug']: p for p in pages}
by_path = {p['path']: p for p in pages}
rows = list(csv.DictReader(open(PRICES, encoding='utf-8'), delimiter=';'))

new_price = {r['slug']: int(float(r['cena_prays_2026_06'])) for r in rows}
razdel = {r['slug']: r['razdel'] for r in rows}
marka_prays = {r['slug']: r['marka_v_prayse'] for r in rows}

# ---------------------------------------------------------------- места цены
CELL = re.compile(r'(<td>\s*Цена\s*</td>\s*<td>\s*)([\d][\d\s' + NBSP + r']*)(\s*₽[^<]*</td>)')
TABLE = re.compile(r'<table>.*?</table>', re.S)
TR = re.compile(r'<tr>.*?</tr>', re.S)
TH = re.compile(r'<th>(.*?)</th>', re.S)
TD = re.compile(r'<td>(.*?)</td>', re.S)
HREF = re.compile(r'href="(/catalog/[^"]+/)"')


def strip_tags(s):
    return re.sub(r'<[^>]+>', '', s).strip()


def table_rows_with_price(content):
    """[(таблица, строка, слаг карточки, индекс колонки цены, текст ячейки цены)]"""
    out = []
    for t in TABLE.finditer(content):
        tbl = t.group(0)
        heads = [strip_tags(h) for h in TH.findall(tbl)]
        pi = None
        for i, h in enumerate(heads):
            if 'Цена' in h:
                pi = i
        if pi is None:
            continue
        for r in TR.finditer(tbl):
            row = r.group(0)
            cells = TD.findall(row)
            if len(cells) <= pi:
                continue
            a = HREF.search(cells[0])
            if not a:
                continue
            slug = a.group(1).rstrip('/').rsplit('/', 1)[-1]
            out.append((row, slug, pi, cells[pi]))
    return out


# карта: слаг карточки -> список (страница, вид места, текущее значение)
mesta = collections.defaultdict(list)

for p in pages:
    c = p['content']
    # 1. ячейка «Цена» в таблице характеристик самой карточки
    m = CELL.search(c)
    if m and p['slug'] in new_price:
        mesta[p['slug']].append((p['slug'], 'tekst-kartochki', num(m.group(2))))
    # 2. строки таблиц с ссылкой на карточку
    for row, slug, pi, cellval in table_rows_with_price(c):
        if slug not in new_price:
            continue
        vid = 'tablica-razdela' if p['slug'] == razdel.get(slug) else (
            'tablica-ryadom' if p['slug'] != slug else 'tablica-svoya')
        mesta[slug].append((p['slug'], vid, num(cellval)))

# 3. мета _rz_product и SEO-мета
for p in pages:
    raw = p.get('rz_product') or ''
    if raw:
        try:
            d = json.loads(raw)
        except Exception:
            d = None
        if isinstance(d, dict):
            if p['slug'] in new_price and d.get('price'):
                mesta[p['slug']].append((p['slug'], 'meta-rz-product', int(d['price'])))
            for it in d.get('items') or []:
                pass  # ниже, при сборке пакета
    for fld, vid in (('rm_title', 'rank-math-title'), ('rm_desc', 'rank-math-description')):
        v = p.get(fld) or ''
        if '₽' not in v:
            continue
        for m in re.finditer(r'([\d][\d\s' + NBSP + r']*)\s*₽', v):
            if p['slug'] in new_price:
                mesta[p['slug']].append((p['slug'], vid, num(m.group(1))))

# ------------------------------------------------------------- ceny-do.csv
with open(os.path.join(OUT, 'ceny-do.csv'), 'w', encoding='utf-8', newline='') as f:
    w = csv.writer(f, delimiter=';')
    w.writerow(['slug', 'ID', 'cena_v_mete', 'cena_v_tekste', 'cena_v_tablice_razdela',
                'cena_prays_2026_06', 'mest_vsego'])
    for r in rows:
        s = r['slug']
        p = by_slug.get(s)
        mm = mesta.get(s, [])
        def one(vid):
            v = [x[2] for x in mm if x[1] == vid]
            return v[0] if v else ''
        w.writerow([s, p['id'] if p else '', one('meta-rz-product'), one('tekst-kartochki'),
                    one('tablica-razdela'), new_price[s], len(mm)])

# ---------------------------------------------------------- ceny-mesta.csv
with open(os.path.join(OUT, 'ceny-mesta.csv'), 'w', encoding='utf-8', newline='') as f:
    w = csv.writer(f, delimiter=';')
    w.writerow(['slug_marki', 'stranica', 'vid_mesta', 'cena_seychas', 'cena_prays'])
    for s in sorted(mesta):
        for stranica, vid, val in mesta[s]:
            w.writerow([s, stranica, vid, val if val is not None else '', new_price[s]])

print('марок в прайсе:', len(rows))
print('мест, где живёт цена:', sum(len(v) for v in mesta.values()))
vidy = collections.Counter(x[1] for v in mesta.values() for x in v)
for k, v in vidy.most_common():
    print('   ', k, v)
sovpadaet = [s for s in new_price if all(x[2] == new_price[s] for x in mesta[s])]
print('марок, где везде уже прайсовая цена:', len(sovpadaet))
raznoboy = [s for s in new_price if len({x[2] for x in mesta[s]}) > 1]
print('марок, где цена на сайте расходится сама с собой:', len(raznoboy), raznoboy[:20])
