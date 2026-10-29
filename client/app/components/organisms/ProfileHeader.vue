<script setup lang="ts">
import type { Overview } from '~/types/api'

const props = defineProps<{ overview: Overview }>()

const goal = computed(() => props.overview.facts.entered_by_user.main_goal?.replaceAll('_', ' ') ?? null)
const days = computed(() => props.overview.facts.entered_by_user.training_days_per_week)
const score = computed(() => Math.round(props.overview.facts.this_week.weekly_score_percent))
const sessions = computed(() => props.overview.facts.this_week.sessions)

// Weekly score as a ring: circumference of r=26 is ~163.4
const dash = computed(() => `${(score.value / 100) * 163.4} 163.4`)
</script>

<template>
  <header class="flex items-center gap-4">
    <div class="relative grid size-20 shrink-0 place-items-center">
      <svg viewBox="0 0 64 64" class="absolute inset-0 size-full -rotate-90" aria-hidden="true">
        <circle cx="32" cy="32" r="26" fill="none" stroke="currentColor" stroke-width="6" class="text-ink-line" />
        <circle cx="32" cy="32" r="26" fill="none" stroke="currentColor" stroke-width="6" stroke-linecap="round" class="text-teal-bright transition-[stroke-dasharray] duration-700" :stroke-dasharray="dash" />
      </svg>
      <div class="text-center leading-none">
        <div class="tabular font-display text-xl font-semibold">{{ score }}<span class="text-xs">%</span></div>
        <div class="text-[10px] uppercase tracking-wider text-muted">week</div>
      </div>
    </div>
    <div class="min-w-0">
      <p class="eyebrow">Your profile</p>
      <h1 class="truncate text-3xl font-semibold">{{ overview.user.name }}</h1>
      <p class="mt-1 text-sm text-mist">
        <span v-if="goal" class="capitalize">{{ goal }}</span><span v-else>No goal set yet</span>
        <span class="mx-1.5 text-muted">·</span>
        <span v-if="days">{{ sessions }} of {{ days }} training days this week</span>
        <span v-else>training days not set</span>
      </p>
    </div>
  </header>
</template>
