<script setup lang="ts">
defineProps<{ message: string }>()
</script>

<template>
  <p class="error-message" role="alert">{{ message }}</p>
</template>
