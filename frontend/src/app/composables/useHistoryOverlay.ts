import { onBeforeUnmount, watch, type Ref } from 'vue'

/**
 * 把一个浮层（抽屉 / 弹窗）的开关状态接入浏览器历史。
 *
 * 目的：移动端「侧滑返回 / 硬件返回键」时，先关闭当前打开的浮层（等价于退回上一层），
 * 而不是直接触发路由后退退回到主页。
 *
 * 用法：
 *   const { forceClose } = useHistoryOverlay(open, {
 *     guard: () => 是否有未保存内容 ? false : true, // 返回 false 拦截返回
 *     onBlocked: () => 弹出确认框,
 *   })
 */

interface OverlayEntry {
  id: number
  requestClose: () => void
}

// 全局浮层栈：只有栈顶（最后打开的）浮层响应一次返回事件，避免嵌套浮层被同时关闭。
const stack: OverlayEntry[] = []
let listening = false
// 需要忽略的 popstate 次数（由我们主动 history.back() 触发的那一次）。
let ignorePop = 0
let idSeq = 0

function handlePopState() {
  if (ignorePop > 0) {
    ignorePop--
    return
  }
  const top = stack[stack.length - 1]
  if (top) top.requestClose()
}

function ensureListener() {
  if (listening) return
  listening = true
  window.addEventListener('popstate', handlePopState)
}

export interface HistoryOverlayOptions {
  /** 返回 false 表示拦截本次返回（例如有未保存内容），浮层保持打开。 */
  guard?: () => boolean
  /** 当 guard 拦截返回时触发，通常用于弹出「放弃编辑」确认框。 */
  onBlocked?: () => void
}

export function useHistoryOverlay(open: Ref<boolean>, options: HistoryOverlayOptions = {}) {
  const id = ++idSeq
  let active = false

  function removeFromStack() {
    const index = stack.findIndex((entry) => entry.id === id)
    if (index >= 0) stack.splice(index, 1)
  }

  function pushEntry() {
    active = true
    stack.push({ id, requestClose })
    ensureListener()
    history.pushState({ ...history.state, __overlay: id }, '')
  }

  // 由返回事件触发：此时浏览器历史已经后退了一步。
  function requestClose() {
    if (!open.value) {
      active = false
      removeFromStack()
      return
    }
    if (options.guard && !options.guard()) {
      // 拦截返回：把刚被弹掉的历史记录补回来，浮层继续保持打开。
      history.pushState({ ...history.state, __overlay: id }, '')
      options.onBlocked?.()
      return
    }
    active = false
    removeFromStack()
    open.value = false
  }

  watch(open, (val, old) => {
    if (val && !old && !active) {
      pushEntry()
    } else if (!val && old && active) {
      // 代码里主动关闭：回退掉我们压入的那条历史记录。
      active = false
      removeFromStack()
      ignorePop++
      history.back()
    }
  })

  // 强制关闭（跳过 guard），用于「放弃编辑」确认后关闭。
  function forceClose() {
    if (!open.value) return
    open.value = false
  }

  onBeforeUnmount(() => {
    if (active) {
      active = false
      removeFromStack()
    }
  })

  return { forceClose }
}
