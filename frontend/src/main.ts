import { createApp } from 'vue'
import Antd from 'ant-design-vue'
import dayjs from 'dayjs'
import 'dayjs/locale/zh-cn'
import { createPinia } from 'pinia'
import 'ant-design-vue/dist/reset.css'
import './style.css'
import App from './App.vue'
import { router } from './router'

dayjs.locale('zh-cn')

createApp(App).use(createPinia()).use(router).use(Antd).mount('#app')
