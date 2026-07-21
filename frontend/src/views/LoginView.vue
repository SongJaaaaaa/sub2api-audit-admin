<script setup lang="ts">
import { LockOutlined, UserOutlined } from '@ant-design/icons-vue'
import { isAxiosError } from 'axios'
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { login } from '../api/auth'
import { useAuthStore } from '../stores/auth'
import { useAppMode } from '../app/composables/useAppMode'

const route = useRoute()
const router = useRouter()
const adminAuth = useAuthStore()
const { isAppMode } = useAppMode()
const loading = ref(false)
const error = ref('')
const form = reactive({
  account: '',
  password: '',
})

function errorMessage(err: unknown) {
  const status = isAxiosError(err) ? err.response?.status : undefined
  if (status === 401) return 'Sub2API 账号或密码错误'
  if (isAxiosError(err) && typeof err.response?.data?.message === 'string') return err.response.data.message
  return '登录请求失败，请确认后端服务已启动'
}

async function submit() {
  loading.value = true
  error.value = ''
  try {
    const account = form.account.trim()
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : ''
    const res = await login(account, form.password)
    await adminAuth.clear()
    await adminAuth.save(res.token, res.admin)
    const fallback = isAppMode.value ? '/app' : '/'
    const target = isAppMode.value && redirect === '/'
      ? fallback
      : redirect.startsWith('/') ? redirect : fallback
    await router.replace(target)
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
        <p>Sub2API 账号登录</p>
      </div>
      <a-alert v-if="error" class="loginError" type="error" show-icon :message="error" />
      <a-form layout="vertical" :model="form" @finish="submit">
        <a-form-item name="account" label="账号" :rules="[{ required: true, message: '请输入账号' }]">
          <a-input v-model:value="form.account" size="large" autocomplete="username" placeholder="Sub2API 用户邮箱">
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
.loginError { margin-bottom: 18px; }
</style>
