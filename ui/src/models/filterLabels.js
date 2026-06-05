
import useUsers from "../store/StoreUsers";
import useQuery from "../store/StoreQuery";

const { state: uuu, getUserOrder } = useUsers();
const { state: q, getQueryLength } = useQuery();


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
    iconRight: uuu.userOrder.isNotificated,
};

export const isConfirmed = {
    name: 'Nepotvrdené',
    key: 'isOpened=',
    value: true,
    active: false,
    iconRight: getUserOrder.value.isConfirmed,
};
export const isDeleted = {
    name: 'Zmazané',
    key: 'isDeleted=',
    value: true,
    active: false,
    iconRight: getUserOrder.value.isDeleted,
};

export const resetFilter = {
    name: 'Zrušiť filter',
    key: 'resetFilter',
    value: true,
    active: false,
    iconRight: getQueryLength,
};