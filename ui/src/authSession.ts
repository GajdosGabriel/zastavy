// Prihlasovací token je v httpOnly cookie (JS ho nevidí). V localStorage zostáva len
// nesenzitívny príznak, aby sme sa hosťa nepýtali na /user pri každej navigácii.
const KEY = 'authSession';

export const hasSessionHint = (): boolean => {
    try {
        return localStorage.getItem(KEY) === '1';
    } catch {
        return false;
    }
};

// Zvyšky starých verzií: token pred prechodom na httpOnly cookie a spoločný kľúč
// `customer`, do ktorého zapisoval aj admin formulár (cudzie osobné údaje na zdieľanom PC).
export const purgeLegacyStorage = (): void => {
    try {
        ['authToken', 'token', 'customer'].forEach((key) => localStorage.removeItem(key));
    } catch { /* súkromný režim – nie je čo mazať */ }
};

export const markSession =(active: boolean): void => {
    try {
        if (active) localStorage.setItem(KEY, '1');
        else localStorage.removeItem(KEY);
    } catch { /* súkromný režim – bez príznaku sa len nezavolá /user automaticky */ }
};
