#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Задание 035. Пересчёт чисел двух исправленных карточек (КС 7-3, КС 15-5)
по живой таблице раздела колец. Ничего не пишет — только считает.

Вход: tools/w035/tablica-razdela-ks.json (снята с живой страницы раздела),
      packages/perepis-02.json.

Проверяется:
  1. габариты, массы и цены всех марок рядов Ø700 и Ø1500 — по таблице раздела;
  2. каждая строка «цена → метр шахты» в текстах двух карточек — пересчётом;
  3. новые производные числа задания: 7 400, 4 933, 830, 394, «в 1,75 раза»,
     «вчетверо», «от 230 кг», массы двух младших доборных;
  4. отсутствие снятых утверждений («единственное», «любого другого кольца»);
  5. число строк в таблице ряда Ø700 (ждём семь марок).

Запуск: python3 tools/w035/sverka-035.py
"""
import json, os, re, sys

REPO = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
T = json.load(open(os.path.join(REPO, 'tools/w035/tablica-razdela-ks.json'), encoding='utf-8'))['marki']
pkg = json.load(open(os.path.join(REPO, 'packages/perepis-02.json'), encoding='utf-8'))
S = {i['slug']: i['content'] for i in pkg['items']}

oshibok = 0
def nbsp(s):
    return s.replace(' ', ' ').replace(' ', ' ')
def chislo(s):
    return int(re.sub(r'\D', '', s))
def vysota(m):
    return chislo(T[m]['razmery'].split('×')[-1])
def metr(m):
    return round(chislo(T[m]['cena']) / vysota(m) * 1000)
def proverit(chto, bylo, zhdem, dopusk=0):
    global oshibok
    ok = abs(bylo - zhdem) <= dopusk
    if not ok: oshibok += 1
    print('  %-56s %10s  %s' % (chto, round(bylo, 3), 'ок' if ok else 'НЕ СХОДИТСЯ, в тексте %s' % zhdem))
def est(chto, slug, stroka, dolzhno=True):
    global oshibok
    nashlos = nbsp(stroka) in nbsp(S[slug])
    ok = nashlos == dolzhno
    if not ok: oshibok += 1
    print('  %-56s %10s  %s' % (chto, 'есть' if nashlos else 'нет', 'ок' if ok else 'НЕ ТАК'))

print('=== 1. ряды Ø700 и Ø1500 по живой таблице раздела')
for m, v in T.items():
    if m.startswith('КС 7-') or m.startswith('КС 15-'):
        print('  %-9s %-14s %5s кг %8s ₽   метр %5d ₽' % (m, v['razmery'], v['massa'], v['cena'], metr(m)))

print()
print('=== 2. строки «цена → метр» в текстах двух карточек')
for slug in ('ks-7-3', 'ks-15-5'):
    for marka, vys, cena, m in re.findall(
            r'<td>(КС [\d\-,]+) — (\d+) мм</td><td>([\d ]+) ₽ → метр(?: шахты)? ([\d ]+) ₽</td>', nbsp(S[slug])):
        v_razdele = (vysota(marka), chislo(T[marka]['cena']), metr(marka))
        v_tekste = (int(vys), chislo(cena), chislo(m))
        ok = v_razdele == v_tekste
        if not ok: oshibok += 1
        print('  %-8s %-9s текст %s  раздел+пересчёт %s  %s'
              % (slug, marka, v_tekste, v_razdele, 'ок' if ok else 'НЕ СХОДИТСЯ'))

print()
print('=== 3. новые числа задания')
proverit('КС 7-0,1: 740 / 100 × 1000 («7 400»)', metr('КС 7-0,1'), 7400)
proverit('КС 7-1,5: 740 / 150 × 1000 («4 933»)', metr('КС 7-1,5'), 4933)
proverit('КС 15-5 − КС 15-6 («на 830 ₽ дешевле»)', metr('КС 15-5') - metr('КС 15-6'), 830)
proverit('КС 15-6 − КС 15-9 («на 394 ₽ дороже»)', metr('КС 15-6') - metr('КС 15-9'), 394)
proverit('КС 7-3 / КС 7-10 («в 1,75 раза»)', metr('КС 7-3') / metr('КС 7-10'), 1.75, 0.005)
proverit('КС 7-0,1 / КС 7-10 («вчетверо дороже старших»)', metr('КС 7-0,1') / metr('КС 7-10'), 4, 0.25)
proverit('самое лёгкое из рабочих колец ряда («от 230 кг»)',
         min(chislo(T[m]['massa']) for m in T if m.startswith('КС 7-') and vysota(m) >= 290 and m != 'КС 7-3'), 230)
proverit('КС 7-1,5 масса («70 кг»)', chislo(T['КС 7-1,5']['massa']), 70)
proverit('КС 7-0,1 масса («46 кг»)', chislo(T['КС 7-0,1']['massa']), 46)
proverit('КС 7-3 масса («130 кг»)', chislo(T['КС 7-3']['massa']), 130)
proverit('КС 7-3 в машину («около 153 штук»), тонн', 153 * chislo(T['КС 7-3']['massa']) / 1000, 20, 0.5)

print()
print('=== 4. снятые и добавленные утверждения')
est('ks-7-3: слова «единственное» нет', 'ks-7-3', 'динственное', False)
est('ks-7-3: «больше, чем любого другого» нет', 'ks-7-3', 'любого другого', False)
est('ks-7-3: h2 «Самое лёгкое из рабочих колец ряда»', 'ks-7-3',
    '<h2>Самое лёгкое из рабочих колец ряда</h2>')
est('ks-7-3: «Остальные рабочие кольца ряда — от 230 кг»', 'ks-7-3',
    'Остальные рабочие кольца ряда — от 230 кг')
est('ks-7-3: КС 7-1,5 (150 мм, 70 кг) в тексте', 'ks-7-3', 'КС 7-1,5 (150 мм, 70 кг)')
est('ks-7-3: КС 7-0,1 (100 мм, 46 кг) в тексте', 'ks-7-3', 'КС 7-0,1 (100 мм, 46 кг)')
est('ks-7-3: «около 153 штук.» без превосходной степени', 'ks-7-3', 'около 153 штук.')
est('ks-15-5: «сотню» нет', 'ks-15-5', 'сотню', False)
est('ks-15-5: «на 830 ₽ дешевле, чем у КС 15-5»', 'ks-15-5', 'на 830 ₽ дешевле, чем у КС 15-5')
est('ks-15-5: «на 394 ₽ дороже, чем у КС 15-9»', 'ks-15-5', 'на 394 ₽ дороже, чем у КС 15-9')

print()
print('=== 5. таблица ряда Ø700 в карточке КС 7-3')
marki_v_tablice = re.findall(r'<td>(КС 7-[\d\-,]+) — \d+ мм</td>', nbsp(S['ks-7-3']))
marki_v_razdele = [m for m in T if m.startswith('КС 7-')]
print('  в карточке: %d — %s' % (len(marki_v_tablice), ', '.join(marki_v_tablice)))
print('  в разделе:  %d — %s' % (len(marki_v_razdele), ', '.join(marki_v_razdele)))
if marki_v_tablice != marki_v_razdele:
    oshibok += 1
    print('  СОСТАВ НЕ СОВПАДАЕТ')
else:
    print('  состав совпал, порядок тот же')

print()
print('=== 6. остальные четыре позиции пакета не менялись с задания 034')
print('  (сверяется прогоном deploy.sh: они уже на сайте, разбор покажет обновление)')
for s in ('ks-7-5', 'ks-20-10', 'pp-10-2', 'pp-20-2'):
    print('  %-9s знаков %d' % (s, len(S[s])))

print()
print('ошибок сверки:', oshibok)
sys.exit(1 if oshibok else 0)
