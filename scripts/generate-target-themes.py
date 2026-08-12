#!/usr/bin/env python3
"""按主站主题的视觉标识，生成目标站点主题 CSS。

目标站点的标记是固定的，所以一套主题 = 一组变量 + 少量结构差异。
除了主色，还刻意变化字体、卡片形态、页头样式和圆角，避免 20 个站
只是同一张脸换配色。
"""
from pathlib import Path

OUT = Path('/Users/lan/Documents/GEOFlow/resources/target-themes')

SERIF = 'Georgia,"Times New Roman","Songti SC",serif'
SANS = '-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC",sans-serif'
MONOISH = '"Iowan Old Style",Charter,Georgia,serif'

# key: (accent, page_bg, surface, ink, muted, font_body, font_head, radius, card, header)
# card:   border | shadow | rule | flat
# header: plain | bar | thick | minimal
SPEC = {
    'ink-editorial':      ('#8b1e1e', '#faf8f5', '#ffffff', '#1a1614', '#6b625c', SERIF,  SERIF,  0,  'rule',   'thick'),
    'market-briefing':    ('#b8860b', '#fffdf7', '#ffffff', '#1c1a15', '#6c6558', SANS,   SERIF,  2,  'border', 'bar'),
    'salmon-insight':     ('#0f766e', '#f6faf9', '#ffffff', '#132320', '#5b716d', SANS,   SANS,   10, 'shadow', 'plain'),
    'red-opinion':        ('#e3120b', '#ffffff', '#ffffff', '#141414', '#666666', SERIF,  SANS,   0,  'rule',   'thick'),
    'wire-clean':         ('#ff8000', '#fcfcfa', '#ffffff', '#1b1b1a', '#6d6d68', SANS,   SANS,   4,  'border', 'minimal'),
    'public-broadcast':   ('#bb1919', '#f4f4f4', '#ffffff', '#141414', '#5a5a5a', SANS,   SANS,   0,  'flat',   'bar'),
    'breaking-red':       ('#cc0000', '#ffffff', '#fff7f7', '#111111', '#5f5f5f', SANS,   SANS,   0,  'rule',   'thick'),
    'section-blue':       ('#052962', '#fdfaf6', '#ffffff', '#121212', '#5c5c5c', SERIF,  SERIF,  0,  'rule',   'bar'),
    'tech-spectrum':      ('#ff0080', '#0f0f14', '#1a1a22', '#f2f2f7', '#9a9aa8', SANS,   SANS,   14, 'shadow', 'minimal'),
    'wired-feature':      ('#1f6feb', '#ffffff', '#ffffff', '#101418', '#5a636d', SANS,   SANS,   0,  'rule',   'minimal'),
    'product-newsroom':   ('#0071e3', '#fbfbfd', '#ffffff', '#1d1d1f', '#6e6e73', SANS,   SANS,   16, 'shadow', 'plain'),
    'saas-gradient':      ('#635bff', '#f7f6ff', '#ffffff', '#0a2540', '#5d6b83', SANS,   SANS,   12, 'shadow', 'plain'),
    'linear-system':      ('#5e6ad2', '#f7f8fa', '#ffffff', '#16181d', '#61656d', SANS,   SANS,   8,  'border', 'minimal'),
    'knowledge-paper':    ('#2f6f4e', '#f7f5ee', '#fffefb', '#1d2119', '#63695c', MONOISH, SERIF, 2,  'border', 'plain'),
    'reading-medium':     ('#1a8917', '#ffffff', '#ffffff', '#191919', '#6b6b6b', SERIF,  SANS,   0,  'flat',   'minimal'),
    'newsletter-letter':  ('#ff6719', '#fffaf5', '#ffffff', '#1a1613', '#6f645c', SERIF,  SANS,   6,  'border', 'plain'),
    'executive-review':   ('#a41034', '#f9f7f4', '#ffffff', '#17141a', '#635c66', SERIF,  SERIF,  0,  'rule',   'bar'),
    'consulting-insight': ('#0065bd', '#f6f9fc', '#ffffff', '#10151b', '#5a6470', SANS,   SANS,   4,  'border', 'plain'),
    'tech-review':        ('#a31f34', '#fbf9f9', '#ffffff', '#171314', '#665c5e', SANS,   SERIF,  2,  'rule',   'thick'),
    'research-journal':   ('#14625c', '#fbfbf8', '#ffffff', '#151a19', '#5f6866', MONOISH, SERIF, 0,  'border', 'plain'),
    'support-clone':      ('#2997ff', '#f5f5f7', '#ffffff', '#1d1d1f', '#6e6e73', SANS,   SANS,   14, 'flat',   'plain'),
}


def card_css(k, card, surface, radius, accent, muted):
    if card == 'border':
        return (f'.target-theme-{k} .card{{background:{surface};border:1px solid rgba(0,0,0,.10);'
                f'border-radius:{radius}px;box-shadow:none}}')
    if card == 'shadow':
        return (f'.target-theme-{k} .card{{background:{surface};border:0;border-radius:{radius}px;'
                f'box-shadow:0 4px 20px rgba(15,23,42,.08)}}')
    if card == 'rule':
        return (f'.target-theme-{k} .card{{background:transparent;border:0;border-bottom:1px solid rgba(0,0,0,.14);'
                f'border-radius:0;box-shadow:none;padding-left:0;padding-right:0}}'
                f'.target-theme-{k} .list{{gap:0}}')
    return (f'.target-theme-{k} .card{{background:{surface};border:0;border-radius:{radius}px;box-shadow:none}}')


def header_css(k, header, accent, surface, ink):
    if header == 'bar':
        return (f'.target-theme-{k} header{{background:{accent};border-bottom:0}}'
                f'.target-theme-{k} .brand{{color:#fff}}'
                f'.target-theme-{k} nav a{{color:rgba(255,255,255,.86)}}')
    if header == 'thick':
        return (f'.target-theme-{k} header{{background:{surface};border-bottom:3px solid {accent}}}'
                f'.target-theme-{k} .brand{{color:{ink}}}')
    if header == 'minimal':
        return (f'.target-theme-{k} header{{background:transparent;border-bottom:1px solid rgba(128,128,128,.22);'
                f'backdrop-filter:none}}'
                f'.target-theme-{k} .brand{{color:{ink}}}')
    return (f'.target-theme-{k} header{{background:{surface};border-bottom:1px solid rgba(128,128,128,.20)}}'
            f'.target-theme-{k} .brand{{color:{ink}}}')


def build(k, spec):
    accent, bg, surface, ink, muted, fbody, fhead, radius, card, header = spec
    dark = bg.startswith('#0') or bg.startswith('#1')
    line = 'rgba(255,255,255,.14)' if dark else 'rgba(0,0,0,.12)'
    css = [
        f'body.target-theme-{k}{{background:{bg};color:{ink};font-family:{fbody}}}',
        f'.target-theme-{k} .brand{{font-family:{fhead};letter-spacing:-.01em}}',
        f'.target-theme-{k} h1,.target-theme-{k} h2,.target-theme-{k} .detail h1{{font-family:{fhead};color:{ink}}}',
        f'.target-theme-{k} h2 a{{color:{ink}}}',
        f'.target-theme-{k} h2 a:hover,.target-theme-{k} .read,.target-theme-{k} .back{{color:{accent}}}',
        f'.target-theme-{k} .summary,.target-theme-{k} .meta,.target-theme-{k} .empty{{color:{muted}}}',
        f'.target-theme-{k} .content{{color:{ink};font-family:{fbody}}}',
        f'.target-theme-{k} .content h2,.target-theme-{k} .content h3{{font-family:{fhead};color:{ink}}}',
        f'.target-theme-{k} .content a{{color:{accent}}}',
        f'.target-theme-{k} .content blockquote{{border-left:3px solid {accent};color:{muted}}}',
        f'.target-theme-{k} .chip{{background:transparent;border:1px solid {accent};color:{accent}}}',
        f'.target-theme-{k} .tags span{{border:1px solid {line};color:{muted}}}',
        f'.target-theme-{k} .detail{{background:{surface};border-radius:{radius}px}}',
        f'.target-theme-{k} footer{{border-top:1px solid {line};color:{muted}}}',
        f'.target-theme-{k} nav a{{color:{muted}}}',
        f'.target-theme-{k} nav a:hover{{color:{accent}}}',
        f'.target-theme-{k} .homepage-module{{background:{surface};border:1px solid {line};border-radius:{radius}px}}',
        f'.target-theme-{k} .module-action{{background:{accent};border-radius:{max(radius, 4)}px}}',
        f'.target-theme-{k} .module-kicker{{color:{accent}}}',
        card_css(k, card, surface, radius, accent, muted),
        header_css(k, header, accent, surface, ink),
    ]
    if dark:
        css.append(f'.target-theme-{k} .content pre{{background:#000;color:#e6e6ef}}')
        css.append(f'.target-theme-{k} .content img{{opacity:.94}}')
    return '\n'.join(css) + '\n'


made = []
for key, spec in SPEC.items():
    path = OUT / f'{key}.css'
    path.write_text(build(key, spec), encoding='utf-8')
    made.append((key, len(path.read_text(encoding='utf-8'))))

for k, size in made:
    print(f'  {k:22} {size:5d} 字节')
print(f'\n  新增 {len(made)} 套')
