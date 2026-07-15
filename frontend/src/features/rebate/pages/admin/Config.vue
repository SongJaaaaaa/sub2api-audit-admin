<script setup lang="ts">
import {
  ClockCircleOutlined,
  DatabaseOutlined,
  GiftOutlined,
  SaveOutlined,
  WalletOutlined,
} from '@ant-design/icons-vue'
import { message } from 'ant-design-vue'
import { onMounted, reactive, ref } from 'vue'
import { getRebateConfig, updateRebateConfig } from '../../api/admin'
import AsyncState from '../../components/AsyncState.vue'
import type { RebateConfig, RebateConfigInput } from '../../types'

const loading = ref(false)
const saving = ref(false)
const error = ref('')
const cutoverAt = ref<string | null>(null)
const updatedAt = ref<string | null>(null)
const form = reactive<RebateConfigInput>({
  milestone_amount: '100.00',
  milestone_reward_amount: '15.00',
  milestone_max_times: 2,
  stage_amount: '100.00',
  stage_reward_amount: '15.00',
  withdraw_min_amount: '2.00',
  withdraw_daily_limit: 10,
  withdraw_daily_amount_limit: '0.00',
  withdraw_to_api_quota_rate: '1.00',
  native_recharge_enabled: true,
  redeem_enabled: true,
  admin_adjust_enabled: false,
})

function apiMessage(err: unknown, fallback: string) {
  return (err as { response?: { data?: { message?: string } } }).response?.data?.message || fallback
}

function assign(data: RebateConfig) {
  Object.assign(form, {
    milestone_amount: data.milestone_amount,
    milestone_reward_amount: data.milestone_reward_amount,
    milestone_max_times: data.milestone_max_times,
    stage_amount: data.stage_amount,
    stage_reward_amount: data.stage_reward_amount,
    withdraw_min_amount: data.withdraw_min_amount,
    withdraw_daily_limit: data.withdraw_daily_limit,
    withdraw_daily_amount_limit: data.withdraw_daily_amount_limit,
    withdraw_to_api_quota_rate: data.withdraw_to_api_quota_rate,
    native_recharge_enabled: data.native_recharge_enabled,
    redeem_enabled: data.redeem_enabled,
    admin_adjust_enabled: data.admin_adjust_enabled,
  })
  cutoverAt.value = data.rebate_cutover_at
  updatedAt.value = data.updated_at
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    assign(await getRebateConfig())
  } catch (err) {
    error.value = apiMessage(err, '读取返利配置失败')
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  try {
    const res = await updateRebateConfig({ ...form })
    assign(res)
    message.success(res.message || '返利配置已保存')
  } catch (err) {
    message.error(apiMessage(err, '保存返利配置失败'))
  } finally {
    saving.value = false
  }
}

function jumpTo(id: string) {
  document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

onMounted(load)
</script>

<template>
  <div class="rebatePage configPage">
    <header class="adminPageHead">
      <div>
        <span class="pageEyebrow">配置中心</span>
        <h1>返利配置</h1>
        <p>管理一级返利、提现和事件来源规则</p>
      </div>
      <a-button class="saveButton" type="primary" :loading="saving" :disabled="loading" @click="save">
        <template #icon><SaveOutlined /></template>
        保存更改
      </a-button>
    </header>

    <AsyncState :loading="loading" :error="error" @retry="load">
      <div class="configLayout">
        <aside class="configRail">
          <nav aria-label="返利配置分区">
            <button type="button" @click="jumpTo('rebate-rule')">
              <GiftOutlined />
              <span>一级返利规则</span>
            </button>
            <button type="button" @click="jumpTo('withdraw-rule')">
              <WalletOutlined />
              <span>提现规则</span>
            </button>
            <button type="button" @click="jumpTo('rebate-source')">
              <DatabaseOutlined />
              <span>返利来源</span>
            </button>
          </nav>
          <div class="updateInfo">
            <ClockCircleOutlined />
            <div>
              <strong>最后更新</strong>
              <span>{{ updatedAt || '--' }}</span>
            </div>
          </div>
        </aside>

        <div class="configContent">
          <section id="rebate-rule" class="configPanel">
            <header class="configPanelHead">
              <span class="sectionIcon"><GiftOutlined /></span>
              <div>
                <h2>一级返利规则</h2>
                <p>里程碑奖励与后续累充台阶</p>
              </div>
              <span class="enabledTag">已启用</span>
            </header>
            <a-form layout="vertical" :model="form">
              <div class="configFormGrid">
                <a-form-item label="初始累充门槛" name="milestone_amount" required>
                  <a-input v-model:value="form.milestone_amount" inputmode="decimal" addon-after="元" />
                </a-form-item>
                <a-form-item label="初始每次奖励" name="milestone_reward_amount" required>
                  <a-input v-model:value="form.milestone_reward_amount" inputmode="decimal" addon-after="元" />
                </a-form-item>
                <a-form-item label="初始最多奖励次数" name="milestone_max_times" required>
                  <a-input-number v-model:value="form.milestone_max_times" :min="0" :precision="0" style="width: 100%" addon-after="次" />
                </a-form-item>
                <a-form-item label="后续累充门槛" name="stage_amount" required>
                  <a-input v-model:value="form.stage_amount" inputmode="decimal" addon-after="元" />
                </a-form-item>
                <a-form-item label="后续每次奖励" name="stage_reward_amount" required>
                  <a-input v-model:value="form.stage_reward_amount" inputmode="decimal" addon-after="元" />
                </a-form-item>
              </div>
            </a-form>
          </section>

          <section id="withdraw-rule" class="configPanel">
            <header class="configPanelHead">
              <span class="sectionIcon green"><WalletOutlined /></span>
              <div>
                <h2>提现规则</h2>
                <p>返利转入 Sub2API 额度的限额与频率</p>
              </div>
            </header>
            <a-form layout="vertical" :model="form">
              <div class="configFormGrid">
                <a-form-item label="单次最低提现" name="withdraw_min_amount" required>
                  <a-input v-model:value="form.withdraw_min_amount" inputmode="decimal" addon-after="元" />
                </a-form-item>
                <a-form-item label="每日申请次数" name="withdraw_daily_limit" required>
                  <a-input-number v-model:value="form.withdraw_daily_limit" :min="0" :precision="0" style="width: 100%" addon-after="次" />
                </a-form-item>
                <a-form-item label="每日申请总金额" name="withdraw_daily_amount_limit" required>
                  <a-input v-model:value="form.withdraw_daily_amount_limit" inputmode="decimal" addon-after="元" />
                </a-form-item>
                <a-form-item label="返利到 API 额度换算比例" name="withdraw_to_api_quota_rate" required>
                  <a-input v-model:value="form.withdraw_to_api_quota_rate" inputmode="decimal" addon-after="倍" />
                </a-form-item>
              </div>
            </a-form>
          </section>

          <section id="rebate-source" class="configPanel">
            <header class="configPanelHead">
              <span class="sectionIcon orange"><DatabaseOutlined /></span>
              <div>
                <h2>返利来源</h2>
                <p>控制进入返利计算的 Sub2API 事件</p>
              </div>
            </header>
            <div class="sourceList">
              <div class="sourceRow">
                <div>
                  <strong>Sub2API 原生充值</strong>
                  <span>已完成的原生余额充值订单</span>
                </div>
                <a-switch v-model:checked="form.native_recharge_enabled" />
              </div>
              <div class="sourceRow">
                <div>
                  <strong>Sub2API 兑换</strong>
                  <span>用户完成兑换后产生的余额事件</span>
                </div>
                <a-switch v-model:checked="form.redeem_enabled" />
              </div>
              <div class="sourceRow">
                <div>
                  <strong>Sub2API 后台调额</strong>
                  <span>后台人工调整产生的余额事件</span>
                </div>
                <a-switch v-model:checked="form.admin_adjust_enabled" />
              </div>
            </div>
            <div class="cutoverInfo">
              <span>数据切换时间</span>
              <strong>{{ cutoverAt || '--' }}</strong>
            </div>
          </section>
        </div>
      </div>
    </AsyncState>
  </div>
</template>

<style scoped>
.configPage {
  gap: 24px;
}

.adminPageHead {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 24px;
}

.pageEyebrow {
  display: block;
  margin-bottom: 4px;
  color: #4648d4;
  font-size: 12px;
  font-weight: 700;
  line-height: 18px;
}

.adminPageHead h1 {
  margin: 0;
  color: var(--heading);
  font-size: 28px;
  line-height: 38px;
  letter-spacing: 0;
}

.adminPageHead p {
  margin: 3px 0 0;
  color: var(--muted);
  font-size: 13px;
  line-height: 20px;
}

.saveButton {
  min-width: 118px;
}

.configLayout {
  display: grid;
  align-items: start;
  grid-template-columns: 230px minmax(0, 1fr);
  gap: 24px;
}

.configRail {
  position: sticky;
  top: 0;
}

.configRail nav {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.configRail button {
  display: grid;
  width: 100%;
  min-height: 50px;
  padding: 0 14px;
  grid-template-columns: 20px minmax(0, 1fr);
  align-items: center;
  gap: 10px;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: var(--surface);
  color: var(--text);
  cursor: pointer;
  font: inherit;
  font-size: 13px;
  font-weight: 600;
  text-align: left;
  transition: border-color 0.2s, color 0.2s, transform 0.2s;
}

.configRail button:first-child,
.configRail button:hover {
  border-color: #7c7ee7;
  color: #4648d4;
}

.configRail button:hover {
  transform: translateY(-1px);
}

.updateInfo {
  display: flex;
  margin-top: 22px;
  padding: 16px;
  align-items: flex-start;
  gap: 10px;
  border: 1px dashed var(--border);
  border-radius: 8px;
  color: #64748b;
}

.updateInfo div,
.updateInfo strong,
.updateInfo span {
  display: block;
  min-width: 0;
}

.updateInfo strong {
  color: var(--heading);
  font-size: 12px;
  line-height: 18px;
}

.updateInfo span {
  margin-top: 3px;
  overflow-wrap: anywhere;
  color: var(--muted);
  font-size: 11px;
  line-height: 17px;
}

.configContent {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 20px;
}

.configPanel {
  min-width: 0;
  padding: 0 22px 22px;
  scroll-margin-top: 16px;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: var(--surface);
  box-shadow: 0 1px 3px rgb(15 23 42 / 4%);
}

.configPanelHead {
  display: grid;
  min-height: 82px;
  margin-bottom: 20px;
  padding: 16px 0;
  grid-template-columns: 38px minmax(0, 1fr) auto;
  align-items: center;
  gap: 12px;
  border-bottom: 1px solid var(--border);
}

.sectionIcon {
  display: inline-flex;
  width: 38px;
  height: 38px;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  background: #eeedff;
  color: #4648d4;
  font-size: 18px;
}

.sectionIcon.green {
  background: #e7f8f1;
  color: #059669;
}

.sectionIcon.orange {
  background: #fff5df;
  color: #d97706;
}

.configPanelHead h2,
.configPanelHead p {
  margin: 0;
}

.configPanelHead h2 {
  color: var(--heading);
  font-size: 16px;
  line-height: 24px;
  letter-spacing: 0;
}

.configPanelHead p {
  margin-top: 2px;
  color: var(--muted);
  font-size: 12px;
  line-height: 18px;
}

.enabledTag {
  padding: 3px 10px;
  border-radius: 999px;
  background: #e7f8f1;
  color: #059669;
  font-size: 11px;
  font-weight: 700;
}

.configFormGrid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0 24px;
}

.configFormGrid :deep(.ant-form-item) {
  margin-bottom: 18px;
}

.configFormGrid :deep(.ant-form-item-label > label) {
  color: var(--text);
  font-size: 12px;
  font-weight: 600;
}

.configFormGrid :deep(.ant-input),
.configFormGrid :deep(.ant-input-number),
.configFormGrid :deep(.ant-input-group-addon) {
  min-height: 38px;
}

.sourceList {
  display: flex;
  flex-direction: column;
}

.sourceRow {
  display: flex;
  min-height: 68px;
  padding: 13px 4px;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  border-bottom: 1px solid var(--border);
}

.sourceRow strong,
.sourceRow span {
  display: block;
}

.sourceRow strong {
  color: var(--heading);
  font-size: 13px;
  line-height: 20px;
}

.sourceRow span {
  margin-top: 2px;
  color: var(--muted);
  font-size: 12px;
  line-height: 18px;
}

.cutoverInfo {
  display: flex;
  margin-top: 18px;
  padding: 14px 16px;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  border-radius: 8px;
  background: #f2f4f6;
  color: #64748b;
  font-size: 12px;
}

.cutoverInfo strong {
  overflow-wrap: anywhere;
  color: #334155;
  text-align: right;
}

@media (max-width: 900px) {
  .configLayout {
    grid-template-columns: 1fr;
  }

  .configRail {
    position: static;
  }

  .configRail nav {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .updateInfo {
    display: none;
  }
}

@media (max-width: 760px) {
  .configPage {
    gap: 16px;
  }

  .adminPageHead {
    align-items: stretch;
    flex-direction: column;
  }

  .adminPageHead h1 {
    font-size: 24px;
    line-height: 34px;
  }

  .saveButton {
    width: 100%;
  }

  .configRail nav {
    grid-template-columns: 1fr;
  }

  .configRail button {
    min-height: 44px;
  }

  .configContent {
    gap: 14px;
  }

  .configPanel {
    padding: 0 14px 14px;
    border-radius: 8px;
  }

  .configPanelHead {
    min-height: 72px;
    margin-bottom: 16px;
    grid-template-columns: 34px minmax(0, 1fr) auto;
  }

  .sectionIcon {
    width: 34px;
    height: 34px;
  }

  .configFormGrid {
    grid-template-columns: 1fr;
  }

  .sourceRow {
    align-items: flex-start;
  }

  .cutoverInfo {
    align-items: flex-start;
    flex-direction: column;
  }

  .cutoverInfo strong {
    text-align: left;
  }
}
</style>
