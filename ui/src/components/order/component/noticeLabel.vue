<template>
    <div v-if="!shipping" class="flex justify-between">
        <select v-model="notifyType" id="notify" class="mx-4">
            <option :value="''">Oznámenie</option>
            <option value="email">Email</option>
            <option disabled value="sms">SMS</option>
        </select>
    </div>

    <div v-if="shipping" class="flex">

        <button @click="onClickShipping(shipping)"
            class="hover:bg-blue-500 hover:text-gray-200 px-2 rounded-md border-2 bg-white">
            {{ shipping.id }}

        </button>

        <div v-for="notice in shipping.notices" :key="notice.id" class="ml-2 text-center ">
            <div class="label lable-green" :title="notice.created_at">
                <icon-email />
                <!--   {{ notice.notice }} -->
                <div style="font-size: .7rem;">
                    {{ notice.created_at_human }}
                </div>
            </div>
        </div>

    </div>
</template>

<script>
import { ref } from "vue";
import useNotices from "../../../store/StoreNotices";
import useOrders from "../../../store/StoreOrders";
import iconEmail from "../../../components/icons/email.vue";

export default {
    props: ["shipping"],
    components: { iconEmail },
    setup() {
        const { storeShippingNotice } = useNotices();
        const { fetchOrders } = useOrders();
        const notifyType = ref("email");

        const onClickShipping = async (shipping) => {

            if (shipping.notices.length) {
                alert('Notifikácia už bola zaslaná.')
                return
            }
            if (
                !window.confirm(
                    shipping
                        ? "Poslať notifikáciu?"
                        : "Poslať ďalšiu notifikáciu?"
                )
            ) {
                return;
            }
            await storeShippingNotice(shipping, {
                notifyType: notifyType.value,
            });
            await fetchOrders()
        };

        return {
            onClickShipping,
            notifyType,
        };
    },
};
</script>
