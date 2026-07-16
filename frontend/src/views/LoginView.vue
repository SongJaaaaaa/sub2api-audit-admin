<script setup lang="ts">
import { LockOutlined, UserOutlined } from '@ant-design/icons-vue'
import { isAxiosError } from 'axios'
import { computed, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAffiliateAuthStore } from '../features/rebate/stores/affiliateAuth'
import { useAuthStore } from '../stores/auth'

type LoginMode = 'admin' | 'affiliate'

const route = useRoute()
const router = useRouter()
const adminAuth = useAuthStore()
const affiliateAuth = useAffiliateAuthStore()
const loading = ref(false)
const error = ref('')
const mode = ref<LoginMode>(route.query.mode === 'affiliate' ? 'affiliate' : 'admin')
const form = reactive({
  account: '',
  password: '',
})

const modeOptions = [
  { label: '管理员登录', value: 'admin' },
  { label: '推广用户登录', value: 'affiliate' },
]
const description = computed(() => mode.value === 'admin' ? '审计管理员登录' : 'Sub2API 普通用户登录')
const accountPlaceholder = computed(() => mode.value === 'admin' ? '用户名或管理员邮箱' : 'Sub2API 用户邮箱')

function changeMode(value: LoginMode) {
  mode.value = value
  error.value = ''
  router.replace({
    query: {
      ...route.query,
      mode: value === 'affiliate' ? 'affiliate' : undefined,
    },
  })
}

function errorMessage(err: unknown) {
  const status = isAxiosError(err) ? err.response?.status : undefined
  if (mode.value === 'affiliate' && (status === 401 || status === 422)) return 'Sub2API 账号或密码错误'
  if (mode.value === 'admin' && status === 422) return '管理员账号或密码错误'
  if (isAxiosError(err) && typeof err.response?.data?.message === 'string') return err.response.data.message
  return '登录请求失败，请确认后端服务已启动'
}

async function submit() {
  loading.value = true
  error.value = ''
  try {
    const account = form.account.trim()
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : ''
    if (mode.value === 'affiliate') {
      await affiliateAuth.login(account, form.password)
      await router.replace(redirect.startsWith('/affiliate/') ? redirect : '/affiliate/dashboard')
    } else {
      await adminAuth.login(account, form.password)
      await router.replace(redirect.startsWith('/') && !redirect.startsWith('/affiliate/') ? redirect : '/')
    }
  } catch (err) {
    error.value = errorMessage(err)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="loginPage">
    <section class="loginPanel">
      <div class="loginHead">
        <h1>Sub2API 审计后台</h1>
        <p>{{ description }}</p>
      </div>
      <a-segmented
        class="loginMode"
        block
        :value="mode"
        :options="modeOptions"
        @change="changeMode"
      />
      <a-alert v-if="error" class="loginError" type="error" show-icon :message="error" />
      <a-form layout="vertical" :model="form" @finish="submit">
        <a-form-item name="account" label="账号" :rules="[{ required: true, message: '请输入账号' }]">
          <a-input v-model:value="form.account" size="large" autocomplete="username" :placeholder="accountPlaceholder">
            <template #prefix><UserOutlined /></template>
          </a-input>
        </a-form-item>
        <a-form-item name="password" label="密码" :rules="[{ required: true, message: '请输入密码' }]">
          <a-input-password v-model:value="form.password" size="large" autocomplete="current-password" placeholder="密码">
            <template #prefix><LockOutlined /></template>
          </a-input-password>
        </a-form-item>
        <a-button block type="primary" html-type="submit" size="large" :loading="loading">
          登录
        </a-button>
      </a-form>
    </section>
  </main>
</template>

<style scoped>
.loginMode { margin-bottom: 20px; }
.loginError { margin-bottom: 18px; }
</style>
