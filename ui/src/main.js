import { createApp } from 'vue'
import { createPinia } from 'pinia'
import './assets/style.css'
import App from './App.vue'

import router from "./router";
import { purgeLegacyStorage } from "./authSession";

purgeLegacyStorage();

const app = createApp(App)
const pinia = createPinia()

app.use(pinia).use(router).mount('#app')

// Statický <main> z index.html platí len do prvého vykreslenia routy.
router.isReady().then(() => {
    setTimeout(() => document.getElementById("static-main")?.remove(), 0);
});
