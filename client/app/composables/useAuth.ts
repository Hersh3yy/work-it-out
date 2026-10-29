// The signed-in state: a bearer token from a /login link, kept in this
// browser only. Everything reads it through here.
const TOKEN_KEY = 'feetness.token'

const token = ref<string | null>(null)
let loaded = false

function readToken(): string | null {
  try {
    return localStorage.getItem(TOKEN_KEY)
  } catch {
    return null
  }
}

export function useAuth() {
  if (!loaded && import.meta.client) {
    token.value = readToken()
    loaded = true
  }

  const isSignedIn = computed(() => token.value !== null && token.value !== '')

  function signIn(newToken: string) {
    token.value = newToken
    try {
      localStorage.setItem(TOKEN_KEY, newToken)
    } catch {
      // Private window or blocked storage: the session lasts until reload.
    }
  }

  function signOut() {
    token.value = null
    try {
      localStorage.removeItem(TOKEN_KEY)
    } catch {
      // nothing to clear
    }
  }

  return { token, isSignedIn, signIn, signOut }
}
