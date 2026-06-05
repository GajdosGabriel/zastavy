<script setup>
import BaseLayout from "../layout/BaseLayout.vue";
import { onMounted, watch } from "vue";
import useCheckouts from "../../store/StoreCheckouts";
import useCustomers from "../../store/StoreCustomers";
import useOrders from "../../store/StoreOrders";
import useUsers from "../../store/StoreUsers";
import router from "../../router";
import search from "../icons/search.vue";


const {
      state,
      removeCart,
      storeCheckout,
      getCarts,
      getCheckout,
      getlocalStorage,
      resetCarts,
} = useCheckouts();

const { getCustomer, setCustomer, findCustomerByIco, } = useCustomers();
const { state: order, getOrder } = useOrders();
const { getUser } = useUsers();


onMounted(() => {
      getlocalStorage();

      if (getCarts.value.length) {
            setCustomer(JSON.parse(localStorage.getItem('customer')) || {});
      }

});

const clickEmptyBasket = () => {
      if (!window.confirm("Skutočne vyprázniť košík!")) {
            return;
      }
      resetCarts();
};


const onClickIco = () => {
      findCustomerByIco();
};

const onClickForm = async () => {
      if (!state.carts.length) {
            return alert("Objednávka je prázdna!");
      }
      await storeCheckout();

      // if (!getErrors) {
      router.push({ name: "public.thankYouForOrder.show" });
      // }
};

</script>

<template>
      <BaseLayout>
            <template #main>
                  <h1 class="page-heading">Váš košík</h1>
                  <div class="page-body col-span-9">
                        <table class="border-4 border-blue-900 mb-6 w-full">
                              <thead>
                                    <tr class="bg-gray-200">
                                          <th class="p-2">Tovar</th>
                                          <th class="text-left">Cena</th>
                                          <th class="text-left">Množstvo</th>
                                          <th class="text-center w-24">Spolu</th>
                                          <th class="text-center"></th>
                                    </tr>
                              </thead>
                              <tbody>
                                    <tr v-for="product in getCarts" :key="product.id"
                                          class="border-b-2 border-gray-500">
                                          <td class="p-4">
                                                <div class="flex">
                                                      <div
                                                            class="mr-3 w-12 h-12 overflow-hidden rounded-full border-2 border-gray-200">
                                                            <img :src="product.thumb" :alt="product.name" />
                                                      </div>
                                                      <div>
                                                            <h4 class="font-semibold">
                                                                  {{ product.name }}
                                                            </h4>
                                                            <p class="text-xs">
                                                                  {{ product.description.substring(0, 25) }}
                                                            </p>
                                                      </div>
                                                </div>
                                          </td>
                                          <td class="whitespace-nowrap">
                                                {{ product.active_price + " €" }}
                                          </td>
                                          <td>
                                                <span class="text-center font-semibold">
                                                      <input type="number" v-model="product.input_order"
                                                            class="rounded w-16" :min="product.min_order" required />
                                                      {{ product.unit_value }}
                                                </span>
                                          </td>
                                          <td class="text-center whitespace-nowrap">
                                                {{ product.active_price * product.input_order }} €
                                          </td>

                                          <td class="actions">
                                                <form @submit.prevent="removeCart(product)">
                                                      <button class="btn btn-default">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4"
                                                                  fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                                                  stroke-width="2">
                                                                  <path stroke-linecap="round" stroke-linejoin="round"
                                                                        d="M6 18L18 6M6 6l12 12" />
                                                            </svg>
                                                      </button>
                                                </form>
                                          </td>
                                    </tr>
                                    <tr v-if="!getCarts.length" class="border-b-2 border-gray-500">
                                          <td colspan="4" class="p-4 font-semibold text-center">
                                                V košík je prázdny.
                                          </td>
                                    </tr>
                              </tbody>
                              <tfoot>
                                    <tr>
                                          <!-- {{-- <td class="text-center"><strong>{{ $sum }}</strong></td> --}} -->
                                    </tr>
                                    <tr>
                                          <td>
                                                <router-link :to="{ name: 'public.index' }">
                                                      <div
                                                            class="mb-2 mt-3 text-gray-900 nav_link bg-gray-300 rounded-lg  ml-2">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4"
                                                                  fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                  <path stroke-linecap="round" stroke-linejoin="round"
                                                                        stroke-width="2" d="M15 19l-7-7 7-7" />
                                                            </svg>
                                                            {{ getCarts.length ?
                                                                  'Pokračovat v nákupe' :
                                                                  'Začnite nakupovať' }}
                                                      </div>
                                                </router-link>
                                          </td>
                                          <td class="hidden-xs"></td>
                                          <td class="hidden-xs">{{ getCheckout.grandQuantity }} ks</td>
                                          <td class="hidden-xs text-center font-semibold whitespace-nowrap">
                                                {{ getCheckout.grandTotal }},- €
                                          </td>
                                          <td class="hidden-xs text-right p-3">
                                                <button class="btn btn-default" @click="clickEmptyBasket"
                                                      v-if="getCarts.length">
                                                      <svg xmlns="http://www.w3.org/2000/svg" fill="none"
                                                            viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                                            class="w-4 h-4">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                  d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                                      </svg>
                                                </button>
                                          </td>
                                    </tr>
                              </tfoot>
                        </table>

                        <div class="rounded-sm">
                              <h2 class="font-semibold text-lg text-center p-2 mb-2 bg-gray-500 text-gray-100">
                                    Vyplňte fakturačné údaje
                              </h2>
                              <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4 w-full lg:w-8/12 md:w-12/12">

                                    <div class="mb-4">
                                          <label class="block text-gray-700 text-sm font-bold mb-2" for="company">
                                                Názov <span class="text-red-700 font-semibold text-lg">*</span>
                                          </label>
                                          <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                                id="company" type="text" v-model="getCustomer.company" ref="companyRef"
                                                required placeholder="Názov firmy" />
                                    </div>
                                    <div class="md:grid justify-items-stretch grid-cols-3 gap-5">
                                          <div>
                                                <label class="block text-gray-700 text-sm font-bold mb-2" for="street">
                                                      Adresa <span class="text-red-700 font-semibold text-lg ">*</span>
                                                </label>
                                                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                                      id="street" type="text" v-model="getCustomer.street"
                                                      placeholder="Adresa a číslo" required />
                                          </div>

                                          <div>
                                                <label class="block text-gray-700 text-sm font-bold mb-2"
                                                      for="postcode">
                                                      PSČ <span class="text-red-700 font-semibold text-lg ">*</span>
                                                </label>
                                                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                                      id="postcode" type="text" v-model="getCustomer.postcode"
                                                      placeholder="Poštové smerové číslo" required />
                                          </div>

                                          <div>
                                                <label class="block text-gray-700 text-sm font-bold mb-2" for="city">
                                                      Mesto <span class="text-red-700 font-semibold text-lg ">*</span>
                                                </label>
                                                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                                      id="city" type="text" v-model="getCustomer.city"
                                                      placeholder="Meslo" required />
                                          </div>

                                          <div>
                                                <label class="block text-gray-700 text-sm font-bold mb-2" for="email">
                                                      Email <span class="text-red-700 font-semibold text-lg ">*</span>
                                                </label>
                                                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                                      id="email" type="email" v-model="getCustomer.email" required
                                                      placeholder="Email na zaslanie objednávky" />
                                          </div>

                                          <div>
                                                <label class="block text-gray-700 text-sm font-bold mb-2" for="name">
                                                      Kontaktné meno <span
                                                            class="text-red-700 font-semibold text-lg ">*</span>
                                                </label>
                                                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                                      id="name" type="text" v-model="getCustomer.name" required
                                                      placeholder="Meno objednávateľa" />
                                          </div>
                                          <div>
                                                <label class="block text-gray-700 text-sm font-bold mb-2" for="phone">
                                                      Telefón <span class="text-red-700 font-semibold text-lg ">*</span>
                                                </label>
                                                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                                      id="phone" type="text" required v-model="getCustomer.phone"
                                                      placeholder="Telefón" />
                                          </div>

                                          <div>
                                                <div class="flex justify-between">
                                                      <label class="block text-gray-700 text-sm font-bold mb-2"
                                                            for="ico">
                                                            ICO
                                                      </label>
                                                      <search @click="onClickIco" class="cursor-pointer" />
                                                </div>
                                                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                                      id="ico" type="text" v-model="getCustomer.ico"
                                                      placeholder="IČO organizácie" />
                                          </div>

                                          <div>
                                                <label class="block text-gray-700 text-sm font-bold mb-2" for="dic">
                                                      DIC
                                                </label>
                                                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                                      id="dic" type="text" v-model="getCustomer.dic"
                                                      placeholder="DIČ organizácie" />
                                          </div>
                                          <div>
                                                <label class="block text-gray-700 text-sm font-bold mb-2" for="ic_dic">
                                                      SKDIC
                                                </label>
                                                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                                      id="ic_dic" type="text" v-model="getCustomer.ic_dic"
                                                      placeholder="SKDIČ organizácie" />
                                          </div>
                                    </div>

                                    <div class="my-4">

                                          <label class="block text-gray-700 text-sm font-bold mb-2" for="notice">
                                                Poznámka
                                          </label>
                                          <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                                id="notice" type="text" v-model="getOrder.notice"
                                                placeholder="Poznámka" />

                                    </div>

                                    <div class="text-right">
                                          <button @click="onClickForm"
                                                class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                                Odoslať objednávku
                                          </button>
                                    </div>
                              </div>
                        </div>
                  </div>
            </template>
      </BaseLayout>
</template>
