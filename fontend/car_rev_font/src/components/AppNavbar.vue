<script setup lang="ts">
import AppIcon from './AppIcon.vue'
import type { MenuItem } from '@/navigation'

defineProps<{
  items: readonly MenuItem[]
  current: string
}>()
</script>

<template>
  <nav class="navbar" aria-label="เมนูหลัก">
    <a class="brand" href="#/">
      <img src="/summit-logo.png" alt="" aria-hidden="true" />
      <span>Car Booking Notification</span>
    </a>

    <ul class="menu">
      <li v-for="item in items" :key="item.key">
        <a
          :href="`#${item.path}`"
          :class="{ active: item.key === current }"
          :aria-current="item.key === current ? 'page' : undefined"
        >
          <AppIcon :name="item.icon" :size="17" />
          <span>{{ item.label }}</span>
        </a>
      </li>
    </ul>
  </nav>
</template>

<style scoped>
.navbar { align-items: center; background: rgba(255,255,255,.94); border: 1px solid #e5eaf1; border-radius: 20px; box-shadow: 0 12px 34px rgba(26,45,80,.07); display: flex; flex-wrap: wrap; gap: 1rem; justify-content: space-between; margin-bottom: 1.75rem; padding: .7rem 1.1rem; }
.brand { align-items: center; color: #14213d; display: flex; font-weight: 750; gap: .6rem; letter-spacing: -.015em; min-width: 0; }
.brand img { height: 34px; object-fit: contain; width: 36px; }
.brand span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.menu { display: flex; flex-wrap: wrap; gap: .35rem; list-style: none; margin: 0; padding: 0; }
.menu a { align-items: center; border-radius: 12px; color: #475569; display: flex; gap: .45rem; font-size: .9rem; font-weight: 650; padding: .55rem .95rem; transition: background .15s, color .15s; }
.menu a:hover { background: #f1f5f9; color: #14213d; }
.menu a.active { background: #eaf2ff; color: #1d4ed8; }
@media (max-width: 720px) {
  .navbar { align-items: stretch; flex-direction: column; gap: .75rem; padding: .7rem .6rem; }
  .brand { padding: 0 .5rem; }
  .brand span { white-space: normal; }
  /* Fixed 2 columns so the four items sit in a 2x2 block: auto-fit could
     never place enough 150px tracks on a phone and left one item stranded
     alone on a half-width last row. */
  .menu { grid-template-columns: repeat(2, minmax(0, 1fr)); display: grid; }
  .menu li { display: grid; }
  .menu a { align-content: center; background: #f8fafc; display: grid; font-size: .85rem; gap: .25rem; justify-items: center; padding: .5rem .35rem; text-align: center; }
  .menu a.active { background: #eaf2ff; }
}
</style>
