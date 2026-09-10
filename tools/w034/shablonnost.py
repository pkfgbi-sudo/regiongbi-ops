#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Задание 034. Мерка шаблонности — та же, что в задании 027: предложения длиннее
40 знаков, которые встречаются ещё где-то на сайте.

Считает две величины по шести карточкам партии:
  A. предложений, общих хотя бы двум карточкам из шести;
  B. предложений, встречающихся и на других страницах сайта, — «N из M».

Вход: снимок корпуса сайта (все опубликованные страницы и записи без тегов),
      снятый tools/w034/dump-perepis-02.php.

Запуск: python3 tools/w034/shablonnost.py <korpus.json> [подпись]
"""
import json, re, sys, collections

SLUGS = ['ks-7-3', 'ks-7-5', 'ks-15-5', 'ks-20-10', 'pp-10-2', 'pp-20-2']

def predlozheniya(t):
    t = re.sub(r'\s+', ' ', t)
    out = []
    for p in re.split(r'(?<=[.!?])\s+', t):
        p = p.strip()
        if len(p) > 40:
            out.append(p)
    return out

korpus = json.load(open(sys.argv[1], encoding='utf-8'))['korpus']
podpis = sys.argv[2] if len(sys.argv) > 2 else sys.argv[1]

svoi = {s: predlozheniya(korpus[s]) for s in SLUGS}
chuzhie = collections.Counter()
for slug, t in korpus.items():
    if slug in SLUGS:
        continue
    for p in predlozheniya(t):
        chuzhie[p] += 1

vsego = sum(len(v) for v in svoi.values())
gde = collections.defaultdict(set)
for s, ps in svoi.items():
    for p in ps:
        gde[p].add(s)

obshchie = [p for p, s in gde.items() if len(s) >= 2]
na_storone = [p for s in SLUGS for p in svoi[s] if chuzhie[p]]

print('=== шаблонность: %s' % podpis)
print('  предложений длиннее 40 знаков в шести карточках: %d' % vsego)
print('  из них общих хотя бы двум карточкам из шести:     %d' % sum(len(gde[p]) for p in obshchie))
print('  из них встречается и на других страницах сайта:   %d из %d' % (len(na_storone), vsego))
for s in SLUGS:
    n = sum(1 for p in svoi[s] if chuzhie[p])
    print('     %-9s %2d из %2d' % (s, n, len(svoi[s])))
if na_storone:
    print('  повторы (первые 12):')
    for p in sorted(set(na_storone))[:12]:
        print('    · %s' % (p[:110] + ('…' if len(p) > 110 else '')))
