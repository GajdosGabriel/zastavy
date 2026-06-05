<template>
    <!-- Settings Dropdown -->
    <div v-if="getUser?.isAuth" class="relative right" width="48">
        <button @click.stop="isShow"
            class="flex items-center text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out">
            <div>{{ getUser.firstName }}</div>

            <div class="ml-1">
                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                    <path fill-rule="evenodd"
                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                        clip-rule="evenodd" />
                </svg>
            </div>
        </button>
        <Transition enter-active-class="duration-300 ease-out" enter-from-class="transform opacity-0 scale-75"
            enter-to-class=" opacity-100 scale-100" leave-active-class="transform duration-200 ease-in"
            leave-from-class=" opacity-100 scale-100" leave-to-class="opacity-0 scale-75">
            <div v-if="show" class="absolute right-0 mt-2 py-2 w-48 bg-white rounded-md shadow-xl z-20">
                <router-link :to="{ name: 'stocks.index' }"
                    class="block px-4 py-2 text-sm capitalize text-gray-700 hover:bg-blue-500 hover:text-white">
                    Stock
                </router-link>


                <a href="#" @click="logoutUser"
                    class="block px-4 py-2 text-sm capitalize text-gray-700 hover:bg-blue-500 hover:text-white">
                    Odhlásiť
                </a>
            </div>
        </Transition>
    </div>

    <!-- Hamburger -->
    <div class="-mr-2 flex items-center sm:hidden">
        <!-- <button
            @click="isShow"
            class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out"
        >
            <svg
                class="h-6 w-6"
                stroke="currentColor"
                fill="none"
                viewBox="0 0 24 24"
            >
                <path
                    :class="{ hidden: show, 'inline-flex': !show }"
                    class="inline-flex"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M4 6h16M4 12h16M4 18h16"
                />
                <path
                    :class="{ hidden: !show, 'inline-flex': show }"
                    class="hidden"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M6 18L18 6M6 6l12 12"
                />
            </svg>
        </button> -->
    </div>
</template>
<script>
import { ref } from "vue";
import router from "../../router";
import useUser from "../../store/StoreUsers";

export default {
    created: function () {
        let self = this;

        window.addEventListener("click", function (e) {
            // close dropdown when clicked outside
            if (!self.$el.contains(e.target)) {
                self.show = false;
            }
        });

        document.addEventListener("keyup", function (evt) {
            if (evt.keyCode === 27) {
                self.show = false;
            }
        });
    },
    setup() {
        const { user, logout, getUser } = useUser();
        const show = ref(false);
        const isShow = () => (show.value = !show.value);

        const logoutUser = async () => {
            logout();
            router.push({ name: "public.index" });
            show.value = false;
        };

        return {
            user,
            show,
            isShow,
            logoutUser,
            getUser
        };
    },
};
</script>
