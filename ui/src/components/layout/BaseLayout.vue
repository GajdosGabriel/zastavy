<script setup>
import NavBarBottom from '../bars/NavBarBottom.vue';
import NavBarTop from '../bars/NavBarTop.vue';
import MainBar from '../bars/MainBar.vue';
import navigationMain from './navigationMain.vue';
import footerComponent from './footer.vue';
import ErrorPanel from '../plugins/ErrorPanel.vue';
import spinner from '../icons/spinnerTable.vue';
import loadingStore from '../../store/StoreLoading';


</script>
<template>

  <div class="bg-slate-200 ">

    <main>

      <NavBarTop />

      <navigationMain />

      <NavBarBottom />


      <div class="container mx-auto bg-slate-100 min-h-screen relative">
        <MainBar />


        <!-- <spinner size="50px" v-if="loadingStore.isLoading" /> -->

        <article class="md:grid grid-cols-12">
          <div class="col-span-12 mt-6">
            <ErrorPanel />
          </div>
          <slot name="main"></slot>
        </article>

      </div>

      <footerComponent />

    </main>
  </div>
</template>
