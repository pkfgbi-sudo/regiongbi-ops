#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Задание 024, сверка пакета kollektory-01 с таблицей раздела
/catalog/elementy-kollektorov/. Две правки текста; цифры характеристик,
цены, заголовки и мета не трогаются.

Приём тот же, что в tools/w023/pravki-vp-03.py: каждое «было» обязано
встретиться в содержимом карточки ровно один раз, иначе правка не
применяется и скрипт падает.

Запуск: python3 tools/w024/pravki-kollektory-01.py
"""
import json, os

REPO = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
PKG = os.path.join(REPO, 'packages', 'kollektory-01.json')

PRAVKI = [
    # (слаг, было, стало, основание)
    ('kd-36',
     'Элементы стыкуются строго по типоразмеру: у КД-30 и КД-36 разная ширина, стеновые блоки на них не сядут.',
     'Элементы стыкуются строго по типоразмеру: у КД-30 длина 1600 мм, у КД-36 — 2200 мм, и раскладка секции не сойдётся.',
     'в таблице раздела ширина у обеих марок одна и та же — 2080 мм; различается длина (1600 против 2200)'),
    ('db-21',
     'у тяжёлых массa от 1950 до 3240 кг, то есть в шесть-десять раз больше',
     'у тяжёлых масса от 1950 до 3240 кг, то есть в четыре-десять раз больше',
     'лёгкая группа 330–510 кг против 1950–3240 у тяжёлой — это 3,8…9,8 раза, а не 6…10; '
     'заодно исправлена латинская «a» в слове «масса»'),
]

pkg = json.load(open(PKG, encoding='utf-8'))
items = {i['slug']: i for i in pkg['items']}

for slug, bylo, stalo, why in PRAVKI:
    it = items[slug]
    n = it['content'].count(bylo)
    assert n == 1, 'у %s «%s» встречается %d раз' % (slug, bylo[:40], n)
    it['content'] = it['content'].replace(bylo, stalo)
    print('%-8s %s' % (slug, why))

with open(PKG, 'w', encoding='utf-8') as f:
    json.dump(pkg, f, ensure_ascii=False, indent=2)
    f.write('\n')
print('правок внесено:', len(PRAVKI))
