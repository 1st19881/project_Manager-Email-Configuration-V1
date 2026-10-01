<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import AppNavbar from './components/AppNavbar.vue'
import { menuItems, resolveMenuItem } from '@/navigation'

const hash = ref(window.location.hash)
const currentItem = computed(() => resolveMenuItem(hash.value))

function syncHash(): void {
  hash.value = window.location.hash
}

onMounted(() => window.addEventListener('hashchange', syncHash))
onUnmounted(() => window.removeEventListener('hashchange', syncHash))
</script>

<template>
  <div class="app-shell">
    <AppNavbar :items="menuItems" :current="currentItem.key" />

    <main>
      <component :is="currentItem.view" :key="currentItem.key" />
    </main>
  </div>
</template>

<style scoped>
.app-shell { width: 100%; }
</style>
