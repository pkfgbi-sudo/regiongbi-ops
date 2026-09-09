#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Задание 029, пункт 5. Сверка всех цен в 22 записях блога с ценами на карточках.
Только чтение: печатает таблицу и пишет packages/ceny-blog-029.csv, ничего не правит.

Источник текущих цен — мета _rz_product карточек (её же читает товарная разметка).
Место цены в блоге — либо ячейка таблицы со столбцом «Цена», либо число перед ₽
в прозе; марка берётся из первой ячейки строки или из ближайшего слева обозначения.

Запуск: python3 tools/w029/sverit-ceny-bloga.py [dump.json]
"""
import csv, html, json, os, re, sys

REPO = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
DUMP = sys.argv[1] if len(sys.argv) > 1 else '/srv/regiongbi/tmp/w029/w029-do.json'

MARKA = re.compile(r'([А-ЯЁ]{2,4}[рp]?[\s -]?\d+(?:[-,.]\d+)*(?:\s?ч)?)')

def bez_tegov(s):
    return re.sub(r'\s+', ' ', re.sub(r'<[^>]+>', ' ', html.unescape(s))).strip()

def klyuch(s):
    s = re.sub(r'[\s ]+', ' ', s.replace('—', '-').replace('–', '-')).upper().strip()
    return re.sub(r'\s*Ч$', '', s)

def chislo(s):
    s = re.sub(r'[\s ₽]', '', bez_tegov(s))
    m = re.match(r'^(\d+)$', s)
    return int(m.group(1)) if m else None

d = json.load(open(DUMP, encoding='utf-8'))

# текущие цены: марка -> (цена, путь карточки)
tek = {}
for p in d['pages']:
    raw = p.get('rz_product') or ''
    if not raw:
        continue
    try:
        x = json.loads(raw)
    except Exception:
        continue
    if x.get('type') != 'single' or not x.get('price'):
        continue
    sku = (x.get('sku') or '').strip()
    if sku:
        tek[klyuch(sku)] = (int(x['price']), p['path'])

mesta = []
for post in d['posts']:
    c = post['content']

    for t in re.findall(r'<table.*?</table>', c, re.S):
        heads = [klyuch(bez_tegov(h)) for h in re.findall(r'<th[^>]*>(.*?)</th>', t, re.S)]
        stolb = [i for i, h in enumerate(heads) if 'ЦЕНА' in h]
        if not stolb:
            continue
        ci = stolb[0]
        for row in re.findall(r'<tr[^>]*>(.*?)</tr>', t, re.S):
            cells = re.findall(r'<td[^>]*>(.*?)</td>', row, re.S)
            if len(cells) <= ci:
                continue
            cena = chislo(cells[ci])
            if cena is None:
                continue
            m = MARKA.search(bez_tegov(cells[0]))
            mesta.append((post['slug'], post['id'], 'таблица',
                          klyuch(m.group(1)) if m else bez_tegov(cells[0])[:24], cena))

    proza = re.sub(r'<table.*?</table>', ' ', c, flags=re.S)
    proza = bez_tegov(proza)
    for m in re.finditer(r'(\d[\d\s ]{2,8})\s*₽', proza):
        cena = int(re.sub(r'\D', '', m.group(1)))
        levo = proza[max(0, m.start() - 90):m.start()]
        mm = list(MARKA.finditer(levo))
        mesta.append((post['slug'], post['id'], 'проза',
                      klyuch(mm[-1].group(1)) if mm else '?', cena))

sovpalo, razoshlos, ne_sverit = [], [], []
for slug, pid, gde, marka, cena in mesta:
    if marka in tek:
        (sovpalo if tek[marka][0] == cena else razoshlos).append((slug, pid, gde, marka, cena, tek[marka][0], tek[marka][1]))
    else:
        ne_sverit.append((slug, pid, gde, marka, cena))

out = os.path.join(REPO, 'packages', 'ceny-blog-029.csv')
with open(out, 'w', encoding='utf-8', newline='') as f:
    w = csv.writer(f, delimiter=';')
    w.writerow(['zapis', 'id', 'gde', 'marka', 'v_bloge', 'na_sayte', 'kartochka', 'itog'])
    for r in sorted(razoshlos):
        w.writerow([r[0], r[1], r[2], r[3], r[4], r[5], r[6], 'РАСХОЖДЕНИЕ'])
    for r in sorted(sovpalo):
        w.writerow([r[0], r[1], r[2], r[3], r[4], r[5], r[6], 'совпало'])
    for r in sorted(ne_sverit):
        w.writerow([r[0], r[1], r[2], r[3], r[4], '', '', 'не с чем сверить'])

print('мест с ценой в блоге: %d' % len(mesta))
print('  совпало с карточкой: %d' % len(sovpalo))
print('  РАСХОЖДЕНИЙ: %d' % len(razoshlos))
print('  не с чем сверить (марки нет среди карточек с ценой): %d, марок %d'
      % (len(ne_sverit), len(set(x[3] for x in ne_sverit))))
print()
for r in sorted(razoshlos):
    print('  %-42s #%-5s %-8s %-10s блог %-7d сайт %-7d %s' % r)
print()
print('марки без карточки с ценой:', ', '.join(sorted(set(x[3] for x in ne_sverit))))
print('->', out)
