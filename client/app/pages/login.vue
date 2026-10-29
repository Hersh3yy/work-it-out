<script setup lang="ts">
// Lands here from a /login link sent by the Telegram bot: ?token=... is
// traded for an API token once, then the app opens.
const route = useRoute()
const config = useRuntimeConfig()
const api = useApi()
const { signIn, isSignedIn } = useAuth()

const state = ref<'idle' | 'working' | 'failed'>('idle')
const botLink = `https://t.me/${config.public.telegramBot}`

onMounted(async () => {
  const token = typeof route.query.token === 'string' ? route.query.token : null
  if (!token) {
    if (isSignedIn.value) await navigateTo('/')
    return
  }
  state.value = 'working'
  try {
    const result = await api.magicLogin(token)
    signIn(result.token)
    await navigateTo('/', { replace: true })
  } catch {
    state.value = 'failed'
  }
})
</script>

<template>
  <div class="mx-auto flex min-h-full w-full max-w-md flex-col justify-center gap-8 px-5 py-10">
    <div class="flex flex-col items-center gap-3 text-center">
      <Icon name="logo" :size="72" label="Feetness" />
      <h1 class="text-4xl font-semibold">Feetness</h1>
      <p class="text-mist">Your training log, read back to you.</p>
    </div>

    <div class="card flex flex-col gap-4">
      <template v-if="state === 'working'">
        <p class="text-center text-sm text-mist">Signing you in…</p>
      </template>
      <template v-else>
        <p v-if="state === 'failed'" class="rounded-xl border border-coral/40 bg-coral/10 px-3 py-2 text-sm text-coral">That link has expired or was already used. Ask the bot for a new one.</p>
        <h2 class="text-xl font-semibold">Sign in from Telegram</h2>
        <ol class="flex flex-col gap-3 text-sm text-paper/90">
          <li class="flex gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-teal text-xs font-bold text-paper">1</span>Open your Feetness bot in Telegram.</li>
          <li class="flex gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-teal text-xs font-bold text-paper">2</span>Send <code class="rounded bg-ink px-1.5 py-0.5 font-mono text-coral">/login</code></li>
          <li class="flex gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-teal text-xs font-bold text-paper">3</span>Tap the link it sends back. It works once, for 15 minutes.</li>
        </ol>
        <a :href="botLink" class="focus-ring inline-flex min-h-11 items-center justify-center gap-2 rounded-full bg-coral px-5 font-display font-semibold text-ink"><Icon name="telegram" :size="18" />Open the bot</a>
        <p class="text-xs text-muted">No account yet? Feetness is in private beta: ask the person who invited you for a link.</p>
      </template>
    </div>
  </div>
</template>
