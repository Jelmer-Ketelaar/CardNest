export interface PaginatedResponse<T> {
  items: T[]
  page: number
  perPage: number
  total: number
  totalPages: number
}
