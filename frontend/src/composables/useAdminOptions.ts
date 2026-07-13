import { onMounted, ref } from 'vue'
import { getAdminOptions, type AdminOption } from '../api/admin'
import { useAuthStore } from '../stores/auth'

export function useAdminOptions() {
  const auth = useAuthStore()
  const options = ref<AdminOption[]>(auth.admin ? [auth.admin] : [])

  onMounted(async () => {
    try {
      const res = await getAdminOptions()
      options.value = res.items
    } catch {
      // 筛选项加载失败不影响页面主数据。
    }
  })

  return options
}
