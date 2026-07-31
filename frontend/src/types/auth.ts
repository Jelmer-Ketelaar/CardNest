export interface User {
  id: string
  email: string
  roles: string[]
  emailVerifiedAt: string | null
  createdAt: string
}

export interface AuthResponse {
  user: User
  token: string
}
