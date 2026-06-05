<template>
    <div class="col-span-11">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="thead">
                <tr>
                    <th class="thead_th">ID</th>
                    <th class="thead_th">Tovar</th>
                    <th class="thead_th">Kusov</th>
                    <th class="thead_th">Cena spolu</th>
                    <th class="thead_th">Storno</th>
                    <th class="thead_th">Panel</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="item in state.orderProducts" :key="item.id">
                    <item-row :item="item.id"></item-row>
                    <item-row :item="item.name"></item-row>
                    <item-row :item="item.quantity"></item-row>
                    <item-row :item="item.price"></item-row>
                    <item-row :item="item.storno"></item-row>

                    <td class="tbody_td">
                        <span @click="
                            update(order.id, item.id, {
                                storno: (item.storno =
                                    !item.storno),
                            })
                            " class="cursor-pointer">Stornovať</span>
                        <span @click="deleteItem(order.id, item.id)" class="cursor-pointer">Zmazať</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script>
import { onMounted } from "vue";

import useOrderProducts from "../../store/OrderProducts";
import itemRow from "./itemRow.vue";
export default {
    props: ["order"],
    components: { itemRow },
    setup(props) {
        const { state, getItems, updateItem, destroyItem } = useOrderProducts();
        onMounted(() => {
            getItems(props.order.id);
        });

        const update = (order, item, data) => {
            updateItem(order, item, data);
        };

        const deleteItem = (order, item) => {
            destroyItem(order, item);
        };

        return {
            state,
            deleteItem,
            update,
        };
    },
};
</script>
