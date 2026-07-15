export function apiMessage(error: unknown, fallback: string) {
  return (error as { response?: { data?: { message?: string } } }).response?.data?.message || fallback
}
