<script setup>
import { NAV_ITEMS } from '../../constants';


</script>

<!-- <template>
  <div class="bg-slate-700 text-slate-100 p-5 ">FOOTER</div>
</template> -->


<template>
  <div class="w-full bg-gray-800 text-gray-300 pt-2">
    <div class="md:flex justify-between container mx-auto max-w-6xl">
      <div class="m-4">
        <h3 class="font-semibold text-lg">Obecné zástavy</h3>
        <iframe src="https://player.vimeo.com/video/62613770" width="w-full" frameborder="0" webkitallowfullscreen
          mozallowfullscreen allowfullscreen></iframe>
      </div>

      <div class="m-4">
        <h3 class="font-semibold text-lg border-b-2 border-gray-500 mb-2">
          Adresa
        </h3>
        <address>
          <ul>
            <li>Gajdoš Gabriel - Reprezent</li>
            <li>Slatinská 14</li>
            <li>921 07 Bratislava</li>
            <li>Telefón : 0905 320 616</li>
            <li>
              Email :
              <a class="mail" href="mailto:obchod@zastavy-vlajky.sk">obchod(at)zastavy-vlajky.sk</a>
            </li>
          </ul>
        </address>
      </div>
      <div class="m-4">
        <h3 class="font-semibold text-lg border-b-2 border-gray-500 mb-2">
          Linky
        </h3>
        
        <ul class="footer_list">
          <li v-for="item in NAV_ITEMS" :key="item.name">
            <router-link :to="{ name: item.route }">
              {{ item.name }}
            </router-link>
          </li>


          <!-- <li v-if="!getUser.isAuth">
            <router-link :to="{ name: 'login.index' }">
              Login
            </router-link>
          </li> -->
        </ul>
      </div>
      <div class="clearfix"></div>
    </div>

    <div class="text-center border-2 border-gray-500 p-2 text-gray-400">
      <p>&copy; 2016 Všetky práva vyhradené | Design by Gabriel Gajdoš</p>
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