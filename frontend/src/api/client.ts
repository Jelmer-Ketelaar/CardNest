export interface ApiErrorPayload {
  error: {
    code: string
    message: string
    details?: Record<string, string[]>
  }
}

export class ApiError extends Error {
  public constructor(
    message: string,
    public readonly status: number,
    public readonly code: string,
    public readonly details: Record<string, string[]> = {},
  ) {
    super(message)
  }
}

const baseUrl = import.meta.env.VITE_API_BASE_URL ?? '/api/v1'

export async function apiRequest<T>(path: string, options: RequestInit = {}): Promise<T> {
  const token = localStorage.getItem('cardnest.authToken')
  const headers = new Headers(options.headers)
  headers.set('Accept', 'application/json')
  if (options.body) headers.set('Content-Type', 'application/json')
  if (token) headers.set('Authorization', `Bearer ${token}`)

  const response = await fetch(`${baseUrl}${path}`, { ...options, headers })
  if (!response.ok) {
    const payload = (await response.json().catch(() => null)) as ApiErrorPayload | null
    throw new ApiError(
      payload?.error.message ?? 'Er is een onverwachte fout opgetreden.',
      response.status,
      payload?.error.code ?? 'unexpected_error',
      payload?.error.details,
    )
  }

  if (response.status === 204) return undefined as T

  const body = await response.text()
  return body.trim() === '' ? undefined as T : JSON.parse(body) as T
}
