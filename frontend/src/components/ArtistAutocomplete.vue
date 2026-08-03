<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'

import { apiRequest } from '../api/client'
import type { ArtistSummary } from '../types/catalog'

interface ArtistSuggestionsResponse {
  items: ArtistSummary[]
}

const props = withDefaults(defineProps<{
  id: string
  label: string
  modelValue: string
  debounceMs?: number
  minimumQueryLength?: number
}>(), {
  debounceMs: 250,
  minimumQueryLength: 2,
})

const emit = defineEmits<{
  select: [artist: ArtistSummary]
  'update:modelValue': [value: string]
}>()

const wrapper = ref<HTMLElement | null>(null)
const input = ref<HTMLInputElement | null>(null)
const suggestions = ref<ArtistSummary[]>([])
const activeIndex = ref(-1)
const isOpen = ref(false)
const loading = ref(false)
const hasSearched = ref(false)
const error = ref('')
const searchedQuery = ref('')
let requestVersion = 0
let suggestionTimer: ReturnType<typeof setTimeout> | null = null
let blurTimer: ReturnType<typeof setTimeout> | null = null

const listboxId = computed(() => `${props.id}-suggestions`)
const helpId = computed(() => `${props.id}-help`)
const activeOptionId = computed(() => {
  if (!isOpen.value || activeIndex.value < 0) {
    return undefined
  }

  return `${props.id}-option-${activeIndex.value}`
})
const panelVisible = computed(() => (
  isOpen.value
  && (loading.value || error.value !== '' || hasSearched.value || suggestions.value.length > 0)
))

const clearSuggestionTimer = (): void => {
  if (suggestionTimer !== null) {
    clearTimeout(suggestionTimer)
    suggestionTimer = null
  }
}

const closeSuggestions = (): void => {
  isOpen.value = false
  activeIndex.value = -1
}

const loadSuggestions = async (query: string, version: number): Promise<void> => {
  loading.value = true
  error.value = ''
  hasSearched.value = false
  searchedQuery.value = query

  try {
    const response = await apiRequest<ArtistSuggestionsResponse>(
      `/artists/suggestions?q=${encodeURIComponent(query)}&limit=10`,
    )
    if (version !== requestVersion) {
      return
    }

    suggestions.value = response.items
    hasSearched.value = true
    activeIndex.value = response.items.length > 0 ? 0 : -1
  } catch {
    if (version !== requestVersion) {
      return
    }

    suggestions.value = []
    hasSearched.value = true
    activeIndex.value = -1
    error.value = 'Artist-suggesties konden niet worden geladen.'
  } finally {
    if (version === requestVersion) {
      loading.value = false
    }
  }
}

const scheduleSuggestions = (value: string): void => {
  const query = value.trim()
  requestVersion += 1
  const version = requestVersion
  clearSuggestionTimer()
  loading.value = false
  error.value = ''
  hasSearched.value = false
  activeIndex.value = -1

  if (query.length < props.minimumQueryLength) {
    suggestions.value = []
    closeSuggestions()

    return
  }

  suggestions.value = []
  isOpen.value = true
  suggestionTimer = setTimeout(() => {
    suggestionTimer = null
    void loadSuggestions(query, version)
  }, props.debounceMs)
}

const handleInput = (event: Event): void => {
  const value = (event.target as HTMLInputElement).value
  emit('update:modelValue', value)
  scheduleSuggestions(value)
}

const handleFocus = (): void => {
  if (suggestions.value.length > 0 || hasSearched.value || error.value !== '') {
    isOpen.value = true
  }
}

const selectArtist = (artist: ArtistSummary): void => {
  requestVersion += 1
  clearSuggestionTimer()
  suggestions.value = []
  hasSearched.value = false
  error.value = ''
  closeSuggestions()
  emit('select', artist)
  input.value?.focus()
}

const moveActiveOption = (direction: 1 | -1): void => {
  if (suggestions.value.length === 0) {
    return
  }

  isOpen.value = true
  const nextIndex = activeIndex.value + direction
  if (nextIndex < 0) {
    activeIndex.value = suggestions.value.length - 1
  } else if (nextIndex >= suggestions.value.length) {
    activeIndex.value = 0
  } else {
    activeIndex.value = nextIndex
  }
}

const handleKeydown = (event: KeyboardEvent): void => {
  if (event.key === 'ArrowDown') {
    event.preventDefault()
    moveActiveOption(1)

    return
  }

  if (event.key === 'ArrowUp') {
    event.preventDefault()
    moveActiveOption(-1)

    return
  }

  if (event.key === 'Enter' && isOpen.value && activeIndex.value >= 0) {
    event.preventDefault()
    const artist = suggestions.value[activeIndex.value]
    if (artist !== undefined) {
      selectArtist(artist)
    }

    return
  }

  if (event.key === 'Escape' && isOpen.value) {
    event.preventDefault()
    closeSuggestions()
  }
}

const handleFocusOut = (): void => {
  if (blurTimer !== null) {
    clearTimeout(blurTimer)
  }

  blurTimer = setTimeout(() => {
    blurTimer = null
    if (wrapper.value?.contains(document.activeElement) !== true) {
      closeSuggestions()
    }
  }, 0)
}

onBeforeUnmount(() => {
  requestVersion += 1
  clearSuggestionTimer()
  if (blurTimer !== null) {
    clearTimeout(blurTimer)
  }
})
</script>

<template>
  <div ref="wrapper" class="field artist-autocomplete" @focusout="handleFocusOut">
    <label class="artist-autocomplete__label" :for="id">{{ label }}</label>
    <div class="artist-autocomplete__control">
      <svg class="artist-autocomplete__search-icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24">
        <circle cx="10.5" cy="10.5" r="6.25" />
        <path d="m15.25 15.25 4.5 4.5" />
      </svg>
      <input
        :id="id"
        ref="input"
        :value="modelValue"
        type="search"
        autocomplete="off"
        placeholder="Zoek op naam, bijvoorbeeld Arita"
        :aria-activedescendant="activeOptionId"
        aria-autocomplete="list"
        :aria-controls="listboxId"
        :aria-describedby="helpId"
        :aria-expanded="panelVisible"
        role="combobox"
        spellcheck="false"
        @focus="handleFocus"
        @input="handleInput"
        @keydown="handleKeydown"
      >

      <div v-if="panelVisible" :id="listboxId" class="artist-autocomplete__panel" role="listbox">
        <div v-if="loading" class="artist-autocomplete__message" role="option" aria-disabled="true">
          <span class="artist-autocomplete__spinner" aria-hidden="true" />
          Artists zoeken…
        </div>
        <div v-else-if="error" class="artist-autocomplete__message artist-autocomplete__message--error" role="option" aria-disabled="true">
          <span role="alert">{{ error }}</span>
        </div>
        <div v-else-if="suggestions.length === 0" class="artist-autocomplete__message" role="option" aria-disabled="true">
          Geen artists gevonden voor “{{ searchedQuery }}”.
        </div>
        <template v-else>
          <button
            v-for="(artist, index) in suggestions"
            :id="`${id}-option-${index}`"
            :key="artist.id"
            class="artist-autocomplete__option"
            :class="{ 'artist-autocomplete__option--active': index === activeIndex }"
            type="button"
            role="option"
            :aria-selected="index === activeIndex"
            tabindex="-1"
            @click="selectArtist(artist)"
            @pointerdown.prevent
            @pointerenter="activeIndex = index"
          >
            <span class="artist-autocomplete__monogram" aria-hidden="true">{{ artist.name.slice(0, 1) }}</span>
            <span class="artist-autocomplete__option-text">
              <strong>{{ artist.name }}</strong>
              <small>{{ artist.cardCount }} {{ artist.cardCount === 1 ? 'kaart' : 'kaarten' }}</small>
            </span>
            <span class="artist-autocomplete__option-arrow" aria-hidden="true">→</span>
          </button>
        </template>
      </div>
    </div>

    <p :id="helpId" class="visually-hidden">
      Typ minimaal {{ minimumQueryLength }} tekens en kies een illustrator.
    </p>
  </div>
</template>
