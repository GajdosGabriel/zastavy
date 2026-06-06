<script setup>
import { reactive, ref, watch } from "vue";
import useQuery from "../../store/StoreQuery";

const search = reactive({
    key: "bySearchInput=",
    value: "",
});

const role = ref("");
const { setQuery, removeQuery } = useQuery();

watch(search, () => {
    setQuery(search);
});

watch(role, () => {
    role.value ? setQuery("role=" + role.value) : removeQuery("role=");
});

const clearInput = () => {
    removeQuery(search);
    search.value = "";
};
</script>

<template>
    <div class="filter-panel">
        <div class="grid gap-4 md:grid-cols-3">
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
                <select id="user-role" v-model="role" class="filter-input">
                    <option value="">Vsetky role</option>
                    <option value="super-admin">Super admin</option>
                    <option value="admin">Admin</option>
                    <option value="manager">Manager</option>
                    <option value="sales">Sales</option>
                    <option value="warehouse">Warehouse</option>
                    <option value="customer">Customer</option>
                </select>
            </div>
        </div>
    </div>
</template>
