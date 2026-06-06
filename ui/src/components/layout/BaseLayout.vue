<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import NavBarBottom from '../bars/NavBarBottom.vue';
import NavBarTop from '../bars/NavBarTop.vue';
import MainBar from '../bars/MainBar.vue';
import HeaderContactStrip from './HeaderContactStrip.vue';
import navigationMain from './navigationMain.vue';
import footerComponent from './footer.vue';
import spinner from '../icons/spinnerTable.vue';
import loadingStore from '../../store/StoreLoading';
import GlobalErrorPanel from '../plugins/GlobalErrorPanel.vue';

const route = useRoute();
const showHeaderContactStrip = computed(() => route.name === 'public.index');

</script>
<template>

  <div class="bg-slate-200 ">

    <main>

      <NavBarTop />

      <navigationMain />

      <HeaderContactStrip v-if="showHeaderContactStrip" />

      <NavBarBottom />


      <div class="container mx-auto bg-slate-100 min-h-screen relative">
        <MainBar />


        <!-- <spinner size="50px" v-if="loadingStore.isLoading" /> -->

        <article class="md:grid grid-cols-12">
          <GlobalErrorPanel />

          <slot name="main"></slot>
        </article>

      </div>

      <footerComponent />

    </main>
  </div>
</template>
