<script setup lang="ts">
import type { Overview } from '~/types/api'

const props = defineProps<{ notes: Overview['notes'] }>()

const updated = computed(() => {
  if (!props.notes.updated_at) return null
  const diff = (Date.now() - new Date(props.notes.updated_at).getTime()) / 60000
  if (diff < 60) return `${Math.max(1, Math.round(diff))} min ago`
  if (diff < 60 * 24) return `${Math.round(diff / 60)} h ago`
  return new Date(props.notes.updated_at).toLocaleDateString(undefined, { day: 'numeric', month: 'short' })
})
</script>

<template>
  <section class="card" aria-labelledby="notes-title">
    <div class="mb-3 flex items-baseline justify-between gap-3">
      <h2 id="notes-title" class="flex items-center gap-2 text-xl font-semibold"><Icon name="note" :size="20" class="text-teal-bright" />Written notes</h2>
      <span v-if="updated" class="text-xs text-muted">updated {{ updated }}</span>
    </div>
    <ul v-if="notes.items.length" class="flex flex-col gap-2.5">
      <li v-for="(note, i) in notes.items" :key="i" class="flex gap-3 text-sm leading-relaxed">
        <span class="mt-2 size-1.5 shrink-0 rounded-full bg-teal-bright" aria-hidden="true" />
        <span>
          {{ note.text }}
          <span class="ml-1 text-xs text-muted" :title="note.cites.join(', ')">· {{ note.cites.includes('facts') && note.cites.length === 1 ? 'from your numbers' : `from ${note.cites.filter(c => c !== 'facts').length} message${note.cites.filter(c => c !== 'facts').length === 1 ? '' : 's'}` }}</span>
        </span>
      </li>
    </ul>
    <p v-else class="text-sm text-muted">No notes yet. They appear a few minutes after your next log, written from your own messages.</p>
  </section>
</template>
