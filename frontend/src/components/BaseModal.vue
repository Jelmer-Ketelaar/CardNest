<script setup lang="ts">
// Vendor
import { nextTick, onMounted, onUnmounted, ref } from 'vue'

const { headingId, panelClass = '' } = defineProps<{
  headingId: string
  panelClass?: string
}>()

const emit = defineEmits<{
  close: []
}>()

const focusableSelector = [
  'a[href]',
  'button:not([disabled])',
  'input:not([disabled]):not([type="hidden"])',
  'select:not([disabled])',
  'textarea:not([disabled])',
  '[contenteditable="true"]',
  '[tabindex]:not([tabindex="-1"])',
].join(',')

const previouslyFocusedElement = document.activeElement instanceof HTMLElement
  ? document.activeElement
  : null

const dialog = ref<HTMLElement | null>(null)

const focusableElements = (): HTMLElement[] => {
  if (dialog.value === null) {
    return []
  }

  return Array.from(dialog.value.querySelectorAll<HTMLElement>(focusableSelector)).filter((element) => {
    return element.closest('[hidden], [inert], [aria-hidden="true"]') === null
  })
}

const focusInitialControl = (): void => {
  const elements = focusableElements()
  const autofocusElement = elements.find((element) => element.hasAttribute('autofocus'))
  const target = autofocusElement ?? elements[0] ?? dialog.value

  target?.focus()
}

const requestClose = (): void => {
  emit('close')
}

const handleKeydown = (event: KeyboardEvent): void => {
  if (event.key === 'Escape') {
    event.preventDefault()
    event.stopPropagation()
    requestClose()

    return
  }

  if (event.key !== 'Tab') {
    return
  }

  const elements = focusableElements()
  if (elements.length === 0) {
    event.preventDefault()
    dialog.value?.focus()

    return
  }

  const firstElement = elements[0]
  const lastElement = elements[elements.length - 1]
  const activeElement = document.activeElement

  if (event.shiftKey && (activeElement === firstElement || !dialog.value?.contains(activeElement))) {
    event.preventDefault()
    lastElement?.focus()

    return
  }

  if (!event.shiftKey && (activeElement === lastElement || !dialog.value?.contains(activeElement))) {
    event.preventDefault()
    firstElement?.focus()
  }
}

onMounted(() => {
  void nextTick(focusInitialControl)
})

onUnmounted(() => {
  if (previouslyFocusedElement === null || !previouslyFocusedElement.isConnected) {
    return
  }

  void nextTick(() => previouslyFocusedElement.focus())
})
</script>

<template>
  <div class="modal-backdrop" @click.self="requestClose" @keydown="handleKeydown">
    <section
      ref="dialog"
      class="panel"
      :class="panelClass"
      role="dialog"
      aria-modal="true"
      :aria-labelledby="headingId"
      tabindex="-1"
    >
      <slot />
    </section>
  </div>
</template>
