// Without a token, every page sends you to /login. The login page itself is
// open, so a sign-in link from Telegram can land there.
export default defineNuxtRouteMiddleware((to) => {
  if (to.path === '/login') return
  const { isSignedIn } = useAuth()
  if (!isSignedIn.value) return navigateTo('/login')
})
