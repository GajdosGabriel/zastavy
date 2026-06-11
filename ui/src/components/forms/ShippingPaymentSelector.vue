<script setup>
import { onMounted } from 'vue';
import { formatDecimal } from '../../models/functions';
import useCheckoutOptions from '../../store/StoreCheckoutOptions';

const props = defineProps({
    cartTotal: { type: Number, default: 0 },
});

const {
    getShippingMethods,
    getPaymentMethods,
    getSelectedShipping,
    getSelectedPayment,
    getCouponMode,
    getCouponData,
    getCouponError,
    getCouponCode,
    isCouponLoading,
    shippingPrice,
    paymentFee,
    discountAmount,
    fetchShippingMethods,
    fetchPaymentMethods,
    selectShipping,
    selectPayment,
    setCouponMode,
    setCouponCode,
    validateCoupon,
    clearCoupon,
    state,
} = useCheckoutOptions();

onMounted(async () => {
    await Promise.all([fetchShippingMethods(), fetchPaymentMethods()]);
});

const computedShippingPrice = () => shippingPrice(props.cartTotal);
const computedTotal = () => {
    const total = props.cartTotal + computedShippingPrice() + paymentFee.value - discountAmount.value;
    return Math.max(0, total);
};

const onValidateCoupon = () => validateCoupon(props.cartTotal);
</script>

<template>
    <div class="space-y-5">

        <!-- Doprava -->
        <div v-if="getShippingMethods.length">
            <h3 class="mb-2 text-sm font-semibold text-gray-700">Spôsob dopravy</h3>
            <div class="space-y-2">
                <label
                    v-for="method in getShippingMethods"
                    :key="method.id"
                    :class="[
                        'flex cursor-pointer items-center justify-between rounded-lg border px-4 py-3 transition',
                        state.selectedShippingId === method.id
                            ? 'border-blue-500 bg-blue-50 ring-1 ring-blue-500'
                            : 'border-gray-200 bg-white hover:border-gray-300'
                    ]"
                >
                    <div class="flex items-center gap-3">
                        <input
                            type="radio"
                            :value="method.id"
                            :checked="state.selectedShippingId === method.id"
                            @change="selectShipping(method.id)"
                            class="accent-blue-600"
                        />
                        <span class="text-sm font-medium text-gray-800">{{ method.name }}</span>
                    </div>
                    <span class="text-sm font-semibold text-gray-900">
                        <template v-if="method.free_from_price !== null && cartTotal >= parseFloat(method.free_from_price)">
                            <span class="text-green-600">Zdarma</span>
                        </template>
                        <template v-else>
                            {{ formatDecimal(method.price) }} €
                        </template>
                    </span>
                </label>
            </div>
            <p v-if="getSelectedShipping?.free_from_price !== null && cartTotal < parseFloat(getSelectedShipping?.free_from_price ?? 0)" class="mt-1.5 text-xs text-gray-400">
                Doprava zdarma od {{ formatDecimal(getSelectedShipping.free_from_price) }} €
            </p>
        </div>

        <!-- Platba -->
        <div v-if="getPaymentMethods.length">
            <h3 class="mb-2 text-sm font-semibold text-gray-700">Spôsob platby</h3>
            <div class="space-y-2">
                <label
                    v-for="method in getPaymentMethods"
                    :key="method.id"
                    :class="[
                        'flex cursor-pointer items-center justify-between rounded-lg border px-4 py-3 transition',
                        state.selectedPaymentId === method.id
                            ? 'border-blue-500 bg-blue-50 ring-1 ring-blue-500'
                            : 'border-gray-200 bg-white hover:border-gray-300'
                    ]"
                >
                    <div class="flex items-center gap-3">
                        <input
                            type="radio"
                            :value="method.id"
                            :checked="state.selectedPaymentId === method.id"
                            @change="selectPayment(method.id)"
                            class="accent-blue-600"
                        />
                        <span class="text-sm font-medium text-gray-800">{{ method.name }}</span>
                    </div>
                    <span class="text-sm font-semibold" :class="method.fee > 0 ? 'text-gray-700' : 'text-green-600'">
                        {{ method.fee > 0 ? `+ ${formatDecimal(method.fee)} €` : 'Zdarma' }}
                    </span>
                </label>
            </div>
        </div>

        <!-- Kupón -->
        <div>
            <h3 class="mb-2 text-sm font-semibold text-gray-700">Zľavový kupón</h3>
            <!-- Uplatnený kupón -->
            <div v-if="getCouponData" class="flex items-center justify-between rounded-lg border border-green-200 bg-green-50 px-4 py-2.5">
                <div>
                    <span class="text-sm font-semibold text-green-700">{{ getCouponData.code }}</span>
                    <span class="ml-2 text-xs text-green-600">
                        −{{ getCouponData.type === 'percent' ? `${getCouponData.value}%` : `${formatDecimal(getCouponData.value)} €` }}
                    </span>
                </div>
                <button type="button" @click="clearCoupon(); setCouponMode(null)" class="text-xs text-gray-400 hover:text-red-600">Odstrániť</button>
            </div>

            <!-- Výber -->
            <div v-else class="space-y-2">
                <label :class="[
                    'flex cursor-pointer items-center gap-3 rounded-lg border px-4 py-3 transition',
                    getCouponMode === 'get'
                        ? 'border-blue-500 bg-blue-50 ring-1 ring-blue-500'
                        : 'border-gray-200 bg-white hover:border-gray-300'
                ]">
                    <input type="radio" name="coupon_mode" value="get"
                        :checked="getCouponMode === 'get'"
                        @change="setCouponMode('get')"
                        class="accent-blue-600" />
                    <span class="text-sm font-medium text-gray-800">Získaj kupón</span>
                </label>
                <p v-if="getCouponMode === 'get'" class="px-1 text-xs text-gray-400">
                    Kupón bude zaslaný pre ďalší nákup.
                </p>

                <label :class="[
                    'flex cursor-pointer items-center gap-3 rounded-lg border px-4 py-3 transition',
                    getCouponMode === 'have'
                        ? 'border-blue-500 bg-blue-50 ring-1 ring-blue-500'
                        : 'border-gray-200 bg-white hover:border-gray-300'
                ]">
                    <input type="radio" name="coupon_mode" value="have"
                        :checked="getCouponMode === 'have'"
                        @change="setCouponMode('have')"
                        class="accent-blue-600" />
                    <span class="text-sm font-medium text-gray-800">Mám kupón</span>
                </label>
                <div v-if="getCouponMode === 'have'" class="flex gap-2">
                    <input
                        type="text"
                        :value="getCouponCode"
                        @input="setCouponCode($event.target.value)"
                        @keyup.enter="onValidateCoupon"
                        placeholder="Zadajte kód kupóna"
                        class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm uppercase focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    />
                    <button
                        type="button"
                        :disabled="isCouponLoading || !getCouponCode.trim()"
                        @click="onValidateCoupon"
                        class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-900 disabled:bg-gray-400"
                    >
                        {{ isCouponLoading ? '...' : 'Uplatniť' }}
                    </button>
                </div>
                <p v-if="getCouponError" class="text-xs text-red-600">{{ getCouponError }}</p>
            </div>
        </div>

        <!-- Rekapitulácia ceny -->
        <div class="rounded-lg border border-gray-100 bg-gray-50 px-4 py-4 space-y-2 text-sm">
            <div class="flex justify-between text-gray-600">
                <span>Produkty</span>
                <span class="font-medium text-gray-800">{{ formatDecimal(cartTotal) }} €</span>
            </div>
            <div v-if="getSelectedShipping" class="flex justify-between text-gray-600">
                <span>Doprava ({{ getSelectedShipping.name }})</span>
                <span class="font-medium" :class="computedShippingPrice() === 0 ? 'text-green-600' : 'text-gray-800'">
                    {{ computedShippingPrice() === 0 ? 'Zdarma' : `${formatDecimal(computedShippingPrice())} €` }}
                </span>
            </div>
            <div v-if="paymentFee > 0" class="flex justify-between text-gray-600">
                <span>Poplatok za platbu</span>
                <span class="font-medium text-gray-800">{{ formatDecimal(paymentFee) }} €</span>
            </div>
            <div v-if="discountAmount > 0" class="flex justify-between text-green-700">
                <span>Zľava ({{ getCouponData?.code }})</span>
                <span class="font-semibold">−{{ formatDecimal(discountAmount) }} €</span>
            </div>
            <div class="border-t border-gray-200 pt-2 flex justify-between">
                <span class="font-semibold text-gray-900">Celkom s DPH</span>
                <span class="text-lg font-bold text-blue-700">{{ formatDecimal(computedTotal()) }} €</span>
            </div>
        </div>
    </div>
</template>
