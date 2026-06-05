<script setup>
import { computed } from "vue";
import PanelDropdown from "../../layout/PanelDropdown.vue";
import useCustomers from "../../../store/StoreCustomers";
import iconStar from "../../icons/star.vue";

const props = defineProps(["customer"]);
const { destroyCustomer, clickToMark } = useCustomers();

const deleteCustomer = async (url) => {
    await destroyCustomer(url);
};

const onClickMark = async (url) => {
    await clickToMark(url);
};

const dropdownItems = computed(() => {
    const items = []

    if (!Object.keys(props.customer.endpoints).length) {
        return []
    }

    const permissions = props.customer.permissions || {};
    const canUpdate = permissions.update ?? Boolean(props.customer.endpoints.update);
    const canDelete = permissions.delete ?? Boolean(props.customer.endpoints.destroy);

    if (canUpdate) {
        items.push({
            label: 'Upraviť',
            to: {
                name: 'customers.edit',
                params: {
                    customerId: props.customer.id,
                },
            },
        })
    }

    if (canDelete) {
        items.push({
            label: "Zmazať",
            onClick: () => destroyCustomer(props.customer.endpoints.destroy)
        })
    }

    return items
});

</script>

<template>
    <tr>
        <td class="tbody_td">
            {{ customer.id }}
        </td>
        <td class="tbody_td">
            <icon-star :status="customer.mark.isActive" class="mx-auto cursor-pointer"
                @click="onClickMark(customer.mark.endpoint)" />
        </td>
        <td class="px-6 py-4">
            <div class="h-8 w-8 bg-gray-300 rounded-full flex items-center justify-center" title="Počet objednávok">
                <div>{{ customer.orders }}</div>
            </div>
        </td>

        <td class="tbody_td">
            <div class="flex items-center">
                <div>
                    <router-link :to="{
                        name: 'customers.show',
                        params: {
                            customerId: customer.id,
                        },
                    }">
                        <div class="text-sm font-medium text-gray-900">
                            {{ customer.company.substring(0, 35) }}
                            <div class="text-sm text-gray-500">
                                {{ customer.name }}
                                {{ customer.phone }}
                            </div>
                        </div>
                    </router-link>
                </div>
            </div>
        </td>
        <td class="tbody_td">
            <div>
                {{ customer.street }}
            </div>

            <div class="text-sm">
                {{ customer.city }}
                {{ customer.postcode }}
            </div>
        </td>
        <td class="tbody_td">
            <div>
                {{ customer.ico }}
            </div>
            <div>
                {{ customer.dic }}
            </div>
        </td>

        <td class="tbody_td">
            <panel-dropdown :items="dropdownItems" />
        </td>
    </tr>
</template>
