<script setup>
import { ref } from 'vue';
import { NAV_ITEMS } from '../../constants';
import { storeToRefs } from 'pinia';
import { useUsers as useUser } from '../../store/StoreUsers';

const { getUser } = storeToRefs(useUser());
const playVideo = ref(false);
</script>

<!-- <template>
  <div class="bg-slate-700 text-slate-100 p-5 ">FOOTER</div>
</template> -->


<template>
  <div class="w-full bg-gray-800 text-gray-300 pt-2">
    <div class="md:flex justify-between container mx-auto max-w-6xl">
      <div class="m-4">
        <h3 class="font-semibold text-lg">Video o nás</h3>
        <!-- Prehrávač sa načíta až po kliknutí, inak Vimeo spomaľuje každú stránku. -->
        <iframe v-if="playVideo" src="https://player.vimeo.com/video/62613770?autoplay=1" width="100%" height="200"
          frameborder="0" allow="autoplay; fullscreen" allowfullscreen title="Video o firme"></iframe>
        <button v-else type="button" @click="playVideo = true"
          class="flex h-[200px] w-full items-center justify-center rounded bg-gray-900 text-gray-200 hover:bg-gray-700"
          aria-label="Prehrať video">
          <svg viewBox="0 0 24 24" class="mr-2 h-10 w-10" fill="currentColor"><path d="M8 5v14l11-7z" /></svg>
          Prehrať video
        </button>
      </div>

      <div class="m-4">
        <h3 class="font-semibold text-lg border-b-2 border-gray-500 mb-2">
          Prevádzka a kontakt
        </h3>
        <address>
          <ul>
            <li>Gajdoš Gabriel - Reprezent</li>
            <li>Slatinská 14</li>
            <li>921 07 Bratislava</li>
            <li>Telefón : 0905 320 616</li>
            <li>
              Email :
              <a class="mail" href="mailto:obchod@zastavy-vlajky.sk">obchod@zastavy-vlajky.sk</a>
            </li>
          </ul>
        </address>
      </div>
      <div class="m-4">
        <h3 class="font-semibold text-lg border-b-2 border-gray-500 mb-2">
          Linky
        </h3>
        
        <ul class="footer_list">
          <li v-for="item in NAV_ITEMS" :key="item.NAME">
            <router-link :to="{ name: item.ROUTE }">
              {{ item.NAME }}
            </router-link>
          </li>

          <li v-if="!getUser?.isAuth">
            <router-link :to="{ name: 'public.login.index' }">
              Prihlásiť sa
            </router-link>
          </li>
        </ul>
      </div>
      <div class="clearfix"></div>
    </div>

    <div class="text-center border-2 border-gray-500 p-2 text-gray-400">
      <p>&copy; {{ new Date().getFullYear() }} Všetky práva vyhradené | Design by Gabriel Gajdoš</p>
    </div>
  </div>
</template>

<script>
// import useUser from "../../store/Users";

// export default {

//   setup() {

//     const { getUser } = useUser();

//     return {
//       getUser
//     }

//   }
// }
</script>