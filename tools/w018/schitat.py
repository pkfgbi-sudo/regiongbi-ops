# -*- coding: utf-8 -*-
"""Задание 018. Считает объём и шаблонность опубликованных страниц.
Только чтение: на вход — выгрузка post_content из базы, на выход — два CSV."""
import io, json, re, csv, sys
from collections import Counter, defaultdict

d = json.load(io.open('/srv/regiongbi/tmp/w018/pages.json', encoding='utf-8'))

TABLE = re.compile(r'<table\b.*?</table>', re.S | re.I)
COMMENT = re.compile(r'<!--.*?-->', re.S)
SCRIPT = re.compile(r'<(script|style)\b.*?</\1>', re.S | re.I)
TAG = re.compile(r'<[^>]+>')
SLOVO = re.compile(r'[0-9A-Za-zА-Яа-яЁё]+', re.U)

def text(html):
    s = COMMENT.sub(' ', html)
    s = SCRIPT.sub(' ', s)
    s = TAG.sub(' ', s)
    s = s.replace('&nbsp;', ' ').replace('&mdash;', '—').replace('&ndash;', '–')
    s = s.replace('&laquo;', '«').replace('&raquo;', '»').replace('&amp;', '&')
    return re.sub(r'\s+', ' ', s).strip()

def slov(s):
    return len(SLOVO.findall(s))

# --- марки и числа: ПП 10-1, КС 15-9, 1700×1700, 2,5 м
MARKA = re.compile(r'\b[А-ЯЁA-Z]{1,6}[\-\s]?\d[\d\-–—.,/×xхX*]*', re.U)
CHISLO = re.compile(r'\d[\d\-–—.,/×xхX*]*', re.U)
KONEC = re.compile(r'(?<=[.!?…])\s+')

def predlozheniya(s):
    """Предложения страницы: без марок и чисел, в нижнем регистре."""
    out = []
    for kusok in KONEC.split(s):
        for ch in re.split(r'\s*[\n\r]+\s*', kusok):
            t = MARKA.sub(' ', ch)
            t = CHISLO.sub(' ', t)
            t = t.lower()
            t = re.sub(r'[^0-9a-zа-яё ]+', ' ', t)
            t = re.sub(r'\s+', ' ', t).strip()
            if len(SLOVO.findall(t)) >= 3:      # короче трёх слов — не предложение, а подпись
                out.append(t)
    return out

def razdel(url, slug):
    put = re.sub(r'^https?://[^/]+/', '', url).strip('/')
    ch = [x for x in put.split('/') if x]
    if not ch:
        return 'главная'
    if ch[0] == 'catalog':
        if len(ch) == 1:
            return 'catalog (корень раздела)'
        if len(ch) == 2:
            return ch[1] + ' (страница раздела)'
        return ch[1]
    if len(ch) == 1:
        return 'верхний уровень'
    return ch[0]

stroki = []
predl_stranic = defaultdict(set)   # предложение -> слуги
po_stranice = {}

for p in d:
    t_vsego = text(p['content'])
    t_bez = text(TABLE.sub(' ', p['content']))
    pr = predlozheniya(t_bez)
    unik = set(pr)
    po_stranice[p['slug']] = unik
    for s in unik:
        predl_stranic[s].add(p['slug'])
    stroki.append({
        'slug': p['slug'], 'id': p['id'], 'razdel': razdel(p['url'], p['slug']),
        'slov_vsego': slov(t_vsego), 'slov_bez_tablic': slov(t_bez),
        'predlozheniy': len(unik),
    })

# доля предложений страницы, которые встречаются ещё где-то
for r in stroki:
    unik = po_stranice[r['slug']]
    if unik:
        povtor = sum(1 for s in unik if len(predl_stranic[s]) >= 2)
        r['dolya_povtorov'] = round(100.0 * povtor / len(unik), 1)
    else:
        r['dolya_povtorov'] = 0.0

# --- 1. packages/shablonnost.csv
with io.open('packages/shablonnost.csv', 'w', encoding='utf-8', newline='') as f:
    w = csv.writer(f, delimiter=';', quoting=csv.QUOTE_MINIMAL)
    w.writerow(['slug', 'ID', 'razdel', 'slov_vsego', 'slov_bez_tablic',
                'predlozheniy', 'dolya_povtorov_proc'])
    for r in sorted(stroki, key=lambda x: (x['razdel'], x['slug'])):
        w.writerow([r['slug'], r['id'], r['razdel'], r['slov_vsego'],
                    r['slov_bez_tablic'], r['predlozheniy'], r['dolya_povtorov']])

# --- 2. packages/povtory.csv: предложения с трёх и более страниц
pov = [(s, sl) for s, sl in predl_stranic.items() if len(sl) >= 3]
pov.sort(key=lambda x: (-len(x[1]), x[0]))
with io.open('packages/povtory.csv', 'w', encoding='utf-8', newline='') as f:
    w = csv.writer(f, delimiter=';', quoting=csv.QUOTE_MINIMAL)
    w.writerow(['predlozhenie', 'na_skolki_stranicah', 'primer_slugov'])
    for s, sl in pov:
        w.writerow([s, len(sl), ' '.join(sorted(sl)[:5])])

print('страниц: %d' % len(stroki))
print('предложений всего (уникальных): %d' % len(predl_stranic))
print('предложений на 3+ страницах: %d' % len(pov))
print('строк в povtory.csv: %d' % len(pov))

# --- 3. по разделам
gr = defaultdict(list)
for r in stroki:
    gr[r['razdel']].append(r)
print('\n--- по разделам (раздел | страниц | ср. слов без таблиц | ср. доля повторов %)')
itog = []
for k, v in gr.items():
    itog.append((k, len(v),
                 round(sum(x['slov_bez_tablic'] for x in v) / float(len(v)), 1),
                 round(sum(x['dolya_povtorov'] for x in v) / float(len(v)), 1)))
for k, n, sw, dp in sorted(itog, key=lambda x: -x[1]):
    print('%-38s %4d %8.1f %8.1f' % (k, n, sw, dp))

vsego = len(stroki)
print('\nПО САЙТУ: страниц %d, ср. слов всего %.1f, ср. слов без таблиц %.1f, ср. доля повторов %.1f%%' % (
    vsego,
    sum(x['slov_vsego'] for x in stroki) / float(vsego),
    sum(x['slov_bez_tablic'] for x in stroki) / float(vsego),
    sum(x['dolya_povtorov'] for x in stroki) / float(vsego)))
med = sorted(x['dolya_povtorov'] for x in stroki)
print('медиана доли повторов: %.1f%%' % med[vsego // 2])

# --- 4. исключённые из поиска
ISKL = ['o-kompanii', 'price', 'kontakty', 'kl', 'vl-2', 'skoby-hodovye',
        'promyshlennye-lestnicy', 'kak-podobrat-lotok-nkl',
        'lyuki-chugunnye-klassy', 'fundament-f1-pod-dorozhnye-znaki']
po_slugu = dict((r['slug'], r) for r in stroki)
print('\n--- страницы из списка исключённых Вебмастером')
est = []
for s in ISKL:
    r = po_slugu.get(s)
    if r is None:
        print('%-32s НА САЙТЕ НЕТ' % s)
        continue
    est.append(r)
    print('%-32s раздел %-28s слов %4d / без таблиц %4d / повторов %5.1f%%' % (
        s, r['razdel'], r['slov_vsego'], r['slov_bez_tablic'], r['dolya_povtorov']))
if est:
    print('СРЕДНЕЕ ПО НИМ (%d стр.): слов без таблиц %.1f, доля повторов %.1f%%' % (
        len(est),
        sum(x['slov_bez_tablic'] for x in est) / float(len(est)),
        sum(x['dolya_povtorov'] for x in est) / float(len(est))))
