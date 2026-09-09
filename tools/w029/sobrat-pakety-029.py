#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Задание 029, пункты 3, 4 и 5. Собирает два пакета:

  packages/kolodtsy-komplekt.json — раздел колодцев: фраза про комплект
      (обещали люк в комплекте, а все восемь карточек колодцев говорят обратное);
  packages/blog-029.json          — записи блога: описание ПВК со ссылкой на
      карточку ПВК-8 и цены четырёх марок задания 021.

Приём тот же, что в 024–026: строгая замена, каждое «было» обязано встретиться
в тексте ровно один раз, иначе AssertionError и пакет не собирается. Сверх того
здесь стоит проверка на числа: скрипт сравнивает все числа записи до и после и
падает, если изменилось хоть одно число, которого нет в списке разрешённых.
Цены в блоге — про деньги, и «заодно поправилось» тут недопустимо.

Запуск: python3 tools/w029/sobrat-pakety-029.py [dump.json]
"""
import json, os, re, sys

REPO = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
DUMP = sys.argv[1] if len(sys.argv) > 1 else '/srv/regiongbi/tmp/w029/w029-do.json'
PVK  = '/catalog/kolodtsy-unifitsirovannye/pvk-8/'

# --- пункт 3: раздел колодцев ------------------------------------------------
RAZDEL = (
    'kolodtsy-unifitsirovannye',
    'Готовый комплект колодца по типовому проекту: днище, кольца, плита и люк одной поставкой.',
    'Готовый комплект колодца по типовому проекту: днище, кольца и плита одной поставкой. '
    'Люк и скобы подбираются по нагрузке и считаются отдельно.',
)

# --- пункты 4 и 5: записи блога ---------------------------------------------
# (slug, было, стало, какие числа этой заменой разрешено изменить)
BLOG = [
    # пункт 4: ПВК — наша позиция, а не «такой марки нет»
    ('kryshki-kolodcev-pp-pk-pvg',
     '<p>Обозначение ПВК в проектах встречается, но в нашем производственном ряду такой '
     'марки нет — если по проекту нужна именно ПВК, пришлите чертёж колодца, подберём '
     'аналог по габариту и нагрузке.</p>',
     '<p>ПВК — круглая плита к дождеприёмному колодцу ВД-8; в нашем ряду это '
     '<a href="%s">ПВК-8</a> за 2 400 ₽ с НДС. Если по проекту нужен другой типоразмер ПВК, '
     'пришлите чертёж колодца — подберём по габариту и нагрузке.</p>' % PVK,
     {'2 400', '8'}),

    # пункт 5: ПП 10-2  1 810 -> 2 484
    ('kryshki-kolodcev-pp-pk-pvg',
     '<td>1160×1160×120</td><td>250</td><td>1 810</td>',
     '<td>1160×1160×120</td><td>250</td><td>2 484</td>', {'1 810', '2 484'}),
    ('op-ili-pp-chem-otlichayutsya',
     '<td>1160×1160×120</td><td>250</td><td>1 810</td>',
     '<td>1160×1160×120</td><td>250</td><td>2 484</td>', {'1 810', '2 484'}),
    ('op-ili-pp-chem-otlichayutsya',
     'перекрытие ПП 10-2 стоит 1 810 ₽',
     'перекрытие ПП 10-2 стоит 2 484 ₽', {'1 810', '2 484'}),
    # вывод завязан на разницу: 5 100 / 1 810 — это втрое, 5 100 / 2 484 — вдвое
    ('op-ili-pp-chem-otlichayutsya',
     'заказали ОП вместо ПП — переплатили втрое и не закрыли шахту',
     'заказали ОП вместо ПП — переплатили вдвое и не закрыли шахту', set()),
    ('drenazhnyy-kolodec-iz-kolec',
     '<td>1</td><td>250</td><td>1 810</td>',
     '<td>1</td><td>250</td><td>2 484</td>', {'1 810', '2 484'}),
    # итог таблицы: 1 520 + 7 650 + 2 484 + 6 578 = 18 232
    ('drenazhnyy-kolodec-iz-kolec',
     '<strong>17 558</strong>', '<strong>18 232</strong>', {'17 558', '18 232'}),
    ('drenazhnyy-kolodec-iz-kolec',
     'около 17 500 ₽ с НДС', 'около 18 200 ₽ с НДС', {'17 500', '18 200'}),
    ('zhbi-dlya-livnevoy-kanalizacii',
     'Крышка ПП 10-2</a> — 250 кг, 1 810 ₽',
     'Крышка ПП 10-2</a> — 250 кг, 2 484 ₽', {'1 810', '2 484'}),
    ('kak-sobrat-kolodec-komplektom',
     'Крышка ПП 10-2</a> — 250 кг, 1 810 ₽ ·',
     'Крышка ПП 10-2</a> — 250 кг, 2 484 ₽ ·', {'1 810', '2 484'}),
    ('raschet-kolets-dlya-kolodca',
     'ПП 10-2</a> (250 кг, 1 810 ₽)',
     'ПП 10-2</a> (250 кг, 2 484 ₽)', {'1 810', '2 484'}),
    # сумма комплекта: 4 × 2 550 + 1 520 + 2 484 + 6 578 = 20 782, округление прежнее — до сотен
    ('raschet-kolets-dlya-kolodca',
     'примерно 20 100 ₽ по позициям',
     'примерно 20 800 ₽ по позициям', {'20 100', '20 800'}),

    # пункт 5: ПП 20-2  6 810 -> 8 420
    ('kryshki-kolodcev-pp-pk-pvg',
     '<td>2200×2200×160</td><td>1 400</td><td>6 810</td>',
     '<td>2200×2200×160</td><td>1 400</td><td>8 420</td>', {'6 810', '8 420'}),
    ('op-ili-pp-chem-otlichayutsya',
     '<td>2200×2200×160</td><td>1 400</td><td>6 810</td>',
     '<td>2200×2200×160</td><td>1 400</td><td>8 420</td>', {'6 810', '8 420'}),

    # пункт 5: ПК 15  6 710 -> 7 265
    ('kryshki-kolodcev-pp-pk-pvg',
     '<td>1700×1700×140</td><td>680</td><td>6 710</td>',
     '<td>1700×1700×140</td><td>680</td><td>7 265</td>', {'6 710', '7 265'}),
    ('kryshki-kolodcev-pp-pk-pvg',
     'ПК 15 того же размера — 6 710 ₽',
     'ПК 15 того же размера — 7 265 ₽', {'6 710', '7 265'}),
]

CHISLA = re.compile(r'\d[\d ]*')

def chisla(s):
    return sorted(x.strip() for x in CHISLA.findall(s))

d = json.load(open(DUMP, encoding='utf-8'))
pages = {p['slug']: p for p in d['pages']}
posts = {p['slug']: p for p in d['posts']}

# ---------- пакет раздела ----------
slug, bylo, stalo = RAZDEL
p = pages[slug]
c = p['content']
assert c.count(bylo) == 1, 'на %s фраза про комплект встречается %d раз' % (slug, c.count(bylo))
assert 'Люк и скобы подбираются' not in c, 'на %s правка уже стоит' % slug
novoe = c.replace(bylo, stalo)
assert chisla(novoe) == chisla(c), 'на %s изменились числа, а не должны' % slug
razdel_pkg = {
    'package': 'kolodtsy-komplekt',
    'primechanie': ('Задание 029, пункт 3: раздел унифицированных колодцев обещал люк в комплекте, '
                    'а все восемь карточек колодцев говорят, что люк в комплект не входит. '
                    'Правится одна фраза, таблицы и цены не трогаются.'),
    'items': [{'url': p['path'], 'content': novoe}],
}

# ---------- пакет блога ----------
teksty = {s: posts[s]['content'] for s in set(x[0] for x in BLOG)}
razresheno = {s: set() for s in teksty}
log = []
for slug, bylo, stalo, mozhno in BLOG:
    c = teksty[slug]
    n = c.count(bylo)
    assert n == 1, 'в записи %s «%s…» встречается %d раз' % (slug, bylo[:50], n)
    teksty[slug] = c.replace(bylo, stalo)
    razresheno[slug] |= mozhno
    log.append((slug, bylo[:56], stalo[:56]))

items = []
for slug, novoe in sorted(teksty.items()):
    staroe = posts[slug]['content']
    assert novoe != staroe, 'запись %s не изменилась' % slug
    do, posle = chisla(staroe), chisla(novoe)
    ushli = [x for x in do if do.count(x) > posle.count(x)]
    prishli = [x for x in posle if posle.count(x) > do.count(x)]
    lishnie = (set(ushli) | set(prishli)) - razresheno[slug]
    assert not lishnie, 'в записи %s изменились непредусмотренные числа: %s' % (slug, sorted(lishnie))
    par = [[b, s] for sl, b, s in [(x[0], x[1], x[2]) for x in BLOG] if sl == slug]
    items.append({'slug': slug, 'replace': par})

blog_pkg = {
    'package': 'blog-029',
    'primechanie': ('Задание 029, пункты 4 и 5. Пункт 4: в статье про крышки ПВК описана как наша '
                    'позиция ПВК-8 со ссылкой на карточку (владелец подтвердил 09.09.2026, что мы её '
                    'отгружаем по 2 400 ₽). Пункт 5: цены четырёх марок задания 021 в записях блога '
                    'приведены к ценам карточек — ПП 10-2 1 810 -> 2 484, ПП 20-2 6 810 -> 8 420, '
                    'ПК 15 6 710 -> 7 265; КУ-25 в блоге не встречается. Вместе с ценой пересчитаны '
                    'две суммы, в которые ПП 10-2 входит слагаемым, и поправлен вывод «переплатили '
                    'втрое» (5 100 / 2 484 — это вдвое). Прочих цен замена не касается.'),
    'items': items,
}

for name, pkg in (('kolodtsy-komplekt', razdel_pkg), ('blog-029', blog_pkg)):
    out = os.path.join(REPO, 'packages', name + '.json')
    with open(out, 'w', encoding='utf-8') as f:
        json.dump(pkg, f, ensure_ascii=False, indent=2)
        f.write('\n')
    print('%-20s позиций %d -> %s' % (name, len(pkg['items']), out))

print()
print('замен в блоге: %d по %d записям' % (len(BLOG), len(items)))
for slug, b, s in log:
    print('  %-32s «%s…»' % (slug, b))
