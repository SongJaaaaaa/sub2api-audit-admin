<script setup lang="ts">
import { LockOutlined, UserOutlined } from '@ant-design/icons-vue'
import { message } from 'ant-design-vue'
import { isAxiosError } from 'axios'
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const loading = ref(false)
const form = reactive({
  account: '',
  password: '',
})

async function submit() {
  loading.value = true
  try {
    await auth.login(form.account, form.password)
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/'
    router.replace(redirect)
  } catch (err) {
    if (isAxiosError(err) && err.response?.status === 422) {
      message.error('账号或密码错误')
    } else {
      message.error('登录请求失败，请确认后端服务已启动')
    }
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
        <p>管理员登录</p>
      </div>
      <a-form layout="vertical" :model="form" @finish="submit">
        <a-form-item name="account" label="账号" :rules="[{ required: true, message: '请输入账号' }]">
          <a-input v-model:value="form.account" size="large" autocomplete="username" placeholder="用户名或管理员邮箱">
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
