<script setup lang="ts">
import BaseLayout from '../layout/BaseLayout.vue';


</script>

<template>

      <BaseLayout>
            <!-- Content for the default slot -->
            <template #main>
                  <div class="p-3 order-first">
                        <div class="md:grid grid-cols-2 gap-8">
                              Stránka sa nenašla
                        </div>
                  </div>
            </template>

      </BaseLayout>

</template>
