// Po nasadení zmiznú staré hashované chunky z /assets. Karta otvorená ešte pred
// nasadením ich potom nevie doťahať — jediná náprava je načítať nový index.html.

const RELOAD_KEY = "stale-chunk-reload-at";
// Ak chunk chýba aj po čerstvom načítaní, ďalší reload by nepomohol (slučka).
const RELOAD_COOLDOWN_MS = 30_000;

const CHUNK_ERROR_PATTERN = /dynamically imported module|Importing a module script failed|Unable to preload CSS|error loading dynamically imported module/i;

export const isStaleChunkError = (error) => CHUNK_ERROR_PATTERN.test(error?.message ?? "");

export const reloadForStaleChunk = (targetUrl) => {
    let lastReload = 0;
    try {
        lastReload = Number(sessionStorage.getItem(RELOAD_KEY)) || 0;
    } catch {
        // sessionStorage môže byť zablokovaný — radšej neobnovovať, než sa zacykliť
        return false;
    }

    if (Date.now() - lastReload < RELOAD_COOLDOWN_MS) return false;

    try {
        sessionStorage.setItem(RELOAD_KEY, String(Date.now()));
    } catch {
        return false;
    }

    if (targetUrl) {
        window.location.assign(targetUrl);
    } else {
        window.location.reload();
    }
    return true;
};

export const installStaleChunkReload = (router) => {
    // Lazy routy: prejdi na cieľovú adresu už s novým buildom.
    router.onError((error, to) => {
        if (isStaleChunkError(error)) reloadForStaleChunk(to?.fullPath);
    });

    // Ostatné dynamické importy (lazy komponenty, story) mimo routera.
    window.addEventListener("vite:preloadError", (event) => {
        if (reloadForStaleChunk()) event.preventDefault();
    });
};
