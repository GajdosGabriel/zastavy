import { onBeforeRouteLeave } from 'vue-router';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import { confirmDialog } from './confirmDialog';

const EMPTY = Symbol('empty');

// Prázdna hodnota je čokoľvek, čo používateľ nevyplnil. API vracia null,
// input vracia '' a checkbox false — pre porovnanie sú to tie isté "nič".
const isEmptyValue = (value) =>
    value === null || value === undefined || value === '' || value === false || value === 0;

/**
 * Prevedie dáta na tvar, ktorý sa dá porovnať bez falošných rozdielov:
 * zoradí kľúče, zahodí prázdne hodnoty a zjednotí čísla ('10.00' === 10).
 */
function normalize(value, ignoredKeys) {
    if (Array.isArray(value)) {
        const items = value.map((item) => normalize(item, ignoredKeys)).filter((item) => item !== EMPTY);
        return items.length ? items : EMPTY;
    }

    if (value instanceof Date) {
        return value.toISOString();
    }

    if (value && typeof value === 'object') {
        const result = {};
        Object.keys(value)
            .sort()
            .forEach((key) => {
                if (ignoredKeys.includes(key)) return;
                const item = normalize(value[key], ignoredKeys);
                if (item === EMPTY) return;
                result[key] = item;
            });
        return Object.keys(result).length ? result : EMPTY;
    }

    if (isEmptyValue(value)) return EMPTY;
    if (value === true) return 1;

    if (typeof value === 'number') return Number(value);
    // Reťazec s vedúcou nulou je kód, nie číslo ('007' nie je 7).
    if (typeof value === 'string' && /^-?\d+(\.\d+)?$/.test(value.trim()) && !/^-?0\d/.test(value.trim())) {
        return Number(value);
    }

    return value;
}

function serialize(data, ignoredKeys) {
    const normalized = normalize(data, ignoredKeys);
    return JSON.stringify(normalized === EMPTY ? null : normalized);
}

/**
 * Stráži neuložené zmeny vo formulári.
 *
 * Upozorní len vtedy, keď sa dáta naozaj líšia od stavu pri otvorení
 * formulára — pri prázdnom "create" formulári, ktorého sa používateľ
 * nedotkol, teda nevyskočí nič.
 *
 * @param {Function} getCurrentData  vracia aktuálne dáta formulára
 * @param {Object}   options
 * @param {string[]} options.ignore  kľúče vynechané z porovnania (napr. timestampy)
 * @param {string}   options.message text potvrdzovacieho dialógu
 */
export default function useUnsavedChanges(getCurrentData, options = {}) {
    const ignoredKeys = options.ignore ?? [];
    const message = options.message
        ?? 'Vo formulári máte neuložené zmeny. Ak odídete, prídete o ne.';

    const originalData = ref(null);
    const hasBaseline = ref(false);

    const snapshot = () => {
        try {
            return serialize(getCurrentData(), ignoredKeys);
        } catch {
            return null;
        }
    };

    /** Nastaví východiskový stav — bez argumentu odfotí aktuálne dáta. */
    function setOriginalData(data) {
        originalData.value = data === undefined ? snapshot() : serialize(data, ignoredKeys);
        hasBaseline.value = true;
    }

    /** Po uložení sa aktuálny stav stáva novým východiskovým. */
    function markAsSaved() {
        setOriginalData();
    }

    function isFormChanged() {
        if (!hasBaseline.value) return false;
        return originalData.value !== snapshot();
    }

    const isDirty = computed(() => isFormChanged());

    // Ak formulár nenačítava dáta zo servera, východiskový stav si odfotíme sami.
    onMounted(async () => {
        await nextTick();
        if (!hasBaseline.value) setOriginalData();
    });

    // Zatvorenie / obnovenie karty rieši prehliadač vlastným dialógom.
    const onBeforeUnload = (event) => {
        if (!isFormChanged()) return;
        event.preventDefault();
        event.returnValue = '';
    };

    window.addEventListener('beforeunload', onBeforeUnload);
    onUnmounted(() => window.removeEventListener('beforeunload', onBeforeUnload));

    onBeforeRouteLeave(async () => {
        if (!isFormChanged()) return true;

        return await confirmDialog({
            title: 'Neuložené zmeny',
            message,
            confirmLabel: 'Odísť bez uloženia',
            cancelLabel: 'Zostať vo formulári',
            tone: 'danger',
        });
    });

    return {
        setOriginalData,
        resetBaseline: setOriginalData,
        markAsSaved,
        isFormChanged,
        isDirty,
    };
}
