import { URL_BASE, SITE_NAME } from '../constants';

/**
 * Správa <head> pre SPA.
 *
 * Vue Router nemení <head>, takže bez tohto modulu má každá stránka rovnaký
 * (prázdny) titulok a popis. Značky sa prepisujú, nie duplikujú — každý
 * prechod na inú routu najprv zavolá resetSeo().
 */

export const DEFAULT_TITLE = 'Zástavy a vlajky | Vlajky Slovenska, obecné zástavy a štátne symboly';

// Popis sa v <head> orezáva na 160 znakov — držať ho kratší, nech nekončí v polovici vety.
export const DEFAULT_DESCRIPTION =
    'Predaj vlajok a zástav — vlajka Slovenska, obecné a mestské zástavy, štátne symboly ' +
    'a smútočné vlajky. Dodávame obciam, školám aj firmám.';

export const DEFAULT_IMAGE = '/og-default.jpg';

/**
 * Verejné stránky, ktoré má vyhľadávač indexovať. Všetko ostatné (administrácia,
 * košík, prihlásenie, detail objednávky) dostane noindex — allowlist je bezpečnejší
 * ako blacklist, nová admin routa je automaticky skrytá.
 */
const INDEXABLE_ROUTES = new Set([
    'public.index',
    'public.products.show',
    'public.obchodne.podmienky',
    'public.ochranaOsobnychUdajov',
    'public.contactUs',
]);

const JSON_LD_ATTR = 'data-seo';

export const absoluteUrl = (path = '/') => {
    const base = URL_BASE || window.location.origin;
    try {
        return new URL(path, base).href;
    } catch {
        return base;
    }
};

const truncate = (text, limit = 160) => {
    const clean = String(text ?? '').replace(/\s+/g, ' ').trim();
    if (clean.length <= limit) return clean;
    return clean.slice(0, clean.lastIndexOf(' ', limit - 1)).trim() + '…';
};

const setMeta = (attr, key, content) => {
    const el = document.head.querySelector(`meta[${attr}="${key}"]`);
    if (!content) {
        el?.remove();
        return;
    }
    if (el) {
        el.setAttribute('content', content);
        return;
    }
    const meta = document.createElement('meta');
    meta.setAttribute(attr, key);
    meta.setAttribute('content', content);
    document.head.appendChild(meta);
};

const setLink = (rel, href) => {
    const el = document.head.querySelector(`link[rel="${rel}"]`);
    if (!href) {
        el?.remove();
        return;
    }
    if (el) {
        el.setAttribute('href', href);
        return;
    }
    const link = document.createElement('link');
    link.setAttribute('rel', rel);
    link.setAttribute('href', href);
    document.head.appendChild(link);
};

/**
 * Structured data. `id` slúži na výmenu bloku bez duplikovania — napr. 'product'
 * sa pri prechode na iný produkt prepíše.
 */
export const setJsonLd = (id, data) => {
    const selector = `script[${JSON_LD_ATTR}="${id}"]`;
    const existing = document.head.querySelector(selector);
    if (!data) {
        existing?.remove();
        return;
    }
    const script = existing ?? document.createElement('script');
    script.type = 'application/ld+json';
    script.setAttribute(JSON_LD_ATTR, id);
    script.textContent = JSON.stringify(data);
    if (!existing) document.head.appendChild(script);
};

const clearJsonLd = () => {
    document.head
        .querySelectorAll(`script[${JSON_LD_ATTR}]`)
        .forEach((script) => script.remove());
};

/**
 * @param {object} seo
 * @param {string}  [seo.title]        titulok bez názvu webu, doplní sa automaticky
 * @param {string}  [seo.description]
 * @param {string}  [seo.image]        absolútna alebo relatívna cesta k OG obrázku
 * @param {string}  [seo.path]         cesta pre canonical, default aktuálna URL
 * @param {string}  [seo.type]         og:type, default 'website'
 * @param {boolean} [seo.noindex]
 */
export const applySeo = ({ title, description, image, path, type = 'website', noindex = false } = {}) => {
    const fullTitle = !title || title === DEFAULT_TITLE
        ? DEFAULT_TITLE
        : `${title} | ${SITE_NAME}`;
    const desc = truncate(description || DEFAULT_DESCRIPTION);
    const canonical = absoluteUrl(path ?? window.location.pathname);
    const ogImage = absoluteUrl(image || DEFAULT_IMAGE);

    document.title = fullTitle;

    setMeta('name', 'description', desc);
    setMeta('name', 'robots', noindex ? 'noindex, nofollow' : 'index, follow, max-image-preview:large');
    setLink('canonical', noindex ? null : canonical);

    setMeta('property', 'og:type', type);
    setMeta('property', 'og:site_name', SITE_NAME);
    setMeta('property', 'og:locale', 'sk_SK');
    setMeta('property', 'og:title', fullTitle);
    setMeta('property', 'og:description', desc);
    setMeta('property', 'og:url', canonical);
    setMeta('property', 'og:image', ogImage);

    setMeta('name', 'twitter:card', 'summary_large_image');
    setMeta('name', 'twitter:title', fullTitle);
    setMeta('name', 'twitter:description', desc);
    setMeta('name', 'twitter:image', ogImage);
};

/**
 * Volá sa pri každom prechode na inú routu — zhodí structured data predchádzajúcej
 * stránky a nastaví základ z router meta. Komponent si potom môže SEO spresniť
 * (napr. detail produktu po načítaní dát).
 */
export const applyRouteSeo = (route) => {
    clearJsonLd();

    applySeo({
        title: route.meta?.title,
        description: route.meta?.description,
        path: route.fullPath?.split('?')[0] ?? route.path,
        noindex: !(route.meta?.indexable ?? INDEXABLE_ROUTES.has(route.name)),
    });
};

export const breadcrumbJsonLd = (items) => ({
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement: items.map((item, index) => ({
        '@type': 'ListItem',
        position: index + 1,
        name: item.name,
        item: absoluteUrl(item.path),
    })),
});

export const organizationJsonLd = () => ({
    '@context': 'https://schema.org',
    '@type': 'Organization',
    '@id': absoluteUrl('/#organization'),
    name: 'Gajdoš Gabriel - Reprezent',
    alternateName: SITE_NAME,
    url: absoluteUrl('/'),
    logo: absoluteUrl(DEFAULT_IMAGE),
    email: 'obchod@zastavy-vlajky.sk',
    telephone: '+421905320616',
    vatID: 'SK1020747398',
    taxID: '14287315',
    address: {
        '@type': 'PostalAddress',
        streetAddress: 'Sekčovská 19',
        postalCode: '086 41',
        addressLocality: 'Raslavice',
        addressCountry: 'SK',
    },
    contactPoint: {
        '@type': 'ContactPoint',
        contactType: 'customer service',
        telephone: '+421905320616',
        email: 'obchod@zastavy-vlajky.sk',
        availableLanguage: ['sk'],
    },
});

export const websiteJsonLd = () => ({
    '@context': 'https://schema.org',
    '@type': 'WebSite',
    '@id': absoluteUrl('/#website'),
    name: SITE_NAME,
    url: absoluteUrl('/'),
    inLanguage: 'sk-SK',
    publisher: { '@id': absoluteUrl('/#organization') },
});

/**
 * Ceny a dostupnosť žijú na variantoch, preto pri viacerých prevedeniach
 * posielame AggregateOffer s rozsahom — to je aj to, čo Google zobrazí vo výpise.
 */
export const productJsonLd = (product, path) => {
    const variants = (product.variants ?? []).filter((variant) => variant.published);
    const prices = variants
        .map((variant) => Number(variant.active_price))
        .filter((price) => Number.isFinite(price) && price > 0);

    const url = absoluteUrl(path);
    const availability = product.is_in_stock
        ? 'https://schema.org/InStock'
        : 'https://schema.org/OutOfStock';

    const images = (product.images ?? []).map((image) => image.path).filter(Boolean);

    const data = {
        '@context': 'https://schema.org',
        '@type': 'Product',
        name: product.name,
        description: truncate(product.description || `${product.name} — ${DEFAULT_DESCRIPTION}`, 500),
        sku: product.code,
        url,
        image: images.length ? images : [absoluteUrl(product.thumb || DEFAULT_IMAGE)],
        brand: { '@type': 'Organization', name: SITE_NAME },
    };

    if (prices.length > 1 && Math.min(...prices) !== Math.max(...prices)) {
        data.offers = {
            '@type': 'AggregateOffer',
            priceCurrency: 'EUR',
            lowPrice: Math.min(...prices).toFixed(2),
            highPrice: Math.max(...prices).toFixed(2),
            offerCount: variants.length,
            availability,
            url,
        };
    } else if (prices.length) {
        data.offers = {
            '@type': 'Offer',
            priceCurrency: 'EUR',
            price: prices[0].toFixed(2),
            availability,
            itemCondition: 'https://schema.org/NewCondition',
            url,
            seller: { '@id': absoluteUrl('/#organization') },
        };
    }

    return data;
};
