#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Задание 034. Сверка чисел пакета perepis-02 с таблицами разделов и пересчёт
всех производных величин. Ничего не пишет — только считает и печатает.

Вход: tools/w034/vygruzka-do.json — выгрузка с Бегета (dump-perepis-02.php),
      packages/perepis-02.json.

Проверяется:
  1. габариты, массы и цены марок — по таблицам разделов КС, ПП и ПД;
  2. все «цены метра шахты» — пересчётом: цена / высота * 1000, до рубля;
  3. производные утверждения (разницы, проценты, «в N раз»);
  4. загрузка машины: заявленное число штук * масса ≈ 20 т;
  5. утверждения-превосходные степени против полного состава ряда.

Запуск: python3 tools/w034/sverka-chisel.py
"""
import json, os, re, sys

REPO = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
d = json.load(open(os.path.join(REPO, 'tools', 'w034', 'vygruzka-do.json'), encoding='utf-8'))
pkg = json.load(open(os.path.join(REPO, 'packages', 'perepis-02.json'), encoding='utf-8'))
soderzhimoe = {i['slug']: i['content'] for i in pkg['items']}

def nbsp(s):
    return s.replace(' ', ' ').replace(' ', ' ')

def tablica(slug_razdela):
    """Строки таблицы марок раздела: марка -> (размеры, масса, цена)."""
    c = d['razdely'][slug_razdela]['content']
    out = {}
    for t in re.findall(r'<table.*?</table>', c, re.S):
        for r in re.findall(r'<tr.*?</tr>', t, re.S):
            cells = [nbsp(re.sub(r'<[^>]+>', '', x)).strip()
                     for x in re.findall(r'<t[dh][^>]*>(.*?)</t[dh]>', r, re.S)]
            if len(cells) < 4 or cells[0] in ('Марка', ''):
                continue
            out[cells[0]] = (cells[1], cells[2], cells[3])
    return out

KS = tablica('koltsa-kolodeznye-ks')
PP = tablica('kryshki-kolodtsev-pp')
PD = tablica('dnishcha-kolodtsev-pd')

oshibok = 0
def sverit(chto, bylo, zhdem):
    global oshibok
    ok = str(bylo) == str(zhdem)
    if not ok:
        oshibok += 1
    print('  %-58s %-16s %s' % (chto, bylo, 'ок' if ok else 'НЕ СХОДИТСЯ, в разделе: %s' % zhdem))

def chislo(s):
    return int(re.sub(r'\D', '', s))

print('=== 1. марки по таблицам разделов')
for marka, (razm, massa, cena) in (
        ('КС 7-3', KS['КС 7-3']), ('КС 7-5', KS['КС 7-5']), ('КС 15-5', KS['КС 15-5']),
        ('КС 20-10', KS['КС 20-10']), ('ПП 10-2', PP['ПП 10-2']), ('ПП 20-2', PP['ПП 20-2']),
        ('ПП 10-1', PP['ПП 10-1']), ('ПП 20-1', PP['ПП 20-1']),
        ('ПН 7', PD['ПН 7']), ('ПН 15', PD['ПН 15']), ('ПН 20', PD['ПН 20']),
        ('КС 7-6', KS['КС 7-6']), ('КС 7-9', KS['КС 7-9']), ('КС 7-10', KS['КС 7-10']),
        ('КС 15-3', KS['КС 15-3']), ('КС 15-6', KS['КС 15-6']),
        ('КС 15-9', KS['КС 15-9']), ('КС 15-10', KS['КС 15-10']),
        ('КС 20-6', KS['КС 20-6']), ('КС 20-9', KS['КС 20-9'])):
    print('  %-9s %-18s %6s кг  %9s ₽' % (marka, razm, massa, cena))

print()
print('=== 2. цена метра шахты: цена / высота * 1000, до рубля')
VYSOTY = {'КС 7-3': 290, 'КС 7-5': 490, 'КС 7-6': 590, 'КС 7-9': 890, 'КС 7-10': 990,
          'КС 15-3': 290, 'КС 15-5': 490, 'КС 15-6': 590, 'КС 15-9': 890, 'КС 15-10': 990,
          'КС 20-6': 590, 'КС 20-9': 890, 'КС 20-10': 990}
METR = {}
for m, h in VYSOTY.items():
    razm, massa, cena = KS[m]
    v_tablice = chislo(razm.split('×')[-1])
    if v_tablice != h:
        print('  %-9s ВЫСОТА В РАЗДЕЛЕ %d, ждали %d' % (m, v_tablice, h)); oshibok += 1
    METR[m] = round(chislo(cena) / h * 1000)
    print('  %-9s %5d ₽ / %3d мм = %5d ₽ за метр' % (m, chislo(cena), h, METR[m]))

print()
print('=== 3. эти же числа в текстах пакета')
for m, v in METR.items():
    zapis = '{:,}'.format(v).replace(',', ' ')
    gde = [s for s, c in soderzhimoe.items() if zapis in nbsp(c)]
    print('  %-9s %-8s встречается в: %s' % (m, zapis, ', '.join(gde) if gde else '—'))

print()
print('=== 4. производные утверждения')
def utv(tekst, znachenie, zhdem, dopusk=0):
    global oshibok
    ok = abs(znachenie - zhdem) <= dopusk
    if not ok: oshibok += 1
    print('  %-56s %10s  %s' % (tekst, round(znachenie, 2), 'ок' if ok else 'НЕ СХОДИТСЯ, в тексте %s' % zhdem))

utv('КС 7-3 / КС 7-10 (в тексте «в 1,75 раза»)', METR['КС 7-3'] / METR['КС 7-10'], 1.75, 0.005)
utv('КС 7-5 − КС 7-10 (в тексте «561 ₽ на метр»)', METR['КС 7-5'] - METR['КС 7-10'], 561)
utv('то же на трёх метрах (в тексте «1 700 ₽»)', (METR['КС 7-5'] - METR['КС 7-10']) * 3, 1700, 20)
utv('то же на десяти колодцах («семнадцать тысяч»)', (METR['КС 7-5'] - METR['КС 7-10']) * 30, 17000, 200)
utv('КС 15-5 − КС 15-10 («1 393 ₽ на метре»)', METR['КС 15-5'] - METR['КС 15-10'], 1393)
utv('доля от КС 15-10 («почти треть»)', (METR['КС 15-5'] - METR['КС 15-10']) / METR['КС 15-10'], 1 / 3, 0.04)
utv('на трёх метрах («четыре тысячи»)', (METR['КС 15-5'] - METR['КС 15-10']) * 3, 4000, 200)
utv('КС 15-6 − КС 15-9 («всего на сотню дороже»)', METR['КС 15-6'] - METR['КС 15-9'], 100, 30)
utv('КС 20-10 − КС 20-9 («на 127 ₽»)', METR['КС 20-10'] - METR['КС 20-9'], 127)
utv('ПП 10-2 − ПП 10-1 («1 014 ₽»)', chislo(PP['ПП 10-2'][2]) - chislo(PP['ПП 10-1'][2]), 1014)
utv('он же в процентах («69 %»)', (chislo(PP['ПП 10-2'][2]) / chislo(PP['ПП 10-1'][2]) - 1) * 100, 69, 0.5)
utv('ПП 20-2 − ПП 20-1 («2 590 ₽»)', chislo(PP['ПП 20-2'][2]) - chislo(PP['ПП 20-1'][2]), 2590)
utv('он же в процентах («44 %»)', (chislo(PP['ПП 20-2'][2]) / chislo(PP['ПП 20-1'][2]) - 1) * 100, 44, 0.5)
utv('ПП 10-2 − ПП 10-1 по массе («пятьдесят кг»)', chislo(PP['ПП 10-2'][1]) - chislo(PP['ПП 10-1'][1]), 50)
utv('ПП 20-2 − ПП 20-1 по массе («трёхстах кг»)', chislo(PP['ПП 20-2'][1]) - chislo(PP['ПП 20-1'][1]), 300)

print()
print('=== 5. загрузка машины: штук × масса, тонн')
for marka, sht in (('КС 7-3', 153), ('КС 7-5', 86), ('КС 15-5', 31), ('КС 20-10', 12)):
    print('  %-9s %3d шт × %4d кг = %5.1f т' % (marka, sht, chislo(KS[marka][1]), sht * chislo(KS[marka][1]) / 1000))
for marka, sht in (('ПП 10-2', 80), ('ПП 20-2', 14), ('ПП 15-2', 28)):
    print('  %-9s %3d шт × %4d кг = %5.1f т' % (marka, sht, chislo(PP[marka][1]), sht * chislo(PP[marka][1]) / 1000))

print()
print('=== 6. полный состав ряда Ø700 (для утверждений «единственное», «любого другого»)')
for m, (razm, massa, cena) in KS.items():
    if m.startswith('КС 7-'):
        print('  %-9s %-18s %6s кг  %9s ₽' % (m, razm, massa, cena))

print()
print('ошибок сверки:', oshibok)
sys.exit(0)
