import { reactive, readonly } from 'vue';

/**
 * Zdieľaný potvrdzovací dialóg. Stav je modulový singleton, vykresľuje ho
 * jediná inštancia <ConfirmDialog /> v App.vue — komponenty teda nemusia
 * riešiť vlastný modal, stačí `await confirmDialog({ ... })`.
 */
const state = reactive({
    isOpen: false,
    title: '',
    message: '',
    confirmLabel: 'Pokračovať',
    cancelLabel: 'Zrušiť',
    tone: 'danger',
});

let resolveCurrent = null;

export const confirmState = readonly(state);

export function confirmDialog(options = {}) {
    // Ak by sa otvoril nový dialóg nad rozbehnutým, ten pôvodný zrušíme.
    if (resolveCurrent) {
        resolveCurrent(false);
        resolveCurrent = null;
    }

    Object.assign(state, {
        title: 'Naozaj?',
        message: '',
        confirmLabel: 'Pokračovať',
        cancelLabel: 'Zrušiť',
        tone: 'danger',
        ...options,
        isOpen: true,
    });

    return new Promise((resolve) => {
        resolveCurrent = resolve;
    });
}

export function closeConfirmDialog(result) {
    if (!state.isOpen) return;

    state.isOpen = false;

    const resolve = resolveCurrent;
    resolveCurrent = null;
    if (resolve) resolve(result === true);
}
