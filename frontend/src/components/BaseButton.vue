<script setup lang="ts">
withDefaults(defineProps<{
  type?: 'button' | 'submit'
  disabled?: boolean
  variant?: 'primary' | 'secondary' | 'danger'
}>(), {
  type: 'button',
  disabled: false,
  variant: 'primary',
})
</script>

<template>
  <button class="button" :class="`button--${variant}`" :type="type" :disabled="disabled"><slot /></button>
</template>
