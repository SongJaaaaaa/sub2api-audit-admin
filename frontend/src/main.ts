import { createApp } from 'vue'
import Antd from 'ant-design-vue'
import dayjs from 'dayjs'
import 'dayjs/locale/zh-cn'
import { createPinia } from 'pinia'
import 'ant-design-vue/dist/reset.css'
import './style.css'
import App from './App.vue'
import { router } from './router'
import { assertAppApiConfig } from './app/services/platform'
import { useAuthStore } from './stores/auth'

dayjs.locale('zh-cn')

async function bootstrap() {
  assertAppApiConfig()
  const pinia = createPinia()
  const app = createApp(App)
  const auth = useAuthStore(pinia)
  await auth.hydrate()
  window.addEventListener('auth-expired', () => {
    if (router.currentRoute.value.name !== 'login') {
      void router.replace({ name: 'login', query: { redirect: router.currentRoute.value.fullPath } })
    }
  })
  app.use(pinia).use(router).use(Antd).mount('#app')
}

void bootstrap()
