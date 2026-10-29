<script setup lang="ts">
withDefaults(defineProps<{ tone?: 'coral' | 'teal' | 'sage' | 'amber' | 'muted' }>(), { tone: 'muted' })

const tones = {
  coral: 'bg-coral/15 text-coral border-coral/30',
  teal: 'bg-teal/20 text-teal-bright border-teal/40',
  sage: 'bg-sage/25 text-sage-light border-sage/40',
  amber: 'bg-amber/15 text-amber border-amber/30',
  muted: 'bg-mist/10 text-mist border-mist/20',
}
</script>

<template>
  <span class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-semibold" :class="tones[tone]">
    <slot />
  </span>
</template>
