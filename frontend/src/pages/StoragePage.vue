<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ApiError, apiRequest } from '../api/client'
import BaseButton from '../components/BaseButton.vue'
import BaseInput from '../components/BaseInput.vue'
import ErrorMessage from '../components/ErrorMessage.vue'
import type { StorageLocation, StorageLocationCollectionResponse } from '../types/storage'

const storageTypes = [
  ['binder', 'Binder'],
  ['box', 'Box'],
  ['top_loader_box', 'Top-loaderbox'],
  ['graded_card_box', 'Graded-cardbox'],
  ['display', 'Display'],
  ['safe', 'Kluis'],
  ['other', 'Overig'],
] as const

const locations = ref<StorageLocation[]>([])
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const success = ref('')
const editing = ref<StorageLocation | null>(null)
const createForm = reactive({ name: '', type: 'other', parentId: '' })
const editForm = reactive({ name: '', type: 'other', parentId: '' })

function caughtMessage(caught: unknown, fallback: string): string {
  return caught instanceof ApiError ? caught.message : fallback
}

function typeLabel(type: string): string {
  return storageTypes.find((entry) => entry[0] === type)?.[1] ?? type
}

async function loadLocations(): Promise<void> {
  loading.value = true
  error.value = ''
  try {
    const response = await apiRequest<StorageLocationCollectionResponse>('/me/storage-locations')
    locations.value = response.items.sort((left, right) => {
      return left.path.map((entry) => entry.name).join('/').localeCompare(right.path.map((entry) => entry.name).join('/'))
    })
  } catch (caught) {
    error.value = caughtMessage(caught, 'De opslaglocaties konden niet worden geladen.')
  } finally {
    loading.value = false
  }
}

async function createLocation(): Promise<void> {
  error.value = ''
  success.value = ''
  saving.value = true
  const payload: Record<string, string> = { name: createForm.name, type: createForm.type }
  if (createForm.parentId) payload.parentId = createForm.parentId

  try {
    await apiRequest<StorageLocation>('/me/storage-locations', {
      method: 'POST',
      body: JSON.stringify(payload),
    })
    createForm.name = ''
    createForm.type = 'other'
    createForm.parentId = ''
    success.value = 'De opslaglocatie is toegevoegd.'
    await loadLocations()
  } catch (caught) {
    error.value = caughtMessage(caught, 'De opslaglocatie kon niet worden toegevoegd.')
  } finally {
    saving.value = false
  }
}

function startEdit(location: StorageLocation): void {
  editing.value = location
  editForm.name = location.name
  editForm.type = location.type
  editForm.parentId = location.parentId ?? ''
}

function closeEdit(): void {
  editing.value = null
}

async function updateLocation(): Promise<void> {
  const location = editing.value
  if (!location) return

  error.value = ''
  success.value = ''
  saving.value = true
  const payload: Record<string, boolean | string> = {
    name: editForm.name,
    type: editForm.type,
  }
  if (editForm.parentId) payload.parentId = editForm.parentId
  else payload.clearParent = true

  try {
    await apiRequest<StorageLocation>(`/me/storage-locations/${location.id}`, {
      method: 'PATCH',
      body: JSON.stringify(payload),
    })
    success.value = 'De opslaglocatie is bijgewerkt.'
    closeEdit()
    await loadLocations()
  } catch (caught) {
    error.value = caughtMessage(caught, 'De opslaglocatie kon niet worden bijgewerkt.')
  } finally {
    saving.value = false
  }
}

onMounted(() => { void loadLocations() })
</script>

<template>
  <main class="page-container storage-page">
    <header class="page-heading">
      <div>
        <p class="eyebrow">Fysieke organisatie</p>
        <h1>Opslag</h1>
        <p>Leg een hiërarchie vast van kamer tot kast en box, zodat iedere kaart terug te vinden is.</p>
      </div>
    </header>

    <ErrorMessage v-if="error" :message="error" />
    <p v-if="success" class="success-message" role="status">{{ success }}</p>

    <section class="storage-layout">
      <form class="panel storage-form" @submit.prevent="createLocation">
        <p class="eyebrow">Nieuwe plek</p>
        <h2>Opslaglocatie toevoegen</h2>
        <BaseInput id="storage-name" v-model="createForm.name" label="Naam" required />
        <label class="field">
          <span>Type</span>
          <select v-model="createForm.type" required>
            <option v-for="type in storageTypes" :key="type[0]" :value="type[0]">{{ type[1] }}</option>
          </select>
        </label>
        <label class="field">
          <span>Bovenliggende locatie</span>
          <select v-model="createForm.parentId">
            <option value="">Geen — hoofdniveau</option>
            <option v-for="location in locations" :key="location.id" :value="location.id">
              {{ location.path.map((entry) => entry.name).join(' › ') }}
            </option>
          </select>
        </label>
        <BaseButton type="submit" :disabled="saving">{{ saving ? 'Opslaan…' : 'Locatie toevoegen' }}</BaseButton>
      </form>

      <section class="panel panel--wide storage-tree" aria-labelledby="storage-tree-heading">
        <div class="storage-tree__heading">
          <div>
            <p class="eyebrow">{{ locations.length }} locaties</p>
            <h2 id="storage-tree-heading">Mijn opslagstructuur</h2>
          </div>
        </div>
        <p v-if="loading" role="status">Opslag laden…</p>
        <p v-else-if="locations.length === 0" class="empty-state">Maak je eerste opslaglocatie aan.</p>
        <ol v-else class="storage-list">
          <li v-for="location in locations" :key="location.id" :style="{ '--depth': location.path.length - 1 }">
            <div>
              <strong>{{ location.name }}</strong>
              <span>{{ typeLabel(location.type) }} · {{ location.currentItemCount }} kaarten</span>
              <small>{{ location.path.map((entry) => entry.name).join(' › ') }}</small>
            </div>
            <BaseButton variant="secondary" @click="startEdit(location)">Bewerken</BaseButton>
          </li>
        </ol>
      </section>
    </section>

    <div v-if="editing" class="modal-backdrop" @click.self="closeEdit">
      <section class="panel edit-panel" role="dialog" aria-modal="true" aria-labelledby="edit-storage-heading">
        <p class="eyebrow">Opslaghiërarchie</p>
        <h2 id="edit-storage-heading">{{ editing.name }} bewerken</h2>
        <form @submit.prevent="updateLocation">
          <BaseInput id="edit-storage-name" v-model="editForm.name" label="Naam" required />
          <label class="field">
            <span>Type</span>
            <select v-model="editForm.type" required>
              <option v-for="type in storageTypes" :key="type[0]" :value="type[0]">{{ type[1] }}</option>
            </select>
          </label>
          <label class="field">
            <span>Bovenliggende locatie</span>
            <select v-model="editForm.parentId">
              <option value="">Geen — hoofdniveau</option>
              <option v-for="location in locations.filter((candidate) => candidate.id !== editing?.id)" :key="location.id" :value="location.id">
                {{ location.path.map((entry) => entry.name).join(' › ') }}
              </option>
            </select>
          </label>
          <div class="dialog-actions">
            <BaseButton variant="secondary" @click="closeEdit">Annuleren</BaseButton>
            <BaseButton type="submit" :disabled="saving">{{ saving ? 'Opslaan…' : 'Opslaan' }}</BaseButton>
          </div>
        </form>
      </section>
    </div>
  </main>
</template>
