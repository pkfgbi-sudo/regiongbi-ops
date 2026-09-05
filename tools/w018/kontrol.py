# -*- coding: utf-8 -*-
"""Контрольный счёт: та же доля повторов, но БЕЗ вычёркивания марок и чисел.
Нужен, чтобы отделить «страницы отличаются только числами» от «страницы
совпадают дословно»."""
import io, json, re
from collections import defaultdict
_ish = io.open('/srv/regiongbi/tmp/w018/schitat.py', encoding='utf-8').read().split("stroki = []")[0]
_ish = "\n".join(l for l in _ish.split("\n") if not l.startswith("d = json.load"))
exec(_ish)

def predl_doslovno(s):
    out = []
    for kusok in KONEC.split(s):
        for ch in re.split(r'\s*[\n\r]+\s*', kusok):
            t = ch.lower()
            t = re.sub(r'[^0-9a-zа-яё×  ]+', ' ', t)
            t = re.sub(r'\s+', ' ', t).strip()
            if len(SLOVO.findall(t)) >= 3:
                out.append(t)
    return out

d = json.load(io.open('/srv/regiongbi/tmp/w018/pages.json', encoding='utf-8'))
for imya, funk in (('с вычеркнутыми марками и числами', predlozheniya),
                   ('дословно, как написано', predl_doslovno)):
    predl = defaultdict(set); po = {}
    for p in d:
        u = set(funk(text(TABLE.sub(' ', p['content']))))
        po[p['slug']] = u
        for s in u: predl[s].add(p['slug'])
    doli = []
    for slug, u in po.items():
        doli.append(100.0 * sum(1 for s in u if len(predl[s]) >= 2) / len(u) if u else 0.0)
    doli.sort()
    print('%-36s средняя доля повторов %5.1f%%, медиана %5.1f%%, страниц со 100%%: %d' % (
        imya, sum(doli)/len(doli), doli[len(doli)//2], sum(1 for x in doli if x >= 99.9)))
