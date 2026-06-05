<template>
    <Transition
        enter-active-class="duration-300 ease-out"
        enter-from-class="transform opacity-0 scale-75"
        enter-to-class=" opacity-100 scale-100"
        leave-active-class="transform duration-200 ease-in"
        leave-from-class=" opacity-100 scale-100"
        leave-to-class="opacity-0 scale-75"
    >
        <slot></slot>
    </Transition>
</template>
