/* EKT pwa — service worker. Servíruje ho modul pwa na <home>/sw.js a doplní hodnoty "__EKT_…__"
 * (verze, cesta webu i v podsložce, offline stránka, soubory k uložení předem).
 * Stránky: nejdřív síť, bez připojení uložená verze, jinak offline stránka.
 * Soubory z wp-content a wp-includes: z mezipaměti a na pozadí obnovit.
 * Neukládá administraci, přihlášení, REST, hledání, náhledy ani stránky přihlášeného uživatele. */
'use strict';

const VERSION = "__EKT_VERSION__";
const BASE = "__EKT_BASE__";
const OFFLINE = "__EKT_OFFLINE__";
const PRECACHE = "__EKT_PRECACHE__";

const V = 'ekt-' + VERSION;
const PAGES = V + '-pages';
const ASSETS = V + '-assets';
const INDEX = BASE + '__ekt_index';
const MAX_PAGES = 60;
const MAX_ASSETS = 300;
const esc = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
const SKIP = new RegExp('^' + esc(BASE) + '(wp-admin|wp-login\\.php|wp-json|wp-cron\\.php|xmlrpc\\.php|feed|comments/feed|sw\\.js|manifest\\.webmanifest)|\\.md$');

self.addEventListener('install', (e) => {
	e.waitUntil(caches.open(PAGES).then((c) => c.addAll(Array.isArray(PRECACHE) ? PRECACHE : [])).catch(() => {}).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
	// Starší verze (ekt-*) a cache staré šablony (ek-*).
	e.waitUntil(
		caches.keys()
			.then((keys) => Promise.all(keys.filter((k) => (k.startsWith('ekt-') && !k.startsWith(V + '-')) || k.startsWith('ek-')).map((k) => caches.delete(k))))
			.then(() => self.clients.claim())
	);
});

const trim = async (name, max) => {
	const c = await caches.open(name);
	const keys = (await c.keys()).filter((r) => { const p = new URL(r.url).pathname; return p !== INDEX && p !== OFFLINE; });
	for (let i = 0; i < keys.length - max; i++) await c.delete(keys[i]);
};

const readIndex = async (c) => {
	const r = await c.match(INDEX);
	try { return r ? await r.json() : {}; } catch (e) { return {}; }
};

const decode = (s) => s
	.replace(/&#(\d+);/g, (m, n) => String.fromCharCode(Number(n)))
	.replace(/&#x([0-9a-f]+);/gi, (m, n) => String.fromCharCode(parseInt(n, 16)))
	.replace(/&quot;/g, '"').replace(/&apos;|&#039;/g, "'").replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&nbsp;/g, ' ').replace(/&amp;/g, '&');

const remember = async (req, res) => {
	const html = await res.clone().text();
	if (/id="wpadminbar"|<body[^>]*class="[^"]*\blogged-in\b/.test(html)) return; // přihlášený uživatel → neukládat
	const c = await caches.open(PAGES);
	await c.put(req, res.clone());
	const m = html.match(/<title>([^<]*)<\/title>/i);
	if (m && /<body[^>]*class="[^"]*\bsingle\b/.test(html)) {
		const idx = await readIndex(c);
		const title = decode(m[1]).replace(/\s+[–|-]\s+[^–|-]+$/, '').trim();
		idx[new URL(req.url).pathname] = { t: title, d: Date.now() };
		await c.put(INDEX, new Response(JSON.stringify(idx), { headers: { 'Content-Type': 'application/json' } }));
	}
	await trim(PAGES, MAX_PAGES);
};

const fromNetwork = (req, ms) => new Promise((resolve, reject) => {
	const t = setTimeout(() => reject(new Error('timeout')), ms);
	fetch(req).then((r) => { clearTimeout(t); resolve(r); }, (err) => { clearTimeout(t); reject(err); });
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
		return (await c.match(req)) || (await c.match(OFFLINE)) ||
			new Response('<!doctype html><meta charset="utf-8"><title>Offline</title><h1>Offline</h1>', { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
	}
};

const asset = async (e) => {
	const c = await caches.open(ASSETS);
	const hit = await c.match(e.request);
	const update = fetch(e.request).then((res) => {
		if (res.ok && res.type === 'basic') c.put(e.request, res.clone()).then(() => trim(ASSETS, MAX_ASSETS));
		return res;
	}).catch(() => hit || Response.error());
	if (hit) { e.waitUntil(update); return hit; }
	return update;
};

self.addEventListener('fetch', (e) => {
	const req = e.request;
	if (req.method !== 'GET') return;
	const url = new URL(req.url);
	if (url.origin !== self.location.origin || !url.pathname.startsWith(BASE)) return;
	if (url.pathname === INDEX) {
		e.respondWith(caches.open(PAGES).then((c) => c.match(INDEX)).then((r) => r || new Response('{}', { headers: { 'Content-Type': 'application/json' } })));
		return;
	}
	if (SKIP.test(url.pathname) || ['s', 'preview', 'p', 'page_id', 'customize_changeset_uuid'].some((k) => url.searchParams.has(k))) return;
	if (req.mode === 'navigate') {
		e.respondWith(page(e));
		return;
	}
	if (/\/wp-(content|includes)\//.test(url.pathname) && ['style', 'script', 'font', 'image'].includes(req.destination)) {
		e.respondWith(asset(e));
	}
});
