<script setup>
import {ref} from 'vue';import axios from '../../axiosInstance';import useCheckouts from '../../store/StoreCheckouts';import {salesError} from '../../models/sales';
const props=defineProps({endpoint:String});const items=ref(null),busy=ref(false),error=ref(''),added=ref(false);const cart=useCheckouts();
async function preview(){if(busy.value)return;busy.value=true;error.value='';try{items.value=(await axios.get(props.endpoint)).data.items;added.value=false;}catch(e){error.value=salesError(e);}finally{busy.value=false;}}
function add(){if(added.value)return;cart.getlocalStorage();for(const item of items.value.filter(i=>i.available))cart.submitCartToIndex(item.cart);added.value=true;}
</script>

<!-- "contents": tlačidlo sa zaradí do riadku akcií rodiča, náhľad položiek sa zalomí pod neho na celú šírku -->
<template>
    <div class="contents">
        <button type="button" @click="preview" :disabled="busy" :aria-expanded="!!items"
            class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50">
            Zopakovať objednávku
        </button>

        <section v-if="items || error" class="basis-full rounded-md border border-gray-200 bg-white p-4 text-sm shadow-sm">
            <p v-if="error" role="alert" class="text-red-700">{{ error }}</p>
            <div v-if="items" class="space-y-3">
                <p class="text-gray-700">Skontrolujte aktuálne ceny a množstvá. Dostupné položky sa pridajú k existujúcemu košíku.</p>
                <ul>
                    <li v-for="(item, index) in items" :key="index" class="border-b border-gray-100 py-2">
                        {{ item.name }} — {{ item.variant_name }}:
                        <span v-if="!item.available" class="text-red-700">Už nie je v ponuke</span>
                        <span v-else>
                            {{ item.cart.input_order }} {{ item.cart.unit_value }}, {{ item.cart.active_price.toFixed(2) }} € / {{ item.cart.unit_value }}
                            <span v-if="item.price_changed" class="text-amber-800"> (pôvodne {{ item.old_price.toFixed(2) }} €)</span>
                            <span v-if="item.quantity_changed" class="text-amber-800"> — nové minimum odberu</span>
                        </span>
                    </li>
                </ul>
                <div class="flex flex-wrap items-center gap-4">
                    <button type="button" :disabled="added || !items.some(i => i.available)" @click="add"
                        class="rounded bg-blue-700 px-4 py-2 font-semibold text-white hover:bg-blue-800 disabled:opacity-40">
                        {{ added ? 'Pridané do košíka' : 'Pridať dostupné položky' }}
                    </button>
                    <router-link v-if="added" to="/kosik" class="font-semibold text-blue-700 underline">Prejsť do košíka</router-link>
                </div>
            </div>
        </section>
    </div>
</template>
