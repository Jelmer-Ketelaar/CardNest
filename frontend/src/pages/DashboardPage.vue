<script setup lang="ts">
import { useRouter } from 'vue-router'
import BaseButton from '../components/BaseButton.vue'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()

async function logout(): Promise<void> {
  await auth.logout()
  await router.push({ name: 'login' })
}
</script>

<template>
  <main class="dashboard">
    <nav><strong>CardNest</strong><BaseButton @click="logout">Uitloggen</BaseButton></nav>
    <section class="panel">
      <p class="eyebrow">Dashboard</p>
      <h1>Je verzameling begint hier.</h1>
      <p>Het fundament staat klaar. Catalogus- en collectiefuncties volgen in afzonderlijke modules.</p>
    </section>
  </main>
</template>
