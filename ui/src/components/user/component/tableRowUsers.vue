<script setup>
import { computed } from "vue";
import PanelDropdown from "../../layout/PanelDropdown.vue";

const props = defineProps(["user", "index"]);

const fullName = (user) => {
    return [user.firstName, user.lastName].filter(Boolean).join(" ") || user.username || "-";
};

const dropdownItems = computed(() => [
    {
        label: "Zobraziť",
        to: {
            name: "users.show",
            params: {
                userId: props.user.id,
            },
        },
    },
    {
        label: "Upraviť",
        to: {
            name: "users.edit",
            params: {
                userId: props.user.id,
            },
        },
    },
]);
</script>

<template>
    <tr>
        <td class="tbody_td" :title="`ID: ${user.id}`">
            {{ index }}
        </td>

        <td class="tbody_td">
            <div class="text-sm font-medium text-gray-900">
                <router-link :to="{ name: 'users.show', params: { userId: user.id } }">
                    {{ fullName(user) }}
                </router-link>
            </div>
            <div class="text-sm text-gray-500">
                {{ user.username || "-" }}
            </div>
        </td>

        <td class="tbody_td">
            <div>{{ user.email || "-" }}</div>
            <div class="text-sm text-gray-500">{{ user.phone || "-" }}</div>
        </td>

        <td class="tbody_td">
            <div>{{ user.customer?.company || "-" }}</div>
            <div class="text-sm text-gray-500">{{ user.customer?.city || "" }}</div>
        </td>

        <td class="tbody_td">
            {{ user.orders_count }}
        </td>

        <td class="tbody_td">
            <div class="flex flex-wrap gap-2">
                <span
                    v-for="role in user.roles"
                    :key="role"
                    class="inline-flex rounded bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700 ring-1 ring-blue-200"
                >
                    {{ role }}
                </span>
                <span v-if="!user.roles?.length" class="text-gray-500">-</span>
            </div>
        </td>

        <td class="tbody_td">
            {{ user.created_at || "-" }}
        </td>

        <td class="tbody_td">
            <PanelDropdown :items="dropdownItems" />
        </td>
    </tr>
</template>
