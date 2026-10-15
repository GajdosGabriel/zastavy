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
const buttonRef = ref(null)
const dropdownStyle = ref({})

const updatePosition = () => {
    if (!buttonRef.value) return
    const rect = buttonRef.value.getBoundingClientRect()
    dropdownStyle.value = {
        top: `${rect.bottom + 4}px`,
        right: `${window.innerWidth - rect.right}px`,
    }
}

const toggle = () => {
    open.value = !open.value
    if (open.value) {
        updatePosition()
        window.dispatchEvent(new CustomEvent('dropdown-opened', { detail: id }))
    }
}

const close = () => { open.value = false }

const handleDropdownOpened = (e) => {
    if (e.detail !== id) close()
}

const handleClickOutside = (e) => {
    if (!e.target.closest(`[data-dropdown-id="${id}"]`)) close()
}

const handleScrollOrResize = () => {
    if (open.value) close()
}

const handleEsc = (e) => {
    if (e.key === 'Escape') close()
}

onMounted(() => {
    window.addEventListener('click', handleClickOutside)
    window.addEventListener('dropdown-opened', handleDropdownOpened)
    window.addEventListener('scroll', handleScrollOrResize, true)
    window.addEventListener('resize', handleScrollOrResize)
    document.addEventListener('keyup', handleEsc)
})

onUnmounted(() => {
    window.removeEventListener('click', handleClickOutside)
    window.removeEventListener('dropdown-opened', handleDropdownOpened)
    window.removeEventListener('scroll', handleScrollOrResize, true)
    window.removeEventListener('resize', handleScrollOrResize)
    document.removeEventListener('keyup', handleEsc)
})
</script>

<template>
    <div :data-dropdown-id="id">
        <button
            ref="buttonRef"
            type="button"
            @click.stop="toggle"
            aria-label="Akcie"
            title="Akcie"
            aria-haspopup="menu"
            :aria-expanded="open"
            class="flex items-center p-2 rounded-md transition-colors"
            :class="[
                !items.length && !$slots.default
                    ? 'text-gray-500 bg-gray-500 cursor-not-allowed'
                    : open
                        ? 'text-white bg-indigo-700 hover:bg-indigo-600'
                        : 'text-indigo-100 bg-gray-700 hover:bg-gray-700'
            ]"
            :disabled="!items.length && !$slots.default"
        >
            <svg class="w-5 h-5 text-indigo-100 dark:text-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path d="M6 10a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM11.5 10a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM17 10a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" />
            </svg>
        </button>

        <Teleport to="body">
            <Transition
                enter-active-class="duration-150 ease-out"
                enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="duration-100 ease-in"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
            >
                <div
                    v-show="open"
                    :data-dropdown-id="id"
                    :style="dropdownStyle"
                    role="menu"
                    class="fixed z-50 w-44 rounded border border-gray-300 bg-white shadow-lg"
                >
                    <div v-if="items.length">
                        <!-- Deštruktívne akcie (danger) sú oddelené čiarou a červené, aby sa nezamenili s bežnými. -->
                        <div v-for="(item, index) in items" :key="index"
                            :class="{ 'border-t-2 border-gray-300': item.danger && index > 0 && !items[index - 1].danger }">
                            <router-link
                                v-if="item.to"
                                :to="item.to"
                                class="block cursor-pointer p-2 hover:bg-indigo-300 border-b border-gray-200"
                                :class="{ 'font-semibold text-red-700 hover:bg-red-100': item.danger }"
                            >
                                {{ item.label }}
                            </router-link>
                            <button
                                v-else-if="item.onClick"
                                type="button"
                                @click="item.onClick(); close()"
                                class="block w-full cursor-pointer p-2 text-left hover:bg-indigo-300 border-b border-gray-200"
                                :class="{ 'font-semibold text-red-700 hover:bg-red-100': item.danger }"
                            >
                                {{ item.label }}
                            </button>
                            <div v-else class="p-2 text-gray-500">{{ item.label }}</div>
                        </div>
                    </div>
                    <slot v-else-if="$slots.default"></slot>
                    <div v-else class="p-2 text-gray-500">Žiadne položky</div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
