<script setup lang="ts">
import type { ActivityLog, PagedLogs } from '~/types/api'

const api = useApi()
const logs = ref<ActivityLog[]>([])
const page = ref(1)
const lastPage = ref(1)
const loading = ref(false)
const failed = ref(false)

async function load(next = 1) {
  loading.value = true
  failed.value = false
  try {
    const result: PagedLogs = await api.logs(next)
    logs.value = next === 1 ? result.data : [...logs.value, ...result.data]
    page.value = result.meta.current_page
    lastPage.value = result.meta.last_page
  } catch {
    failed.value = true
  } finally {
    loading.value = false
  }
}

await load()
</script>

<template>
  <AppShell>
    <LogTimeline :logs="logs" title="Every message" />
    <p v-if="failed" class="card text-sm text-coral">Couldn't load your messages.</p>
    <div v-if="page < lastPage" class="flex justify-center">
      <AppButton variant="ghost" :disabled="loading" @click="load(page + 1)">{{ loading ? 'Loading…' : 'Older messages' }}</AppButton>
    </div>
  </AppShell>
</template>
