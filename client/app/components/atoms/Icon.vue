<script setup lang="ts">
// One symbol from /sprite.svg. The sprite is one request for every icon.
withDefaults(defineProps<{ name: string; size?: number | string; label?: string }>(), { size: 20 })
</script>

<template>
  <svg
    :width="size"
    :height="size"
    :aria-hidden="label ? undefined : 'true'"
    :role="label ? 'img' : undefined"
    :aria-label="label"
    class="inline-block shrink-0 align-middle"
  >
    <use :href="`/sprite.svg#${name}`" />
  </svg>
</template>
