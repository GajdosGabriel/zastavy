<script setup>
import BaseLayout from '../layout/BaseLayout.vue';
import { onMounted, ref } from "vue";
import useProducts from "../../store/StoreProducts";
import paginationComponent from "../plugins/pagination.vue";
import filterPanel from "./FilterPanel.vue";
import tableRowProduct from "./components/tableRowProduct.vue";
import PageHeader from '../layout/page/pageHeader.vue';
import PageBottom from '../layout/page/pageBottom.vue';
import spinnerTable from '../icons/spinnerTable.vue';
import loadingStore from '../../store/StoreLoading';

const {
      state, fetchProducts, setUrl, getProducts
} = useProducts();

const quickMark = ref([]);

const quickMarkSum = () => {
      return quickMark.value.reduce((acumulator, item) => acumulator + (item.sale_price * item.quantity), null);
};

onMounted(() => {
      fetchProducts();
});

const paginatorUrl = (url) => {
      setUrl(url);
};

const onClickRowComponent = (product) => {
      product.quickMark = product.quickMark = !product.quickMark;

      // Remove or add product from array of list
      if (quickMark.value.includes(product)) {
            quickMark.value.splice(quickMark.value.findIndex(a => a.id === product.id), 1);
      } else {
            quickMark.value.push(product);
      }
}

const template = (product) => {
      return {
            ...product,
            page_header: {
                  title: 'Zoznam tovaru',
                  buttonLink: { name: 'Nový produkt', spinner: true, link: '/products/create', icon: 'plus' }
            },
            page_bottom: {
                  // buttonLink: { name: 'Späť', spinner: true, link: 'products.index', icon: 'arrow-left' },
                  // buttonSubmit: { name: 'Uložiť', spinner: true, link: 'products.create', icon: 'plus' }
            },
            buttonLink: state.buttonLink,
            quickMark: quickMark.value.includes(product)
      }
}


</script>

<template>

      <BaseLayout>

            <template #main>

                  <div class="page-body col-span-12">

                        <PageHeader :item="template().page_header" />

                        <filter-panel :quickMark="quickMark" :quickMarkSum="quickMarkSum()" />

                        <table class="min-w-full divide-y divide-gray-200">
                              <thead class="thead">
                                    <tr>
                                          <th scope="col" class="thead_th flex items-center">
                                                Produkt
                                          </th>
                                          <th scope="col" class="thead_th">ClipBoard</th>
                                          <th scope="col" class="thead_th">Cena</th>
                                          <th scope="col" class="thead_th">
                                                Publikované
                                          </th>
                                          <th scope="col" class="thead_th">Zľava</th>
                                          <th scope="col" class="thead_th">Sklad</th>
                                          <th scope="col" class="thead_th">
                                                <span>Panel</span>
                                          </th>
                                    </tr>
                              </thead>
                              <tbody class="bg-white divide-y divide-gray-200">
                                    <spinnerTable v-if="loadingStore.isLoading" />
                                    <table-row-product v-else v-for="product in getProducts"
                                          :product="template(product)" :key="product.id"
                                          @checkmark="onClickRowComponent(product)" />
                              </tbody>
                        </table>

                        <PageBottom :item="template().page_bottom" />

                        <pagination-component @setUrl="paginatorUrl" />
                  </div>

            </template>
      </BaseLayout>
</template>
