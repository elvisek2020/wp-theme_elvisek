/* ElvisEK — service worker (servíruje ho šablona na /sw.js, verze se doplní při odeslání).
 * Stránky: nejdřív síť, bez připojení uložená verze, jinak stránka „Jsi offline“.
 * Soubory šablony a obrázky: z mezipaměti a na pozadí obnovit.
 * Neukládá administraci, přihlášení, REST API, hledání, náhledy ani stránky přihlášeného uživatele. */
'use strict';

const V = 'ek-__EK_VERSION__';
const PAGES = V + '-pages';
const ASSETS = V + '-assets';
const INDEX = '/__ek_index';
const OFFLINE = '__EK_OFFLINE__';
const PRECACHE = __EK_PRECACHE__;
const MAX_PAGES = 60;
const MAX_ASSETS = 300;
const SKIP = /^\/(wp-admin|wp-login\.php|wp-json|wp-cron\.php|xmlrpc\.php|feed|comments\/feed|sw\.js)/;

self.addEventListener('install', (e) => {
	e.waitUntil(caches.open(PAGES).then((c) => c.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
	e.waitUntil(
		caches.keys()
			.then((keys) => Promise.all(keys.filter((k) => k.startsWith('ek-') && !k.startsWith(V + '-')).map((k) => caches.delete(k))))
			.then(() => self.clients.claim())
	);
});

const trim = async (name, max) => {
	const c = await caches.open(name);
	const keys = (await c.keys()).filter((r) => !r.url.endsWith(INDEX) && !r.url.endsWith(OFFLINE));
	for (let i = 0; i < keys.length - max; i++) await c.delete(keys[i]);
};

const readIndex = async (c) => {
	const r = await c.match(INDEX);
	try { return r ? await r.json() : {}; } catch (e) { return {}; }
};

const remember = async (req, res) => {
	const html = await res.clone().text();
	if (/id="wpadminbar"|class="[^"]*\blogged-in\b/.test(html)) return; // přihlášený uživatel → neukládat
	const c = await caches.open(PAGES);
	await c.put(req, res.clone());
	const m = html.match(/<title>([^<]*)<\/title>/i);
	const isArticle = /<body[^>]*class="[^"]*\bsingle-post\b/.test(html);
	if (m && isArticle) {
		const idx = await readIndex(c);
		const t = m[1].replace(/&#8211;|&ndash;/g, '–').replace(/&#8222;/g, '„').replace(/&#8220;/g, '“').replace(/&amp;/g, '&').replace(/\s+[–|-]\s+[^–|-]+$/, '').trim();
		idx[new URL(req.url).pathname] = { t, d: Date.now() };
		await c.put(INDEX, new Response(JSON.stringify(idx), { headers: { 'Content-Type': 'application/json' } }));
	}
	trim(PAGES, MAX_PAGES);
};

const fromNetwork = (req, ms) => new Promise((resolve, reject) => {
	const t = setTimeout(() => reject(new Error('timeout')), ms);
	fetch(req).then((r) => { clearTimeout(t); resolve(r); }, (e) => { clearTimeout(t); reject(e); });
});

const page = async (e) => {
	const req = e.request;
	try {
		const res = await fromNetwork(req, 6000);
		if (res.ok && res.type === 'basic' && (res.headers.get('Content-Type') || '').includes('text/html')) {
			e.waitUntil(remember(req, res.clone()));
		}
		return res;
	} catch (err) {
		const c = await caches.open(PAGES);
		return (await c.match(req, { ignoreSearch: false })) || (await c.match(OFFLINE)) ||
			new Response('<h1>Jsi offline</h1>', { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
	}
};

const asset = async (e) => {
	const c = await caches.open(ASSETS);
	const hit = await c.match(e.request);
	const update = fetch(e.request).then((res) => {
		if (res.ok && res.type === 'basic') { c.put(e.request, res.clone()).then(() => trim(ASSETS, MAX_ASSETS)); }
		return res;
	}).catch(() => hit);
	if (hit) { e.waitUntil(update); return hit; }
	return update;
};

self.addEventListener('fetch', (e) => {
	const req = e.request;
	if (req.method !== 'GET') return;
	const url = new URL(req.url);
	if (url.origin !== self.location.origin) return;
	if (url.pathname === INDEX) {
		e.respondWith(caches.open(PAGES).then((c) => c.match(INDEX)).then((r) => r || new Response('{}', { headers: { 'Content-Type': 'application/json' } })));
		return;
	}
	if (SKIP.test(url.pathname) || url.searchParams.has('s') || url.searchParams.has('preview') || url.searchParams.has('p')) return;
	if (req.mode === 'navigate') {
		e.respondWith(page(e));
		return;
	}
	if (/^\/wp-(content|includes)\//.test(url.pathname) && ['style', 'script', 'font', 'image'].includes(req.destination)) {
		e.respondWith(asset(e));
	}
});
