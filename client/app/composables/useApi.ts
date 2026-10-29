import type { Overview, PagedLogs } from '~/types/api'

// One place that knows the API base URL and the bearer header.
export function useApi() {
  const config = useRuntimeConfig()
  const { token, signOut } = useAuth()

  async function request<T>(path: string, options: Parameters<typeof $fetch>[1] = {}): Promise<T> {
    try {
      return await $fetch<T>(`${config.public.apiBase}${path}`, {
        ...options,
        headers: {
          Accept: 'application/json',
          ...(token.value ? { Authorization: `Bearer ${token.value}` } : {}),
          ...(options.headers as Record<string, string> | undefined),
        },
      })
    } catch (error: unknown) {
      const status = (error as { status?: number; response?: { status?: number } }).status
        ?? (error as { response?: { status?: number } }).response?.status
      if (status === 401) {
        signOut()
        await navigateTo('/login')
      }
      throw error
    }
  }

  return {
    overview: () => request<Overview>('/me/overview'),
    logs: (page = 1) => request<PagedLogs>(`/logs?page=${page}`),
    resolveLimitation: (id: number) => request<void>(`/limitations/${id}/resolve`, { method: 'POST' }),
    magicLogin: (magicToken: string) =>
      request<{ token: string; user: { name: string } }>('/auth/magic', {
        method: 'POST',
        body: { token: magicToken, device_name: 'feetness-app' },
      }),
    logout: () => request<void>('/auth/logout', { method: 'POST' }),
  }
}
