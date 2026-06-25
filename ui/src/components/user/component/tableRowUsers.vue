<script setup>
import { computed } from "vue";
import PanelDropdown from "../../layout/PanelDropdown.vue";

const props = defineProps(["user"]);

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
        <td class="tbody_td text-gray-500">
            {{ user.id }}
        </td>

        <td class="tbody_td">
            <div class="text-sm font-medium text-gray-900">
                <router-link :to="{ name: 'users.show', params: { userId: user.id } }">
                    {{ fullName(user) }}
                </router-link>
            </div>
            <!-- <div v-if="user.firstName || user.lastName" class="text-sm text-gray-500">
                {{ user.username || "-" }}
            </div> -->
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
            <span v-if="user.status" :class="[
                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold',
                user.status.color === 'green'  ? 'bg-green-100 text-green-800' :
                user.status.color === 'red'    ? 'bg-red-100 text-red-800' :
                user.status.color === 'amber'  ? 'bg-amber-100 text-amber-800' :
                user.status.color === 'slate'  ? 'bg-slate-100 text-slate-700' :
                                                 'bg-gray-100 text-gray-700'
            ]">
                {{ user.status.label }}
            </span>
            <span v-else class="text-gray-400">-</span>
        </td>

        <td class="tbody_td">
            {{ user.created_at || "-" }}
        </td>

        <td class="tbody_td">
            <PanelDropdown :items="dropdownItems" />
        </td>
    </tr>
</template>
