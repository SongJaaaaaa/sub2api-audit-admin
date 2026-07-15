<script setup lang="ts">
import { CheckOutlined } from '@ant-design/icons-vue'

const steps = [
  { mark: '1', label: '用户提交申请' },
  { mark: '2', label: '管理员审核' },
  { mark: '3', label: '队列转入额度' },
]
</script>

<template>
  <section class="withdrawFlow" aria-label="提现处理流程">
    <template v-for="step in steps" :key="step.mark">
      <div class="flowStep"><i>{{ step.mark }}</i><span>{{ step.label }}</span></div>
      <b aria-hidden="true">→</b>
    </template>
    <div class="flowStep done"><i><CheckOutlined /></i><span>完成</span></div>
  </section>
</template>

<style scoped>
.withdrawFlow {
  display: flex;
  min-height: 50px;
  padding: 12px 16px;
  align-items: center;
  gap: 20px;
  overflow: hidden;
  border: 1px solid #d9e7fb;
  border-radius: 8px;
  background: #edf5ff;
  color: #0758cf;
}
.flowStep { display: inline-flex; min-width: 0; align-items: center; gap: 8px; font-size: 13px; white-space: nowrap; }
.flowStep i { display: inline-flex; width: 20px; height: 20px; flex: 0 0 20px; align-items: center; justify-content: center; border-radius: 50%; background: #cfe3ff; font-style: normal; font-size: 11px; }
.flowStep.done i { background: #bcefcf; color: #079447; }
.withdrawFlow b { color: #73a9ef; font-weight: 400; }

@media (max-width: 760px) {
  .withdrawFlow { display: grid; min-height: 90px; padding: 12px; grid-template-columns: 1fr auto 1fr auto 1fr auto 1fr; gap: 5px; }
  .flowStep { flex-direction: column; gap: 4px; font-size: 11px; line-height: 14px; text-align: center; white-space: normal; }
  .flowStep span { max-width: 52px; }
}
</style>
