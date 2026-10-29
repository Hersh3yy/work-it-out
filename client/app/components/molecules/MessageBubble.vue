<script setup lang="ts">
defineProps<{ text: string; source: string; sentAt: string | null }>()

function when(iso: string | null): string {
  if (!iso) return ''
  const d = new Date(iso)
  return d.toLocaleString(undefined, { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
  <div class="flex flex-col items-end gap-1">
    <p class="max-w-[85%] whitespace-pre-line rounded-2xl rounded-br-sm bg-coral px-4 py-2.5 text-sm leading-relaxed text-ink">{{ text }}</p>
    <span class="flex items-center gap-1 text-[11px] text-muted">
      <Icon :name="source === 'telegram' ? 'telegram' : 'chat'" :size="12" />
      {{ when(sentAt) }}
    </span>
  </div>
</template>
