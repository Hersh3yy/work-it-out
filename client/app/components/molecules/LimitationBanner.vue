<script setup lang="ts">
import type { Limitation } from '~/types/api'

defineProps<{ limitation: Limitation; busy?: boolean }>()
defineEmits<{ resolve: [id: number] }>()

function until(date: string | null): string {
  if (!date) return ''
  return new Date(date).toLocaleDateString(undefined, { day: 'numeric', month: 'short' })
}
</script>

<template>
  <div class="card flex items-start gap-3 border-amber/40 bg-amber/10">
    <Icon name="shield" :size="22" class="mt-0.5 shrink-0 text-amber" />
    <div class="min-w-0 flex-1">
      <p class="font-display text-lg font-semibold capitalize leading-tight">{{ limitation.area_label }}</p>
      <p class="text-sm text-paper/90">{{ limitation.note }}<span v-if="limitation.expected_until"> · until {{ until(limitation.expected_until) }}</span></p>
      <p class="mt-1 text-xs text-muted">Exercises that load this area get a check in your receipt. Suggestions stay light.</p>
    </div>
    <AppButton variant="ghost" :disabled="busy" class="!min-h-9 !px-3 text-sm" @click="$emit('resolve', limitation.id)">Healed</AppButton>
  </div>
</template>
