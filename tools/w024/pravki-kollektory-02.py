#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Задание 025, сверка пакета kollektory-02 с таблицей раздела
/catalog/elementy-kollektorov/. Две правки текста в карточке ДБ-29; цифры
характеристик, цены, заголовки и мета не трогаются.

Приём тот же, что в tools/w024/pravki-kollektory-01.py: каждое «было» обязано
встретиться в содержимом карточки ровно один раз.

Запуск: python3 tools/w024/pravki-kollektory-02.py
"""
import json, os

REPO = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
PKG = os.path.join(REPO, 'packages', 'kollektory-02.json')

PRAVKI = [
    ('db-29',
     'Доборная балка длиной 2900 мм — самая длинная в лёгкой группе ряда.',
     'Доборная балка длиной 2900 мм — предпоследняя марка лёгкой группы ряда.',
     'самая длинная в лёгкой группе — ДБ-34 (3400 мм), она в том же сечении 200×300; '
     'это же противоречило следующему абзацу самой карточки'),
    ('db-29',
     'ДБ-29 — последняя марка сечением 200×300 мм, которую ещё берёт манипулятор. '
     'Следующая по длине ДБ-34 остаётся в том же сечении, а вот ДБ-39 уже идёт '
     '400×500 мм и весит 1950 кг вместо полутонны.',
     'ДБ-29 и следующая за ней ДБ-34 (3400 мм, 510 кг) — ещё сечение 200×300 мм '
     'и масса под манипулятор. А вот ДБ-39 идёт уже сечением 400×500 мм и весит '
     '1950 кг вместо полутонны.',
     'карточка ДБ-21 из пакета kollektory-01 прямо пишет, что манипулятора хватает '
     'на всю лёгкую группу ДБ-21…ДБ-34; два соседних текста не должны отвечать '
     'клиенту про технику по-разному'),
]

pkg = json.load(open(PKG, encoding='utf-8'))
items = {i['slug']: i for i in pkg['items']}

for slug, bylo, stalo, why in PRAVKI:
    it = items[slug]
    n = it['content'].count(bylo)
    assert n == 1, 'у %s «%s» встречается %d раз' % (slug, bylo[:40], n)
    it['content'] = it['content'].replace(bylo, stalo)
    print('%-8s %s' % (slug, why))

# концовку файла сохраняем как была: в kollektory-02.json перевода строки
# в конце нет, и добавлять его — лишняя строка в diff пакета
hvost = '\n' if open(PKG, encoding='utf-8').read().endswith('\n') else ''
with open(PKG, 'w', encoding='utf-8') as f:
    json.dump(pkg, f, ensure_ascii=False, indent=2)
    f.write(hvost)
print('правок внесено:', len(PRAVKI))
