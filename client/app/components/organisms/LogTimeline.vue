<script setup lang="ts">
import type { ActivityLog } from '~/types/api'

defineProps<{ logs: ActivityLog[]; title?: string }>()

function day(date: string): string {
  const d = new Date(`${date}T12:00:00`)
  const today = new Date()
  const diff = Math.round((new Date(today.toDateString()).getTime() - new Date(d.toDateString()).getTime()) / 86400000)
  if (diff === 0) return 'Today'
  if (diff === 1) return 'Yesterday'
  return d.toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'short' })
}

const typeTone = { workout: 'teal', biometrics: 'sage', general: 'amber', meal: 'muted' } as const
const typeLabel = { workout: 'Workout', biometrics: 'Body weight', general: 'Note', meal: 'Meal' } as const
</script>

<template>
  <section aria-labelledby="timeline-title" class="flex flex-col gap-3">
    <h2 id="timeline-title" class="flex items-center gap-2 text-xl font-semibold"><Icon name="chat" :size="20" class="text-coral" />{{ title ?? 'What you sent' }}</h2>
    <p v-if="!logs.length" class="card text-sm text-muted">Nothing logged yet. Text the bot something like “bench 3x8 80”.</p>
    <article v-for="log in logs" :key="log.id" class="card flex flex-col gap-3 rise">
      <div class="flex items-center justify-between gap-2">
        <span class="text-xs font-semibold uppercase tracking-wider text-mist">{{ day(log.logged_on) }}</span>
        <Badge :tone="log.limitation ? 'amber' : typeTone[log.log_type]">{{ log.limitation ? 'Limitation' : typeLabel[log.log_type] }}</Badge>
      </div>
      <MessageBubble :text="log.message" :source="log.source" :sent-at="log.sent_at" />
      <div class="rounded-2xl rounded-bl-sm border border-ink-line bg-ink/40 px-4 py-3">
        <p class="text-xs font-semibold uppercase tracking-wider text-teal-bright">Saved as</p>
        <p class="mt-1 text-sm">{{ log.summary }}</p>
        <ul v-if="log.entries.length" class="mt-3 flex flex-col gap-3">
          <li v-for="entry in log.entries" :key="entry.id" class="flex flex-col gap-1.5">
            <div class="flex items-baseline justify-between gap-2">
              <span class="font-display text-base font-semibold">{{ entry.exercise_name }}</span>
              <span v-if="entry.volume_kg" class="tabular text-xs text-muted">{{ Math.round(entry.volume_kg).toLocaleString() }} kg total</span>
            </div>
            <SetChips :entry="entry" />
          </li>
        </ul>
        <p v-if="log.open_question" class="mt-3 flex items-start gap-2 text-sm text-amber"><Icon name="arrow" :size="16" class="mt-0.5" />{{ log.open_question }}</p>
      </div>
    </article>
  </section>
</template>
