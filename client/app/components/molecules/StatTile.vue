<script setup lang="ts">
defineProps<{ icon: string; value: string; label: string; hint?: string; tone?: 'coral' | 'teal' | 'sage' | 'amber' }>()

const toneClass = { coral: 'text-coral', teal: 'text-teal-bright', sage: 'text-sage-light', amber: 'text-amber' }
</script>

<template>
  <div class="card flex min-w-0 flex-col gap-2">
    <div class="flex items-center gap-2 text-mist">
      <Icon :name="icon" :size="18" :class="tone ? toneClass[tone] : ''" />
      <span class="text-xs font-semibold uppercase tracking-wider">{{ label }}</span>
    </div>
    <div class="tabular font-display text-3xl font-semibold leading-none">{{ value }}</div>
    <p v-if="hint" class="truncate text-xs text-muted">{{ hint }}</p>
  </div>
</template>
