<script setup lang="ts">
import type { Entry } from '~/types/api'

// Every set as its own chip, so a 90/80/80/80/80 squat reads as it happened.
const props = defineProps<{ entry: Entry }>()

const chips = computed(() => {
  const e = props.entry
  if (e.set_list.length) {
    return e.set_list.map((s) => `${s.weight_kg ?? '?'}×${s.reps ?? '?'}`)
  }
  const parts: string[] = []
  if (e.sets && e.reps) parts.push(`${e.sets}×${e.reps}`)
  if (e.weight_kg) parts.push(`${e.weight_kg} kg`)
  if (e.distance_meters) parts.push(`${(e.distance_meters / 1000).toFixed(e.distance_meters % 1000 ? 1 : 0)} km`)
  if (e.duration_seconds) parts.push(duration(e.duration_seconds))
  return parts
})

const topWeight = computed(() => Math.max(0, ...props.entry.set_list.map((s) => s.weight_kg ?? 0)))

function duration(seconds: number): string {
  const m = Math.floor(seconds / 60)
  const s = seconds % 60
  return s ? `${m}:${String(s).padStart(2, '0')}` : `${m} min`
}
</script>

<template>
  <ul class="flex flex-wrap gap-1.5" :aria-label="`${entry.exercise_name} sets`">
    <li
      v-for="(chip, i) in chips"
      :key="i"
      class="tabular rounded-lg border px-2 py-1 text-sm"
      :class="entry.set_list.length && entry.set_list[i]?.weight_kg === topWeight && topWeight > 0
        ? 'border-coral/50 bg-coral/10 text-coral'
        : 'border-ink-line bg-ink/40 text-paper'"
    >
      {{ chip }}
    </li>
  </ul>
</template>
