<script setup>
defineProps(["user"]);

const fullName = (user) => {
    return [user.firstName, user.lastName].filter(Boolean).join(" ") || user.username || "-";
};
</script>

<template>
    <tr>
        <td class="tbody_td">
            {{ user.id }}
        </td>

        <td class="tbody_td">
            <div class="text-sm font-medium text-gray-900">
                {{ fullName(user) }}
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
    </tr>
</template>
