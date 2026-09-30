#!/usr/bin/env python3
"""Minimal WordPress REST client for www.elvisek.cz (drafts only).

Credentials are read from ../ctime.txt (section "WordPress API"); they are never printed.

Usage:
  wp.py whoami
  wp.py categories
  wp.py drafts
  wp.py draft --title "..." --content file.html [--categories 3,5] [--tags "a,b"] [--excerpt "..."] [--featured image.jpg] [--id 123]
  wp.py upload image.jpg [--alt "..."]
  wp.py get 123            # post content (raw) to stdout
  wp.py posts [--search X] [--category ID]   # published posts
  wp.py pages
  wp.py media [--search X]
Posts are always created/updated with status=draft; publishing is left to a human.
"""
import argparse, base64, html, json, mimetypes, os, re, sys, urllib.error, urllib.request

BASE = 'https://www.elvisek.cz/wp-json/wp/v2'
CTIME = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'ctime.txt')


def _auth() -> str:
    text = open(CTIME, encoding='utf-8').read()
    block = text[text.index('WordPress API'):]
    user = re.search(r'Uživatel:\s*(\S+)', block).group(1)
    pw = re.search(r'Heslo aplikace:\s*(.+)', block).group(1).strip()
    return 'Basic ' + base64.b64encode(f'{user}:{pw}'.encode()).decode()


def api(method, path, data=None, raw=None, headers=None):
    h = {'Authorization': _auth(), 'User-Agent': 'elvisek-wp-cli'}
    body = None
    if raw is not None:
        body = raw
    elif data is not None:
        body = json.dumps(data).encode()
        h['Content-Type'] = 'application/json'
    h.update(headers or {})
    req = urllib.request.Request(BASE + path, data=body, method=method, headers=h)
    try:
        with urllib.request.urlopen(req, timeout=60) as r:
            return json.loads(r.read() or b'null')
    except urllib.error.HTTPError as e:
        msg = e.read().decode('utf-8', 'replace')
        try:
            msg = json.loads(msg).get('message', msg)
        except Exception:
            pass
        sys.exit(f'HTTP {e.code}: {msg[:300]}')


def upload(path, alt=''):
    name = os.path.basename(path)
    mime = mimetypes.guess_type(name)[0] or 'application/octet-stream'
    media = api('POST', '/media', raw=open(path, 'rb').read(), headers={
        'Content-Type': mime, 'Content-Disposition': f'attachment; filename="{name}"'})
    if alt:
        api('POST', f"/media/{media['id']}", {'alt_text': alt})
    return media


def tag_ids(names):
    ids = []
    for n in [x.strip() for x in names.split(',') if x.strip()]:
        found = api('GET', '/tags?search=' + urllib.request.quote(n))
        match = next((t for t in found if t['name'].lower() == n.lower()), None)
        ids.append(match['id'] if match else api('POST', '/tags', {'name': n})['id'])
    return ids


def main():
    ap = argparse.ArgumentParser()
    sub = ap.add_subparsers(dest='cmd', required=True)
    sub.add_parser('whoami'); sub.add_parser('categories'); sub.add_parser('drafts')
    g = sub.add_parser('get'); g.add_argument('id', type=int)
    ps = sub.add_parser('posts'); ps.add_argument('--search', default=''); ps.add_argument('--category', default='')
    sub.add_parser('pages')
    md = sub.add_parser('media'); md.add_argument('--search', default='')
    u = sub.add_parser('upload'); u.add_argument('file'); u.add_argument('--alt', default='')
    d = sub.add_parser('draft')
    d.add_argument('--title', required=True); d.add_argument('--content', required=True)
    d.add_argument('--categories', default=''); d.add_argument('--tags', default='')
    d.add_argument('--excerpt', default=''); d.add_argument('--featured'); d.add_argument('--id', type=int)
    a = ap.parse_args()

    if a.cmd == 'whoami':
        me = api('GET', '/users/me?context=edit')
        print(json.dumps({'id': me['id'], 'name': me['name'], 'roles': me.get('roles')}, ensure_ascii=False))
    elif a.cmd == 'categories':
        for c in api('GET', '/categories?per_page=100&orderby=count&order=desc'):
            print(f"{c['id']:>5}  {c['count']:>3}  {c['slug']:<22} {c['name']}")
    elif a.cmd == 'drafts':
        for p in api('GET', '/posts?status=draft&per_page=50&context=edit'):
            print(f"{p['id']:>6}  {p['modified'][:16]}  {p['title']['raw']}")
    elif a.cmd in ('posts', 'pages', 'media'):
        path = {'posts': '/posts', 'pages': '/pages', 'media': '/media'}[a.cmd]
        q = '?per_page=100&_fields=id,date,slug,title,link,status,categories,media_details,mime_type,source_url'
        if getattr(a, 'search', ''): q += '&search=' + urllib.request.quote(a.search)
        if getattr(a, 'category', ''): q += '&categories=' + a.category
        page, rows = 1, []
        while True:
            batch = api('GET', f'{path}{q}&page={page}')
            rows += batch
            if len(batch) < 100: break
            page += 1
        for r in rows:
            title = html.unescape(r["title"]["rendered"]) if isinstance(r.get("title"), dict) else ""
            if a.cmd == 'media':
                md = r.get('media_details') or {}
                print(f"{r['id']:>6}  {r['date'][:10]}  {md.get('width','?')}x{md.get('height','?')}  {r['source_url'].split('/uploads/')[-1]}")
            else:
                print(f"{r['id']:>6}  {r['date'][:10]}  {r['status']:<8} {title}")
        print(f'# {len(rows)} items', file=sys.stderr)
    elif a.cmd == 'get':
        print(api('GET', f'/posts/{a.id}?context=edit')['content']['raw'])
    elif a.cmd == 'upload':
        m = upload(a.file, a.alt); print(m['id'], m['source_url'])
    elif a.cmd == 'draft':
        post = {'title': a.title, 'content': open(a.content, encoding='utf-8').read(), 'status': 'draft'}
        if a.categories: post['categories'] = [int(x) for x in a.categories.split(',')]
        if a.tags: post['tags'] = tag_ids(a.tags)
        if a.excerpt: post['excerpt'] = a.excerpt
        if a.featured: post['featured_media'] = upload(a.featured, a.title)['id']
        if a.id:
            cur = api('GET', f'/posts/{a.id}?context=edit')
            if cur['status'] != 'draft':
                sys.exit('Refusing to modify a non-draft post.')
            res = api('POST', f'/posts/{a.id}', post)
        else:
            res = api('POST', '/posts', post)
        print(res['id'], res['status'], f"https://www.elvisek.cz/wp-admin/post.php?post={res['id']}&action=edit")


if __name__ == '__main__':
    main()
