#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Задание 035. Вытащить таблицу марок из ЖИВОЙ страницы раздела колец.

  curl ... https://regiongbi.ru/catalog/koltsa-kolodeznye-ks/ > razdel.html
  python3 tools/w035/izvlech-tablicu.py razdel.html > tools/w035/tablica-razdela-ks.json

Нужна затем, чтобы сверка чисел (sverka-035.py) опиралась на то, что стоит на
сайте сейчас, а не на выгрузку задания 034. Ничего не пишет на сайт.
"""
import html, json, re, sys

h = open(sys.argv[1], encoding='utf-8', errors='replace').read()

def bez_tegov(s):
    return html.unescape(re.sub(r'<[^>]+>', '', s)).replace(' ', ' ').replace(' ', ' ').strip()

out = {}
for row in re.findall(r'<tr[^>]*>(.*?)</tr>', h, re.S):
    cells = [bez_tegov(c) for c in re.findall(r'<t[dh][^>]*>(.*?)</t[dh]>', row, re.S)]
    if len(cells) < 4 or not re.match(r'^(КС|ПП|ПН|ПК)\s', cells[0]):
        continue
    out[cells[0]] = {'razmery': cells[1], 'massa': cells[2], 'cena': cells[3]}

print(json.dumps({'istochnik': 'https://regiongbi.ru/catalog/koltsa-kolodeznye-ks/',
                  'marok': len(out), 'marki': out}, ensure_ascii=False, indent=1))
