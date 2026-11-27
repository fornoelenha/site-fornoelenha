#!/usr/bin/env python3
"""
Gera o blog estático da Forno & Lenha a partir dos arquivos em blog/_posts/*.md.

Cada post é um arquivo AAAA-MM-DD-slug.md com cabeçalho:
---
titulo: Título do post
descricao: Resumo de até 160 caracteres (aparece no Google)
data: 2026-11-27
categoria: Curiosidades
mel: silvestre            # opcional: silvestre | organico | melato (define o convite no fim)
imagem: img/jornada-colmeia.jpg   # opcional: imagem do próprio site
---
Texto em Markdown (## subtítulos, listas, **negrito**, [links](...)).

Saída:
  blog/<slug>/index.html  (um por post)
  blog/index.html         (lista de posts)
  sitemap.xml e robots.txt
  bloco "Do blog" na página inicial (entre <!--BLOG-START--> e <!--BLOG-END-->)

Uso: python3 tools/build_blog.py   (rodar na raiz do repositório)
"""
import datetime as dt
import html
import json
import os
import re
import sys

RAIZ = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SITE = 'https://fornoelenhaalimentos.com.br'
POSTS_DIR = os.path.join(RAIZ, 'blog', '_posts')
WHATSAPP = '5511954231336'
MESES = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro']
MEIS = {
    'silvestre': ('Mel Silvestre', 'img/silvestre-pote500.webp', 'O clássico da casa: florada silvestre da mata catarinense.'),
    'organico': ('Mel Silvestre Orgânico', 'img/organico-pote500.webp', 'Florada silvestre com selo Orgânico Brasil.'),
    'melato': ('Melato da Bracatinga', 'img/melato-pote500.webp', 'O raro melato da serra catarinense, escuro e intenso.'),
}
IMG_PADRAO = 'img/jornada-florada.jpg'

try:
    import markdown as _md

    def md_para_html(txt):
        return _md.markdown(txt, extensions=['extra', 'sane_lists'])
except ImportError:  # conversor simples, caso a biblioteca não exista
    def _inline(t):
        t = html.escape(t, quote=False)
        t = re.sub(r'\*\*(.+?)\*\*', r'<strong>\1</strong>', t)
        t = re.sub(r'(?<!\*)\*(?!\*)(.+?)\*', r'<em>\1</em>', t)
        t = re.sub(r'\[(.+?)\]\((.+?)\)', r'<a href="\2">\1</a>', t)
        return t

    def md_para_html(txt):
        out, lista, par = [], None, []

        def fecha_par():
            if par:
                out.append('<p>' + _inline(' '.join(par)) + '</p>')
                par.clear()

        def fecha_lista():
            nonlocal lista
            if lista:
                out.append('</%s>' % lista)
                lista = None

        for linha in txt.splitlines():
            l = linha.strip()
            if not l:
                fecha_par(); fecha_lista(); continue
            m = re.match(r'^(#{2,4})\s+(.*)', l)
            if m:
                fecha_par(); fecha_lista()
                n = len(m.group(1))
                out.append('<h%d>%s</h%d>' % (n, _inline(m.group(2)), n)); continue
            m = re.match(r'^([-*]|\d+\.)\s+(.*)', l)
            if m:
                fecha_par()
                tipo = 'ol' if m.group(1)[0].isdigit() else 'ul'
                if lista != tipo:
                    fecha_lista(); out.append('<%s>' % tipo); lista = tipo
                out.append('<li>' + _inline(m.group(2)) + '</li>'); continue
            if l.startswith('>'):
                fecha_par(); fecha_lista()
                out.append('<blockquote><p>' + _inline(l.lstrip('> ')) + '</p></blockquote>'); continue
            fecha_lista(); par.append(l)
        fecha_par(); fecha_lista()
        return '\n'.join(out)


def ler_posts():
    posts = []
    if not os.path.isdir(POSTS_DIR):
        return posts
    for nome in sorted(os.listdir(POSTS_DIR)):
        if not nome.endswith('.md'):
            continue
        m = re.match(r'^(\d{4}-\d{2}-\d{2})-(.+)\.md$', nome)
        if not m:
            print('Ignorado (nome fora do padrão):', nome); continue
        bruto = open(os.path.join(POSTS_DIR, nome), encoding='utf-8').read()
        fm = re.match(r'^---\s*\n(.*?)\n---\s*\n(.*)$', bruto, re.S)
        if not fm:
            print('Ignorado (sem cabeçalho):', nome); continue
        meta = {}
        for l in fm.group(1).splitlines():
            if ':' in l:
                k, v = l.split(':', 1)
                v = v.split(' #')[0].strip().strip('"').strip("'")
                meta[k.strip()] = v
        data = meta.get('data') or m.group(1)
        if data > dt.date.today().isoformat():
            continue  # post agendado para o futuro
        posts.append({
            'slug': m.group(2), 'data': data, 'titulo': meta.get('titulo', m.group(2)),
            'descricao': meta.get('descricao', ''), 'categoria': meta.get('categoria', 'Blog'),
            'mel': meta.get('mel', ''), 'imagem': meta.get('imagem') or IMG_PADRAO,
            'corpo': md_para_html(fm.group(2).strip()), 'texto': fm.group(2),
        })
    posts.sort(key=lambda p: (p['data'], p['slug']), reverse=True)
    return posts


def data_br(iso):
    d = dt.date.fromisoformat(iso)
    return '%d de %s de %d' % (d.day, MESES[d.month - 1], d.year)


def minutos(texto):
    return max(2, round(len(re.findall(r'\w+', texto)) / 200))


CSS = """
*{box-sizing:border-box}body{margin:0;background:#FFF8EA;font-family:'Lexend',system-ui,sans-serif;color:#2B1A0E}
a{color:#A81C22}a:hover{color:#7E1218}.serif{font-family:'Fraunces',Georgia,serif}
.wrap{max-width:1280px;margin:0 auto}
header{position:sticky;top:0;z-index:50;background:rgba(255,198,26,.97);backdrop-filter:blur(10px);border-bottom:3px solid #D3262E}
header nav{padding:10px 24px;display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap}
.btn.cartbtn{min-height:46px;padding:0 18px}.cc{background:#FFC61A;color:#2B1A0E;border-radius:999px;min-width:24px;height:24px;display:inline-flex;align-items:center;justify-content:center;font-size:13px;padding:0 7px;font-weight:700}
.links{display:flex;gap:6px;flex-wrap:wrap}
.navlink{color:#2B1A0E;text-decoration:none;font-weight:500;font-size:15px;padding:9px 16px;border-radius:999px;transition:background .2s,color .2s}
.navlink:hover,.navlink.on{color:#fff;background:#D3262E;box-shadow:0 6px 16px rgba(168,28,34,.28)}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:10px;min-height:50px;padding:0 26px;border-radius:999px;font-weight:600;font-size:16px;text-decoration:none;border:0;cursor:pointer;font-family:'Lexend',sans-serif}
.btn-red{background:#D3262E;color:#fff;box-shadow:0 10px 24px rgba(211,38,46,.32)}.btn-red:hover{background:#B81F27;color:#fff}
.btn-line{background:#fff;color:#2B1A0E;border:2px solid #2B1A0E}.btn-line:hover{color:#D3262E;border-color:#D3262E}
.eyebrow{font-size:13px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:#A81C22}
.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:22px}
.card{background:#fff;border-radius:24px;overflow:hidden;text-decoration:none;color:#2B1A0E;display:flex;flex-direction:column;box-shadow:0 1px 0 #EAD9B8,0 10px 30px rgba(60,36,12,.07);transition:transform .2s}
.card:hover{transform:translateY(-4px);color:#2B1A0E}
.card img{width:100%;aspect-ratio:16/10;object-fit:cover;display:block}
.card .in{padding:20px;display:flex;flex-direction:column;gap:8px;flex:1}
.card h3{margin:0;font-family:'Fraunces',Georgia,serif;font-size:22px;line-height:1.2}
.card p{margin:0;color:#6B4A2E;font-size:15px;line-height:1.5}
.meta{font-size:13px;color:#6B4A2E}
.tag{display:inline-flex;align-self:flex-start;font-size:12px;font-weight:700;background:#FFF0C2;color:#7A4A00;border-radius:999px;padding:4px 10px}
article.post{max-width:760px;margin:0 auto;padding:40px 24px 30px}
article.post h1{font-family:'Fraunces',Georgia,serif;font-size:clamp(34px,5vw,52px);line-height:1.08;margin:12px 0 14px}
article.post .lead{font-size:19px;color:#6B4A2E;line-height:1.55;margin:0 0 22px}
article.post .capa{width:100%;aspect-ratio:16/9;object-fit:cover;border-radius:24px;margin:10px 0 26px}
.corpo{font-size:18px;line-height:1.75}
.corpo h2{font-family:'Fraunces',Georgia,serif;font-size:30px;line-height:1.2;margin:38px 0 10px}
.corpo h3{font-size:21px;margin:28px 0 8px}
.corpo li{margin:6px 0}.corpo blockquote{margin:24px 0;padding:16px 22px;background:#FFF4D6;border-left:5px solid #FFC61A;border-radius:0 16px 16px 0}
.cta{margin:40px 0 10px;background:#fff;border-radius:26px;padding:26px;display:flex;gap:22px;align-items:center;flex-wrap:wrap;box-shadow:0 1px 0 #EAD9B8,0 10px 30px rgba(60,36,12,.07);border-top:6px solid #D3262E}
.cta img{height:150px;width:auto}
footer{background:#2B1A0E;color:#E9D7B5;padding:44px 24px 34px;margin-top:60px}
footer a{color:#FFC61A}
.wa-float{position:fixed;right:20px;bottom:20px;z-index:55}
.wa-float span{width:60px;height:60px;border-radius:50%;background:#25D366;display:flex;align-items:center;justify-content:center;box-shadow:0 12px 28px rgba(0,0,0,.28)}
@media (max-width:900px){.grid{grid-template-columns:1fr 1fr}}
@media (max-width:1000px){header nav{row-gap:0;padding-top:6px;padding-bottom:0}.links{order:3;width:100%;overflow-x:auto;flex-wrap:nowrap;gap:4px;padding:2px 0 6px;scrollbar-width:none}.links::-webkit-scrollbar{display:none}.navlink{padding:7px 12px;font-size:14px;white-space:nowrap}}
@media (max-width:620px){.grid{grid-template-columns:1fr}.corpo{font-size:17px}}
"""

WA_SVG = '<svg width="30" height="30" viewBox="0 0 32 32" aria-hidden="true"><path d="M16 4C9.4 4 4 9.3 4 15.8c0 2.3.7 4.5 1.9 6.3L4.3 28l6.1-1.6c1.7.9 3.6 1.4 5.6 1.4 6.6 0 12-5.3 12-11.9S22.6 4 16 4z" fill="#fff"/><path d="M12.2 10.2c-.3-.6-.6-.6-.9-.6h-.8c-.3 0-.7.1-1 .5-.4.4-1.4 1.3-1.4 3.2s1.4 3.7 1.6 4c.2.3 2.7 4.3 6.7 5.9 3.3 1.3 4 1 4.7.9.7-.1 2.3-.9 2.6-1.8.3-.9.3-1.7.2-1.8-.1-.2-.4-.3-.8-.5s-2.3-1.1-2.6-1.3c-.4-.1-.6-.2-.9.2-.3.4-1 1.3-1.2 1.5-.2.3-.4.3-.8.1-.4-.2-1.7-.6-3.2-2-1.2-1-2-2.3-2.2-2.7-.2-.4 0-.6.2-.8l.6-.7c.2-.2.3-.4.4-.7.1-.3.1-.5 0-.7-.1-.2-.9-2.1-1.2-2.8z" fill="#25D366"/></svg>'


def pagina(titulo, descricao, url, corpo, extra_head='', og_img=None, blog_on=True):
    wa = 'https://wa.me/%s?text=%s' % (WHATSAPP, 'Ol%C3%A1!%20Vim%20pelo%20blog%20da%20Forno%20%26%20Lenha%20e%20tenho%20uma%20d%C3%BAvida%20sobre%20os%20m%C3%A9is.')
    return f"""<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{html.escape(titulo)}</title>
<meta name="description" content="{html.escape(descricao)}">
<link rel="canonical" href="{url}">
<meta property="og:title" content="{html.escape(titulo)}">
<meta property="og:description" content="{html.escape(descricao)}">
<meta property="og:image" content="{SITE}/{og_img or 'img/og.jpg'}">
<meta property="og:url" content="{url}">
<meta name="theme-color" content="#FFC61A">
<link rel="icon" type="image/png" href="/img/favicon.png?v=3">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,800;9..144,900&family=Lexend:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>{CSS}</style>
{extra_head}
</head>
<body>
<header><nav class="wrap" aria-label="Principal">
<a href="/" aria-label="Forno &amp; Lenha Alimentos, início" style="display:flex;align-items:center"><img src="/img/logo.png?v=3" alt="Forno &amp; Lenha Alimentos" style="height:68px;width:auto;display:block;filter:drop-shadow(0 3px 6px rgba(120,40,10,.25))"></a>
<div class="links"><a class="navlink" href="/#loja">Nossos méis</a><a class="navlink" href="/#quiz">Qual é o seu?</a><a class="navlink" href="/#jornada">Da flor ao pote</a><a class="navlink" href="/#receitas">Receitas</a><a class="navlink" href="/#curiosidades">Curiosidades</a><a class="navlink{' on' if blog_on else ''}" href="/blog/">Blog</a></div>
<a class="btn btn-red cartbtn" href="/?carrinho=1" aria-label="Abrir carrinho"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 6h15l-1.5 9h-12z"/><path d="M6 6 5 3H2"/><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/></svg><span>Carrinho</span><span class="cc" id="cc">0</span></a>
</nav></header>
<script>try{{var c=JSON.parse(localStorage.getItem('fl_cart')||'{{}}'),n=0;for(var k in c)n+=c[k];document.getElementById('cc').textContent=n;}}catch(e){{}}</script>
{corpo}
<footer><div class="wrap" style="display:flex;flex-wrap:wrap;gap:30px;justify-content:space-between;align-items:center">
<div style="display:flex;gap:14px;align-items:center"><img src="/img/logo.png?v=3" alt="" style="width:70px;height:auto"><span class="serif" style="font-size:20px;color:#FFF8EA">Mel direto da Serra Catarinense</span></div>
<div style="display:flex;gap:18px;flex-wrap:wrap;font-size:15px"><a href="/">Página inicial</a><a href="/#loja">Comprar mel</a><a href="/blog/">Blog</a><a href="https://instagram.com/fornoelenha.alimentos" target="_blank" rel="noopener">Instagram</a></div>
</div><p class="wrap" style="font-size:13px;color:#B9A383;margin-top:26px">Mel não deve ser oferecido a crianças menores de 1 ano. © Forno &amp; Lenha Alimentos</p></footer>
<a class="wa-float" href="{wa}" target="_blank" rel="noopener" aria-label="Tirar dúvidas pelo WhatsApp"><span>{WA_SVG}</span></a>
</body>
</html>
"""


def card(p, prefixo='/'):
    img = prefixo + p['imagem'].lstrip('/')
    return (f'<a class="card" href="/blog/{p["slug"]}/"><img src="{img}" alt="" loading="lazy">'
            f'<div class="in"><span class="tag">{html.escape(p["categoria"])}</span><h3>{html.escape(p["titulo"])}</h3>'
            f'<p>{html.escape(p["descricao"])}</p><span class="meta">{data_br(p["data"])} · {minutos(p["texto"])} min de leitura</span></div></a>')


def gerar_post(p, todos):
    url = f'{SITE}/blog/{p["slug"]}/'
    mel = MEIS.get(p['mel'])
    if mel:
        cta = (f'<div class="cta"><img src="/{mel[1]}" alt="{mel[0]}"><div style="flex:1;min-width:220px;display:flex;flex-direction:column;gap:10px">'
               f'<span class="eyebrow">Prove em casa</span><span class="serif" style="font-size:26px;font-weight:800">{mel[0]}</span>'
               f'<span style="color:#6B4A2E;line-height:1.5">{mel[2]} Bisnagas de 250g e 500g e potes de 500g e 1kg.</span>'
               f'<a class="btn btn-red" href="/#loja" style="align-self:flex-start">Escolher meu mel</a></div></div>')
    else:
        cta = ('<div class="cta"><img src="/img/lenha-hd.png?v=3" alt="Lenha, o mascote da Forno &amp; Lenha"><div style="flex:1;min-width:220px;display:flex;flex-direction:column;gap:10px">'
               '<span class="eyebrow">Mel direto da serra</span><span class="serif" style="font-size:26px;font-weight:800">Silvestre, Orgânico ou Melato?</span>'
               '<span style="color:#6B4A2E;line-height:1.5">Faça o teste no site e descubra qual dos nossos méis combina com você.</span>'
               '<a class="btn btn-red" href="/#quiz" style="align-self:flex-start">Descobrir meu mel</a></div></div>')
    outros = [o for o in todos if o['slug'] != p['slug']][:3]
    rel = ''
    if outros:
        rel = ('<section class="wrap" style="padding:20px 24px 0"><h2 class="serif" style="font-size:30px;margin:0 0 18px">Continue lendo</h2>'
               '<div class="grid">' + ''.join(card(o) for o in outros) + '</div></section>')
    ld = {
        '@context': 'https://schema.org', '@type': 'BlogPosting', 'headline': p['titulo'], 'description': p['descricao'],
        'datePublished': p['data'], 'dateModified': p['data'], 'image': f'{SITE}/{p["imagem"]}', 'url': url, 'inLanguage': 'pt-BR',
        'author': {'@type': 'Organization', 'name': 'Forno & Lenha Alimentos'},
        'publisher': {'@type': 'Organization', 'name': 'Forno & Lenha Alimentos', 'logo': {'@type': 'ImageObject', 'url': f'{SITE}/img/logo.png'}},
        'mainEntityOfPage': url,
    }
    corpo = (f'<main><article class="post"><a href="/blog/" style="font-size:15px;font-weight:600;text-decoration:none">← Todos os posts</a>'
             f'<div style="margin-top:18px"><span class="tag">{html.escape(p["categoria"])}</span></div>'
             f'<h1>{html.escape(p["titulo"])}</h1><p class="lead">{html.escape(p["descricao"])}</p>'
             f'<span class="meta">{data_br(p["data"])} · {minutos(p["texto"])} min de leitura · Equipe Forno &amp; Lenha</span>'
             f'<img class="capa" src="/{p["imagem"]}" alt="">'
             f'<div class="corpo">{p["corpo"]}</div>{cta}</article>{rel}</main>')
    extra = '<script type="application/ld+json">' + json.dumps(ld, ensure_ascii=False) + '</script>'
    destino = os.path.join(RAIZ, 'blog', p['slug'])
    os.makedirs(destino, exist_ok=True)
    with open(os.path.join(destino, 'index.html'), 'w', encoding='utf-8') as f:
        f.write(pagina(p['titulo'] + ' | Blog Forno & Lenha', p['descricao'], url, corpo, extra, p['imagem']))


def gerar_lista(posts):
    url = f'{SITE}/blog/'
    if posts:
        lista = '<div class="grid">' + ''.join(card(p) for p in posts) + '</div>'
    else:
        lista = '<p>Os primeiros posts estão chegando.</p>'
    corpo = ('<main class="wrap" style="padding:44px 24px 10px"><span class="eyebrow">Blog</span>'
             '<h1 class="serif" style="font-size:clamp(36px,5vw,56px);margin:10px 0 8px;line-height:1.05">Histórias, receitas e curiosidades do mel</h1>'
             '<p style="font-size:18px;color:#6B4A2E;margin:0 0 32px;max-width:640px;line-height:1.55">Tudo o que a gente aprende entre a serra catarinense e a sua mesa.</p>'
             + lista + '</main>')
    with open(os.path.join(RAIZ, 'blog', 'index.html'), 'w', encoding='utf-8') as f:
        f.write(pagina('Blog | Forno & Lenha Alimentos', 'Receitas com mel, curiosidades das abelhas e tudo sobre os méis da Serra Catarinense.', url, corpo))


def gerar_sitemap(posts):
    hoje = dt.date.today().isoformat()
    urls = [(f'{SITE}/', hoje, '1.0'), (f'{SITE}/blog/', posts[0]['data'] if posts else hoje, '0.8')]
    urls += [(f'{SITE}/blog/{p["slug"]}/', p['data'], '0.7') for p in posts]
    xml = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">']
    for u, d, pr in urls:
        xml.append(f'  <url><loc>{u}</loc><lastmod>{d}</lastmod><priority>{pr}</priority></url>')
    xml.append('</urlset>')
    open(os.path.join(RAIZ, 'sitemap.xml'), 'w', encoding='utf-8').write('\n'.join(xml) + '\n')
    open(os.path.join(RAIZ, 'robots.txt'), 'w', encoding='utf-8').write(
        'User-agent: *\nDisallow: /painel-avaliacoes.php\nDisallow: /api/\n\nSitemap: ' + SITE + '/sitemap.xml\n')


def atualizar_home(posts):
    caminho = os.path.join(RAIZ, 'index.html')
    s = open(caminho, encoding='utf-8').read()
    if '<!--BLOG-START-->' not in s:
        print('Aviso: marcadores do blog não encontrados na página inicial.'); return
    if posts:
        cards = ''.join(card(p, prefixo='') for p in posts[:3])
        bloco = ('<!--BLOG-START--><div class="blog-grid">' + cards.replace('href="/blog/', 'href="blog/') + '</div><!--BLOG-END-->')
    else:
        bloco = '<!--BLOG-START--><!--BLOG-END-->'
    s = re.sub(r'<!--BLOG-START-->.*?<!--BLOG-END-->', lambda m: bloco, s, flags=re.S)
    open(caminho, 'w', encoding='utf-8').write(s)


def main():
    posts = ler_posts()
    # apaga páginas de posts que não existem mais
    blog_dir = os.path.join(RAIZ, 'blog')
    os.makedirs(blog_dir, exist_ok=True)
    validos = {p['slug'] for p in posts}
    for nome in os.listdir(blog_dir):
        cam = os.path.join(blog_dir, nome)
        if os.path.isdir(cam) and not nome.startswith('_') and nome not in validos:
            for arq in os.listdir(cam):
                os.remove(os.path.join(cam, arq))
            os.rmdir(cam)
    for p in posts:
        gerar_post(p, posts)
    gerar_lista(posts)
    gerar_sitemap(posts)
    atualizar_home(posts)
    print('Blog gerado: %d post(s).' % len(posts))
    for p in posts[:5]:
        print(' -', p['data'], p['slug'])


if __name__ == '__main__':
    sys.exit(main())
