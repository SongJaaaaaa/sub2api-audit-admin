import { computed, ref } from 'vue'

export function useMobileList<T>(pageSize = 20) {
  const items = ref<T[]>([])
  const page = ref(0)
  const total = ref(0)
  const loading = ref(false)
  const error = ref('')
  const hasMore = computed(() => items.value.length < total.value)

  async function load(fetchPage: (page: number, pageSize: number) => Promise<{ items: T[]; total: number }>, append = false) {
    if (loading.value) return
    loading.value = true
    error.value = ''
    const nextPage = append ? page.value + 1 : 1
    try {
      const result = await fetchPage(nextPage, pageSize)
      items.value = (append ? [...items.value, ...result.items] : result.items) as T[]
      page.value = nextPage
      total.value = result.total
    } catch (cause) {
      error.value = cause instanceof Error ? cause.message : '加载失败，请重试'
    } finally {
      loading.value = false
    }
  }

  function reset() {
    items.value = []
    page.value = 0
    total.value = 0
    error.value = ''
  }

  return { items, page, total, loading, error, hasMore, load, reset }
}
