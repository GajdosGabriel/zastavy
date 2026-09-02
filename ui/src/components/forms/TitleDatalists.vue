<script setup>
// Navrhované tituly pre inputy prefix/postfix (input ostáva voľne editovateľný).
const prefixes = [
    "Bc.", "Mgr.", "Mgr. art.", "Ing.", "Ing. arch.", "JUDr.", "MUDr.", "MVDr.",
    "PaedDr.", "PhDr.", "PharmDr.", "RNDr.", "ThDr.", "doc.", "prof.",
];

const postfixes = ["PhD.", "ArtD.", "CSc.", "DrSc.", "MBA", "LL.M.", "MPH.", "DiS.", "DiS. art."];
</script>

<template>
    <datalist id="title-prefix-options">
        <option v-for="title in prefixes" :key="title" :value="title" />
    </datalist>
    <datalist id="title-postfix-options">
        <option v-for="title in postfixes" :key="title" :value="title" />
    </datalist>
</template>
