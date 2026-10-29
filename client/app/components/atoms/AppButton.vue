<script setup lang="ts">
withDefaults(defineProps<{ variant?: 'primary' | 'ghost'; type?: 'button' | 'submit'; disabled?: boolean }>(), {
  variant: 'primary',
  type: 'button',
  disabled: false,
})
</script>

<template>
  <button
    :type="type"
    :disabled="disabled"
    class="focus-ring inline-flex min-h-11 items-center justify-center gap-2 rounded-full px-5 font-display text-base font-semibold tracking-wide transition active:scale-[0.98] disabled:opacity-40"
    :class="variant === 'primary' ? 'bg-coral text-ink hover:bg-coral-deep hover:text-paper' : 'border border-ink-line text-paper hover:bg-ink-soft'"
  >
    <slot />
  </button>
</template>
