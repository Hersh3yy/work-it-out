<script setup lang="ts">
import type { Overview } from '~/types/api'

const api = useApi()
const { data, pending, error, refresh } = await useAsyncData<Overview>('overview', () => api.overview())
const busy = ref(false)

async function heal(id: number) {
  busy.value = true
  try {
    await api.resolveLimitation(id)
    await refresh()
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <AppShell>
    <template v-if="pending && !data">
      <div class="flex items-center gap-4"><Skeleton class="size-20 !rounded-full" /><div class="flex-1 space-y-2"><Skeleton class="h-4 w-24" /><Skeleton class="h-8 w-40" /><Skeleton class="h-4 w-56" /></div></div>
      <div class="grid grid-cols-2 gap-3"><Skeleton v-for="i in 4" :key="i" class="h-28" /></div>
    </template>

    <p v-else-if="error" class="card text-sm text-coral">Couldn't load your profile. Pull to refresh or sign in again.</p>

    <template v-else-if="data">
      <ProfileHeader :overview="data" />

      <LimitationBanner v-for="limitation in data.limitations" :key="limitation.id" :limitation="limitation" :busy="busy" @resolve="heal" />

      <WeekStats :overview="data" />

      <NotesPanel :notes="data.notes" />

      <LogTimeline :logs="data.recent_logs" title="Recent messages" />

      <NuxtLink to="/logs" class="focus-ring self-center rounded-full text-sm font-semibold text-teal-bright">All messages <Icon name="arrow" :size="14" /></NuxtLink>
    </template>
  </AppShell>
</template>
