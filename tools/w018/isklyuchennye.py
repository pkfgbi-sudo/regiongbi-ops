# -*- coding: utf-8 -*-
"""Задание 018, пункт 4: исключённые из поиска страницы против среднего.
Корпус — 204 страницы плюс 22 записи блога: три адреса из списка Вебмастера
оказались записями блога, а не страницами."""
import io, json, re
from collections import defaultdict

_ish = io.open('/srv/regiongbi/tmp/w018/schitat.py', encoding='utf-8').read().split("stroki = []")[0]
_ish = "\n".join(l for l in _ish.split("\n") if not l.startswith("d = json.load"))
exec(_ish)   # берём из счётного скрипта только функции и регулярки

stranicy = json.load(io.open('/srv/regiongbi/tmp/w018/pages.json', encoding='utf-8'))
zapisi   = json.load(io.open('/srv/regiongbi/tmp/w018/posts.json', encoding='utf-8'))
for p in stranicy: p['tip'] = 'страница'
for p in zapisi:   p['tip'] = 'запись блога'
korpus = stranicy + zapisi

predl_doc = defaultdict(set)
po_doc = {}
mera = {}
for p in korpus:
    t_vsego = text(p['content'])
    t_bez = text(TABLE.sub(' ', p['content']))
    unik = set(predlozheniya(t_bez))
    po_doc[p['slug']] = unik
    for s in unik: predl_doc[s].add(p['slug'])
    mera[p['slug']] = {'tip': p['tip'], 'slov': slov(t_vsego), 'bez_tablic': slov(t_bez),
                       'predlozheniy': len(unik)}
for slug, unik in po_doc.items():
    m = mera[slug]
    m['dolya'] = round(100.0 * sum(1 for s in unik if len(predl_doc[s]) >= 2) / len(unik), 1) if unik else 0.0

def srednee(nabor, klyuch):
    return sum(mera[s][klyuch] for s in nabor) / float(len(nabor))

vse = list(mera.keys())
str_slugi = [p['slug'] for p in stranicy]
zap_slugi = [p['slug'] for p in zapisi]

ISKL = ['o-kompanii', 'price', 'kontakty', 'kl', 'vl-2', 'skoby-hodovye',
        'promyshlennye-lestnicy', 'kak-podobrat-lotok-nkl',
        'lyuki-chugunnye-klassy', 'fundament-f1-pod-dorozhnye-znaki']

print('корпус: %d документов (%d страниц + %d записей)' % (len(vse), len(str_slugi), len(zap_slugi)))
print('\nслуг | тип | слов всего | слов без таблиц | предложений | доля повторов %')
for s in ISKL:
    m = mera.get(s)
    if not m:
        print('%-33s НЕТ В КОРПУСЕ' % s); continue
    print('%-33s %-13s %5d %6d %6d %8.1f' % (s, m['tip'], m['slov'], m['bez_tablic'], m['predlozheniy'], m['dolya']))

print('\nСРЕДНЕЕ по 10 исключённым: слов без таблиц %.1f, доля повторов %.1f%%' % (
    srednee(ISKL, 'bez_tablic'), srednee(ISKL, 'dolya')))
print('СРЕДНЕЕ по всем 226:       слов без таблиц %.1f, доля повторов %.1f%%' % (
    srednee(vse, 'bez_tablic'), srednee(vse, 'dolya')))
print('СРЕДНЕЕ по 204 страницам:  слов без таблиц %.1f, доля повторов %.1f%%' % (
    srednee(str_slugi, 'bez_tablic'), srednee(str_slugi, 'dolya')))
print('СРЕДНЕЕ по 22 записям:     слов без таблиц %.1f, доля повторов %.1f%%' % (
    srednee(zap_slugi, 'bez_tablic'), srednee(zap_slugi, 'dolya')))

ost = [s for s in vse if s not in ISKL]
print('СРЕДНЕЕ по остальным 216:  слов без таблиц %.1f, доля повторов %.1f%%' % (
    srednee(ost, 'bez_tablic'), srednee(ost, 'dolya')))

med = sorted(mera[s]['dolya'] for s in vse)
print('медиана доли повторов по корпусу: %.1f%%' % med[len(med)//2])

# сколько страниц вообще целиком состоят из повторов
polnye = [s for s in str_slugi if mera[s]['dolya'] >= 99.9]
print('\nстраниц, где ВСЕ предложения встречаются ещё где-то: %d из %d' % (len(polnye), len(str_slugi)))
pochti = [s for s in str_slugi if mera[s]['dolya'] >= 90.0]
print('страниц с долей повторов 90%% и выше: %d из %d' % (len(pochti), len(str_slugi)))
malo = [s for s in str_slugi if mera[s]['dolya'] <= 30.0]
print('страниц с долей повторов 30%% и ниже: %d из %d' % (len(malo), len(str_slugi)))
