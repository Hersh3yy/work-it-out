<script setup lang="ts">
import type { Overview } from '~/types/api'

const props = defineProps<{ overview: Overview }>()

const f = computed(() => props.overview.facts)
const topLift = computed(() => f.value.records.strength[0] ?? null)
const weight = computed(() => f.value.body_weight.latest)
const change = computed(() => f.value.body_weight.change_kg)
const volume = computed(() => Math.round(f.value.this_week.volume_kg).toLocaleString())
const lastSession = computed(() => f.value.totals.days_since_last_session)
</script>

<template>
  <section aria-label="This week" class="grid grid-cols-2 gap-3">
    <StatTile icon="barbell" :value="`${volume} kg`" label="Volume" hint="lifted this week" tone="coral" />
    <StatTile icon="flame" :value="String(f.this_week.streak_days)" label="Streak" :hint="f.this_week.streak_days === 1 ? 'day in a row' : 'days in a row'" tone="amber" />
    <StatTile
      icon="target"
      :value="topLift ? `${topLift.max_weight_kg} kg` : '–'"
      label="Top lift"
      :hint="topLift ? `${topLift.exercise}${topLift.reps ? ` × ${topLift.reps}` : ''}` : 'log a lift to start'"
      tone="teal"
    />
    <StatTile
      icon="scale"
      :value="weight ? `${weight.kg} kg` : '–'"
      label="Body weight"
      :hint="weight ? (change ? `${change > 0 ? '+' : ''}${change} kg since ${f.body_weight.first?.date}` : `logged ${weight.date}`) : 'text your weight, e.g. 104.5kg'"
      tone="sage"
    />
    <p class="col-span-2 text-xs text-muted">
      {{ f.totals.workout_sessions }} sessions, {{ f.totals.logged_messages }} messages so far
      <span v-if="lastSession !== null"> · last session {{ lastSession === 0 ? 'today' : `${lastSession} day${lastSession === 1 ? '' : 's'} ago` }}</span>
    </p>
  </section>
</template>
