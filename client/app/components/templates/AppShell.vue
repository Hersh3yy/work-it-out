<script setup lang="ts">
const route = useRoute()
const { signOut } = useAuth()
const api = useApi()

async function logout() {
  try {
    await api.logout()
  } catch {
    // the token may already be gone; signing out locally is what matters
  }
  signOut()
  await navigateTo('/login')
}

const tabs = [
  { to: '/', icon: 'home', label: 'Home' },
  { to: '/logs', icon: 'list', label: 'Logs' },
]
</script>

<template>
  <div class="mx-auto flex min-h-full w-full max-w-2xl flex-col px-4">
    <div class="flex items-center justify-between py-4">
      <NuxtLink to="/" class="focus-ring flex items-center gap-2 rounded-full" aria-label="Feetness home">
        <Icon name="logo" :size="32" />
        <span class="font-display text-xl font-semibold tracking-wide">Feetness</span>
      </NuxtLink>
      <button type="button" class="focus-ring rounded-full px-3 py-1 text-sm text-muted hover:text-paper" @click="logout">Sign out</button>
    </div>

    <main class="flex flex-1 flex-col gap-6 pb-28">
      <slot />
    </main>

    <nav
      class="fixed inset-x-0 bottom-0 border-t border-ink-line bg-ink/90 backdrop-blur"
      :style="{ paddingBottom: 'env(safe-area-inset-bottom, 0px)' }"
      aria-label="Main"
    >
      <ul class="mx-auto flex max-w-2xl">
        <li v-for="tab in tabs" :key="tab.to" class="flex-1">
          <NuxtLink
            :to="tab.to"
            class="focus-ring flex flex-col items-center gap-1 py-3 text-xs font-semibold uppercase tracking-wider"
            :class="route.path === tab.to ? 'text-coral' : 'text-muted'"
            :aria-current="route.path === tab.to ? 'page' : undefined"
          >
            <Icon :name="tab.icon" :size="22" />
            {{ tab.label }}
          </NuxtLink>
        </li>
      </ul>
    </nav>
  </div>
</template>
