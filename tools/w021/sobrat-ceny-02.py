#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
sobrat-ceny-02.py — сборка пакета ceny-02 (задание 021):
четыре марки поднимаются до фактических отгрузочных цен.

Замена делается не регуляркой по числу, а по якорю:
  * в таблицах — только внутри той строки <tr>, где стоит сама марка
    (ссылка на её карточку либо, если карточки нет, её обозначение);
  * на карточке — строка «Цена | N ₽ с НДС за штуку» и мета той же страницы.
Так «1 810» у ФБС 9-4-6 и КС 7-9 и «10 190» у ККСр-2 остаются нетронутыми:
это чужие товары с той же ценой.

Запуск:  python3 tools/w021/sobrat-ceny-02.py <dump.json> <packages/ceny-02.json>
"""
import json, re, sys

MARKI = [
    # slug карточки, ID, марка, раздел, было, стало
    dict(slug='pp-20-2', id=804, marka='ПП 20-2', razdel='kryshki-kolodtsev-pp',
         bylo='6 810', stalo='8 420', bylo_n=6810, stalo_n=8420),
    dict(slug='pp-10-2', id=799, marka='ПП 10-2', razdel='kryshki-kolodtsev-pp',
         bylo='1 810', stalo='2 484', bylo_n=1810, stalo_n=2484),
    dict(slug='pk-15', id=820, marka='ПК 15', razdel='kryshki-kolodtsev-pp',
         bylo='6 710', stalo='7 265', bylo_n=6710, stalo_n=7265),
    # КУ-25 карточки не имеет: живёт только строкой в таблице раздела
    dict(slug=None, id=None, marka='КУ-25', razdel='elementy-kollektorov',
         bylo='10 190', stalo='10 504', bylo_n=10190, stalo_n=10504),
]

ROW = re.compile(r'<tr>.*?</tr>', re.S)


def yakor(m):
    """Чем опознаётся строка таблицы, относящаяся к этой марке."""
    if m['slug']:
        return '<a href="/catalog/%s/%s/">%s</a>' % (m['razdel'], m['slug'], m['marka'])
    return '<td>%s</td>' % m['marka']


def pravit_tablicy(content, m, zhurnal, stranica):
    """Заменить цену только в строках <tr>, помеченных якорем марки."""
    ank = yakor(m)
    staraya = '<td>%s</td>' % m['bylo']
    novaya = '<td>%s</td>' % m['stalo']

    def po_stroke(mo):
        s = mo.group(0)
        if ank not in s:
            return s
        if staraya not in s:
            zhurnal.append(('ВНИМАНИЕ', stranica, m['marka'], 'строка марки есть, старой цены в ней нет'))
            return s
        n = s.count(staraya)
        if n != 1:
            zhurnal.append(('ВНИМАНИЕ', stranica, m['marka'], 'в строке %d ячеек со старой ценой' % n))
            return s
        zhurnal.append(('tablica', stranica, m['marka'], '%s -> %s' % (m['bylo'], m['stalo'])))
        return s.replace(staraya, novaya)

    return ROW.sub(po_stroke, content)


def main():
    dump_f, out_f = sys.argv[1], sys.argv[2]
    D = json.load(open(dump_f))
    po_id = {p['id']: p for p in D}

    zhurnal = []
    novoe = {}   # id -> {'content':..., 'rm_title':..., 'rm_desc':..., 'rz_product':...}

    def slot(pid):
        return novoe.setdefault(pid, {})

    # --- 1. таблицы: раздел, «Рядом в ряду», любые другие страницы
    for p in D:
        c = c0 = p['content'] or ''
        for m in MARKI:
            c = pravit_tablicy(c, m, zhurnal, p['slug'])
        if c != c0:
            slot(p['id'])['content'] = c

    # --- 2. карточка марки: строка «Цена», мета Rank Math, мета _rz_product
    for m in MARKI:
        if not m['slug']:
            continue
        p = po_id[m['id']]
        assert p['slug'] == m['slug'], 'ID %s это %s, а не %s' % (m['id'], p['slug'], m['slug'])

        c = slot(m['id']).get('content', p['content'])
        cena_row = '<td>Цена</td><td>%s ₽ с НДС за штуку</td>' % m['bylo']
        if c.count(cena_row) != 1:
            zhurnal.append(('ОШИБКА', p['slug'], m['marka'],
                            'строк «Цена %s ₽ с НДС за штуку» на карточке: %d' % (m['bylo'], c.count(cena_row))))
        else:
            c = c.replace(cena_row, '<td>Цена</td><td>%s ₽ с НДС за штуку</td>' % m['stalo'])
            zhurnal.append(('tekst-kartochki', p['slug'], m['marka'], '%s -> %s' % (m['bylo'], m['stalo'])))
        slot(m['id'])['content'] = c

        for pole, kluch in (('rm_title', 'rank_math_title'), ('rm_desc', 'rank_math_description')):
            v = p[pole] or ''
            nado = '%s ₽' % m['bylo']
            if v.count(nado) != 1:
                zhurnal.append(('ОШИБКА', p['slug'], m['marka'],
                                '%s: вхождений «%s»: %d' % (kluch, nado, v.count(nado))))
                continue
            slot(m['id'])[pole] = v.replace(nado, '%s ₽' % m['stalo'])
            zhurnal.append((kluch, p['slug'], m['marka'], '%s -> %s' % (m['bylo'], m['stalo'])))

        raw = p['rz_product'] or ''
        prod = json.loads(raw)
        if prod.get('price') != m['bylo_n']:
            zhurnal.append(('ОШИБКА', p['slug'], m['marka'],
                            '_rz_product.price = %r, ожидалось %d' % (prod.get('price'), m['bylo_n'])))
        else:
            prod['price'] = m['stalo_n']
            opis = prod.get('description', '')
            nado = '%s ₽' % m['bylo']
            if opis.count(nado) != 1:
                zhurnal.append(('ОШИБКА', p['slug'], m['marka'],
                                '_rz_product.description: вхождений «%s»: %d' % (nado, opis.count(nado))))
            else:
                prod['description'] = opis.replace(nado, '%s ₽' % m['stalo'])
            slot(m['id'])['rz_product'] = prod
            zhurnal.append(('meta-rz-product', p['slug'], m['marka'], '%d -> %d' % (m['bylo_n'], m['stalo_n'])))

    # --- 3. пакет
    items = []
    for pid in sorted(novoe):
        p = po_id[pid]
        it = {'url': p['path']}
        n = novoe[pid]
        if 'content' in n:
            it['content'] = n['content']
        if 'rm_title' in n:
            it['rank_math_title'] = n['rm_title']
        if 'rm_desc' in n:
            it['rank_math_description'] = n['rm_desc']
        if 'rz_product' in n:
            it['meta'] = n['rz_product']
        items.append(it)

    json.dump({'package': 'ceny-02', 'items': items},
              open(out_f, 'w'), ensure_ascii=False, indent=1)

    # --- 4. отчёт в stdout
    oshibok = 0
    for vid, stranica, marka, chto in zhurnal:
        if vid in ('ОШИБКА', 'ВНИМАНИЕ'):
            oshibok += 1
        print('%-22s %-30s %-8s %s' % (vid, stranica, marka, chto))
    print('-' * 78)
    print('страниц в пакете: %d, замен: %d, ошибок и предупреждений: %d'
          % (len(items), len(zhurnal) - oshibok, oshibok))

    # --- 5. сумма цен в таблице раздела до и после
    for razdel in ('kryshki-kolodtsev-pp', 'elementy-kollektorov'):
        p = [x for x in D if x['slug'] == razdel][0]
        do = summa_razdela(p['content'])
        posle_c = novoe.get(p['id'], {}).get('content', p['content'])
        posle = summa_razdela(posle_c)
        print('раздел %-24s сумма цен: было %d, стало %d, прибавка %d'
              % (razdel, do, posle, posle - do))
    return 1 if oshibok else 0


def summa_razdela(content):
    """Сумма чисел из последней ячейки каждой строки таблицы марок раздела."""
    i = content.find('<!--rz:marks-->')
    j = content.find('<!--/rz:marks-->')
    kusok = content[i:j] if i >= 0 and j > i else content
    s = 0
    for row in ROW.findall(kusok):
        yach = re.findall(r'<td>(.*?)</td>', row, re.S)
        if not yach:
            continue
        last = re.sub(r'<[^>]+>', '', yach[-1]).replace(' ', '').replace(' ', '').strip()
        if re.fullmatch(r'\d+', last):
            s += int(last)
    return s


if __name__ == '__main__':
    sys.exit(main())
