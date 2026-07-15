<script setup lang="ts">
import { LockOutlined, UserOutlined } from '@ant-design/icons-vue'
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAffiliateAuthStore } from '../../stores/affiliateAuth'

const route = useRoute()
const router = useRouter()
const auth = useAffiliateAuthStore()
const loading = ref(false)
const error = ref('')
const form = reactive({ account: '', password: '' })

function apiMessage(err: unknown) {
  const status = (err as { response?: { status?: number } }).response?.status
  if (status === 422 || status === 401) return 'Sub2API 账号或密码错误'
  return (err as { response?: { data?: { message?: string } } }).response?.data?.message || '登录请求失败'
}

async function submit() {
  loading.value = true
  error.value = ''
  try {
    await auth.login(form.account.trim(), form.password)
    const redirect = typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/affiliate/')
      ? route.query.redirect
      : '/affiliate/dashboard'
    router.replace(redirect)
  } catch (err) {
    error.value = apiMessage(err)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="affiliateLogin">
    <section class="affiliateLoginPanel">
      <h1>推广用户登录</h1>
      <a-alert v-if="error" class="affiliateLoginError" type="error" show-icon :message="error" />
      <a-form layout="vertical" :model="form" @finish="submit">
        <a-form-item name="account" label="Sub2API 账号" :rules="[{ required: true, message: '请输入账号' }]">
          <a-input v-model:value="form.account" size="large" autocomplete="username" placeholder="Sub2API 邮箱">
            <template #prefix><UserOutlined /></template>
          </a-input>
        </a-form-item>
        <a-form-item name="password" label="密码" :rules="[{ required: true, message: '请输入密码' }]">
          <a-input-password v-model:value="form.password" size="large" autocomplete="current-password" placeholder="密码">
            <template #prefix><LockOutlined /></template>
          </a-input-password>
        </a-form-item>
        <a-button block type="primary" html-type="submit" size="large" :loading="loading">登录</a-button>
      </a-form>
    </section>
  </main>
</template>

<style scoped>
.affiliateLoginError { margin-bottom: 18px; }
</style>
