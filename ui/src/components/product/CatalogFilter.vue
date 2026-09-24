<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAttributes } from '../../store/StoreAttributes';
import axios from '../../axiosInstance';
import useErrors from '../../store/StoreErrors';
import { catalogQuery, changeCatalogQuery } from '../../models/catalogQuery';
const route=useRoute(), router=useRouter(), attributes=useAttributes();
const categories=ref([]), search=ref(''), from=ref(''), to=ref('');
const query=computed(()=>catalogQuery(route.query));
watch(query,q=>{search.value=q.bySearchInput??'';from.value=q.priceFrom??'';to.value=q.priceTo??'';},{immediate:true});
onMounted(async()=>{attributes.fetchFacets();try{categories.value=(await axios.get('/catalog-categories')).data;}catch(e){useErrors().setErrors(e);}});
const change=patch=>router.push({query:changeCatalogQuery(route.query,patch)});
const selected=computed(()=>Object.fromEntries((query.value.byAttribute??'').split(',').filter(Boolean).map(group=>{const [key,value='']=group.split(':');return [key,value.split('|')];})));
function toggle(code,value){const next={...selected.value};const values=next[code]??[];next[code]=values.includes(value)?values.filter(v=>v!==value):[...values,value];change({byAttribute:Object.entries(next).filter(([,v])=>v.length).map(([k,v])=>k+':'+v.join('|')).join(',')});}
</script>
<template>
<details class="rounded-md border border-slate-200 bg-white p-4 space-y-4">
 <summary class="font-semibold cursor-pointer">Nájsť tovar</summary>
 <form @submit.prevent="change({bySearchInput:search})" class="space-y-2">
  <label for="catalog-search" class="text-sm">Názov alebo kód</label><input id="catalog-search" v-model="search" type="search" maxlength="200" class="w-full rounded border p-2" />
  <button class="rounded bg-blue-700 text-white px-3 py-2">Hľadať</button>
 </form>
 <label class="block text-sm">Kategória<select :value="query.byCategory??''" @change="change({byCategory:$event.target.value})" class="block w-full rounded border p-2"><option value="">Všetky kategórie</option><option v-for="c in categories" :key="c.id" :value="c.id">{{c.name}}</option></select></label>
 <details open><summary class="font-semibold cursor-pointer">Filtre</summary>
  <fieldset v-for="facet in attributes.getFacets" :key="facet.code" class="mt-3"><legend class="font-medium">{{facet.name}}</legend>
   <label v-for="value in facet.values" :key="value.id" class="flex gap-2 text-sm py-1"><input type="checkbox" :checked="(selected[facet.code]??[]).includes(value.code)" @change="toggle(facet.code,value.code)" />{{value.value}}</label>
  </fieldset>
  <label class="flex gap-2 my-4"><input type="checkbox" :checked="query.inStock==='1'" @change="change({inStock:$event.target.checked?'1':''})" />Len skladom</label>
  <form @submit.prevent="change({priceFrom:from,priceTo:to})" class="space-y-2"><p>Cena s DPH</p><div class="flex gap-2"><input v-model="from" aria-label="Cena od" placeholder="Od" type="number" min="0" step="0.01" class="w-1/2 rounded border p-2"/><input v-model="to" aria-label="Cena do" placeholder="Do" type="number" min="0" step="0.01" class="w-1/2 rounded border p-2"/></div><button class="text-blue-700">Použiť cenu</button></form>
 </details>
 <button v-if="Object.keys(query).length" @click="router.push({query:{}})" class="text-red-700">Zrušiť filtre</button>

</details>
</template>
