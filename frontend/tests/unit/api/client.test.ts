import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { apiRequest } from '../../../src/api/client'

describe('apiRequest', () => {
  beforeEach(() => {
    vi.stubGlobal('localStorage', {
      getItem: vi.fn().mockReturnValue(null),
    })
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('accepts an empty successful response', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(null, { status: 202 })))

    await expect(apiRequest<void>('/auth/email-verification/resend', { method: 'POST' })).resolves.toBeUndefined()
  })

  it('parses a successful JSON response', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(JSON.stringify({ id: 'card-id' }))))

    await expect(apiRequest<{ id: string }>('/cards/card-id')).resolves.toEqual({ id: 'card-id' })
  })
})
