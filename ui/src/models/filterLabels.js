
import useUsers from "../store/StoreUsers";
import useQuery from "../store/StoreQuery";

// StoreUsers aj StoreQuery sú Pinia — volaj lazy vnútri getterov
// (nie na module-level, spadlo by pred app.use(pinia)).

export const isNotificated = {
    name: 'Neoznámené',
    key: 'isNotificated=',
    value: true,
    active: false,
    iconRight: null,
};

export const isActive = {
    name: 'Aktívne',
    key: 'isActive=',
    value: true,
    active: false,
    get iconRight() { return useUsers().userOrder?.isNotificated; },
};

export const isConfirmed = {
    name: 'Nepotvrdené',
    key: 'isOpened=',
    value: true,
    active: false,
    get iconRight() { return useUsers().getUserOrder?.isConfirmed; },
};
export const isDeleted = {
    name: 'Zmazané',
    key: 'isDeleted=',
    value: true,
    active: false,
    get iconRight() { return useUsers().getUserOrder?.isDeleted; },
};

export const resetFilter = {
    name: 'Zrušiť filter',
    key: 'resetFilter',
    value: true,
    active: false,
    get iconRight() { return useQuery().getQueryLength; },
};