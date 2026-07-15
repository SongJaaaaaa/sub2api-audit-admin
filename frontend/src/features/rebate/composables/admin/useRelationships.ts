import { computed, onBeforeUnmount, reactive, ref } from 'vue'
import { getRelationships, searchSub2Users } from '../../api/admin'
import type { RelationshipRes, UserSearchItem } from '../../types'
import { apiMessage } from '../../utils/apiError'

export function useRelationships() {
  const selectedId = ref<number>()
  const users = ref<UserSearchItem[]>([])
  const userLoading = ref(false)
  const loading = ref(false)
  const error = ref('')
  const result = ref<RelationshipRes | null>(null)
  const page = reactive({ current: 1, pageSize: 20 })
  let searchTimer: ReturnType<typeof setTimeout> | undefined

  const options = computed(() => users.value.map((user) => ({
    value: user.id,
    label: `${user.email}${user.username ? ` (${user.username})` : ''} · ID ${user.id}`,
  })))

  function queueUserSearch(keyword: string) {
    clearTimeout(searchTimer)
    if (!keyword.trim()) {
      users.value = []
      return
    }
    searchTimer = setTimeout(() => loadUsers(keyword.trim()), 300)
  }

  async function loadUsers(keyword: string) {
    userLoading.value = true
    try {
      users.value = (await searchSub2Users(keyword)).items
    } catch (err) {
      error.value = apiMessage(err, '搜索 Sub2API 用户失败')
    } finally {
      userLoading.value = false
    }
  }

  function selectUser(id?: number) {
    selectedId.value = id
    result.value = null
    error.value = ''
    page.current = 1
  }

  async function load() {
    if (!selectedId.value) return false
    loading.value = true
    error.value = ''
    try {
      const data = await getRelationships({
        user_id: selectedId.value,
        page: page.current,
        page_size: page.pageSize,
      })
      result.value = data
      page.current = data.page
      page.pageSize = data.page_size
      return true
    } catch (err) {
      result.value = null
      error.value = apiMessage(err, '读取推荐关系失败')
      return false
    } finally {
      loading.value = false
    }
  }

  async function changePage(current: number, pageSize: number) {
    page.current = current
    page.pageSize = pageSize
    await load()
  }

  onBeforeUnmount(() => clearTimeout(searchTimer))

  return {
    error,
    loading,
    options,
    result,
    selectedId,
    userLoading,
    changePage,
    load,
    queueUserSearch,
    selectUser,
  }
}
