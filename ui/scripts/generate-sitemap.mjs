/**
 * Vygeneruje public/sitemap.xml zo zoznamu publikovaného tovaru.
 *
 * SPA nemá server, ktorý by sitemap poskladal za behu, preto vzniká pri builde.
 * Keď je API nedostupné, skript build nezhodí — zapíše aspoň statické stránky.
 *
 *   npm run sitemap        # samostatne
 *   npm run build          # automaticky pred vite build
 */

import { readFileSync, writeFileSync, existsSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const OUTPUT = resolve(ROOT, 'public/sitemap.xml');

const readEnvFile = (file) => {
    const path = resolve(ROOT, file);
    if (!existsSync(path)) return {};

    return Object.fromEntries(
        readFileSync(path, 'utf8')
            .split(/\r?\n/)
            .filter((line) => line.trim() && !line.trim().startsWith('#'))
            .map((line) => {
                const index = line.indexOf('=');
                return [line.slice(0, index).trim(), line.slice(index + 1).trim()];
            })
    );
};

const env = { ...readEnvFile('.env.production'), ...process.env };
const SITE_URL = (env.VITE_URL_BASE || 'https://zastavy-vlajky.sk').replace(/\/$/, '');
const API_URL = (env.VITE_URL_BASE_API || 'https://api.zastavy-vlajky.sk/api').replace(/\/$/, '');

const STATIC_ROUTES = [
    { path: '/', changefreq: 'daily', priority: '1.0' },
    { path: '/kontakt', changefreq: 'yearly', priority: '0.5' },
    { path: '/obchodne-podmienky', changefreq: 'yearly', priority: '0.3' },
    { path: '/ochrana-osobnych-udajov', changefreq: 'yearly', priority: '0.3' },
];

const escapeXml = (value) =>
    String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&apos;');

const fetchProducts = async () => {
    const products = [];
    let page = 1;
    let lastPage = 1;

    do {
        const response = await fetch(`${API_URL}/homes?page=${page}`, {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error(`${API_URL}/homes?page=${page} → HTTP ${response.status}`);
        }

        const payload = await response.json();
        products.push(...(payload.data ?? []));
        lastPage = Number(payload.meta?.last_page ?? 1);
        page += 1;
    } while (page <= lastPage);

    return products;
};

const buildUrl = ({ path, changefreq, priority, lastmod }) =>
    [
        '  <url>',
        `    <loc>${escapeXml(SITE_URL + path)}</loc>`,
        lastmod ? `    <lastmod>${escapeXml(lastmod)}</lastmod>` : null,
        changefreq ? `    <changefreq>${changefreq}</changefreq>` : null,
        priority ? `    <priority>${priority}</priority>` : null,
        '  </url>',
    ]
        .filter(Boolean)
        .join('\n');

const main = async () => {
    let products = [];

    try {
        products = await fetchProducts();
        console.log(`sitemap: načítaných ${products.length} produktov z ${API_URL}`);
    } catch (error) {
        console.warn(`sitemap: API nedostupné — ${error.message}`);

        // Prepísať existujúcu sitemap na štyri statické URL by z indexu vyhodilo
        // celý sortiment. Radšej necháme poslednú funkčnú verziu.
        if (existsSync(OUTPUT)) {
            console.warn(`sitemap: ponechávam predchádzajúcu verziu (${OUTPUT}).`);
            return;
        }
        console.warn('sitemap: zapisujem len statické stránky.');
    }

    const productUrls = products
        .filter((product) => product.id && product.slug)
        .map((product) =>
            buildUrl({
                path: `/product/${product.id}/show/${product.slug}`,
                lastmod: product.updated_at ? String(product.updated_at).slice(0, 10) : undefined,
                changefreq: 'weekly',
                priority: '0.8',
            })
        );

    const xml = [
        '<?xml version="1.0" encoding="UTF-8"?>',
        '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ...STATIC_ROUTES.map(buildUrl),
        ...productUrls,
        '</urlset>',
        '',
    ].join('\n');

    writeFileSync(OUTPUT, xml, 'utf8');
    console.log(`sitemap: zapísaných ${STATIC_ROUTES.length + productUrls.length} URL do ${OUTPUT}`);
};

main().catch((error) => {
    // Sitemap nesmie zhodiť build — bez nej sa dá nasadiť, s rozbitým buildom nie.
    console.warn(`sitemap: preskočené — ${error.message}`);
});
