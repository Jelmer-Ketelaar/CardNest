<script setup lang="ts">
defineProps<{
  id: string
  label: string
  modelValue: string
  type?: 'text' | 'email' | 'password'
  autocomplete?: string
  error?: string
}>()

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()
</script>

<template>
  <label class="field" :for="id">
    <span>{{ label }}</span>
    <input
      :id="id"
      :value="modelValue"
      :type="type ?? 'text'"
      :autocomplete="autocomplete"
      :aria-invalid="Boolean(error)"
      :aria-describedby="error ? `${id}-error` : undefined"
      @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
    />
    <span v-if="error" :id="`${id}-error`" class="field-error">{{ error }}</span>
  </label>
</template>
