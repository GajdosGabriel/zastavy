<script setup>
import { reactive, ref, watch } from "vue";
import { storeToRefs } from "pinia";
import useQuery from "../../store/StoreQuery";
import { useAdminUsers } from "../../store/StoreAdminUsers";

const search = reactive({
    key: "bySearchInput=",
    value: "",
});

const role = ref("");
const status = ref("");
const login = ref("");
const { setQuery, removeQuery } = useQuery();
const { getStatuses } = storeToRefs(useAdminUsers());

watch(search, () => {
    setQuery(search);
});

watch(role, () => {
    role.value ? setQuery("role=" + role.value) : removeQuery("role=");
});

watch(status, () => {
    status.value ? setQuery("status=" + status.value) : removeQuery("status=");
});

watch(login, () => {
    login.value ? setQuery("login=" + login.value) : removeQuery("login=");
});

const clearInput = () => {
    removeQuery(search);
    search.value = "";
};
</script>

<template>
    <div class="filter-panel">
        <div class="grid gap-4 md:grid-cols-3 lg:grid-cols-5">
            <div class="filter-field md:col-span-2">
                <label class="filter-label" for="user-search">Hladanie pouzivatela</label>
                <div class="filter-control">
                    <input
                        id="user-search"
                        type="text"
                        v-model="search.value"
                        class="filter-input"
                        placeholder="Meno, e-mail, telefon, firma alebo mesto"
                    />
                    <button
                        v-if="search.value"
                        type="button"
                        class="filter-clear"
                        aria-label="Zrusit hladanie"
                        @click="clearInput"
                    >
                        x
                    </button>
                </div>
            </div>

            <div class="filter-field">
                <label class="filter-label" for="user-role">Rola</label>
                <select id="user-role" v-model="role" class="filter-select">
                    <option value="">Vsetky role</option>
                    <option value="super-admin">Super admin</option>
                    <option value="admin">Admin</option>
                    <option value="manager">Manager</option>
                    <option value="sales">Sales</option>
                    <option value="warehouse">Warehouse</option>
                    <option value="customer">Customer</option>
                </select>
            </div>

            <div v-if="getStatuses.length" class="filter-field">
                <label class="filter-label" for="user-status">Status</label>
                <select id="user-status" v-model="status" class="filter-select">
                    <option value="">Vsetky statusy</option>
                    <option v-for="item in getStatuses" :key="item.value" :value="item.value">
                        {{ item.label }}
                    </option>
                </select>
            </div>

            <div class="filter-field">
                <label class="filter-label" for="user-login">Prihlásenie</label>
                <select id="user-login" v-model="login" class="filter-select">
                    <option value="">Všetci</option>
                    <option value="never">Nikdy neprihlásení</option>
                    <option value="logged">Aspoň raz prihlásení</option>
                    <option value="last30">Prihlásení za 30 dní</option>
                    <option value="over90">Neprihlásení viac ako 90 dní</option>
                </select>
            </div>
        </div>
    </div>
</template>
