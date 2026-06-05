<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

const props = defineProps({
    items: {
        type: Array,
        default: () => []
    }
})

const open = ref(false)
const id = Math.random().toString(36).substring(2, 10)

const toggle = () => {
    open.value = !open.value
    if (open.value) {
        emitDropdownOpened(id)
    }
}

const close = () => {
    open.value = false
}

const emitDropdownOpened = (id) => {
    window.dispatchEvent(new CustomEvent('dropdown-opened', { detail: id }))
}

const handleDropdownOpened = (e) => {
    const otherId = e.detail
    if (otherId !== id) {
        close()
    }
}

const handleClickOutside = (e) => {
    if (!e.target.closest(`[data-dropdown-id="${id}"]`)) {
        close()
    }
}

onMounted(() => {
    window.addEventListener('click', handleClickOutside)
    window.addEventListener('dropdown-opened', handleDropdownOpened)
    document.addEventListener('keyup', (e) => {
        if (e.key === 'Escape') close()
    })
})

onUnmounted(() => {
    window.removeEventListener('click', handleClickOutside)
    window.removeEventListener('dropdown-opened', handleDropdownOpened)
})
</script>

<template>
    <div class="relative" :data-dropdown-id="id">
        <button @click="toggle" class="flex items-center p-2 rounded-md transition-colors" :class="[
            !items.length
                ? 'text-gray-400 bg-gray-500 cursor-not-allowed'
                : open
                    ? 'text-white bg-indigo-700 hover:bg-indigo-600'
                    : 'text-indigo-100 bg-gray-700 hover:bg-gray-700'
        ]" :disabled="!items.length">
            <svg class="w-5 h-5 text-indigo-100 dark:text-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                fill="currentColor">
                <path fill-rule="evenodd"
                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                    clip-rule="evenodd" />
            </svg>
        </button>

        <Transition enter-active-class="duration-300 ease-out" enter-from-class="transform opacity-0 scale-75"
            enter-to-class="opacity-100 scale-100" leave-active-class="duration-200 ease-in"
            leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-75">
            <div v-show="open" class="z-10 absolute right-0 mt-2 bg-white shadow rounded w-44 border border-gray-300">
                <div v-if="items.length">
                    <div v-for="(item, index) in items" :key="index">
                        <!-- Ak má položka definovaný 'to', je to router-link -->
                        <router-link v-if="item.to" :to="item.to"
                            class="block cursor-pointer p-2 hover:bg-indigo-300 border-b-2 border-gray-200">
                            {{ item.label }}
                        </router-link>

                        <!-- Ak má položka 'onClick', je to akcia -->
                        <div v-else-if="item.onClick" @click="item.onClick"
                            class="cursor-pointer p-2 hover:bg-indigo-300 border-b-2 border-gray-200">
                            {{ item.label }}
                        </div>

                        <!-- Pre istotu fallback -->
                        <div v-else class="p-2 text-gray-500">
                            {{ item.label }}
                        </div>
                    </div>
                </div>
                <div v-else class="p-2 text-gray-500">Žiadne položky</div>
            </div>
        </Transition>
    </div>
</template>
