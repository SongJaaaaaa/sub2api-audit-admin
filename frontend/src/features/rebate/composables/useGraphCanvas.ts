import { computed, reactive } from 'vue'

const minScale = 0.4
const maxScale = 2
const step = 0.15

export function useGraphCanvas() {
  const canvas = reactive({
    scale: 1,
    translateX: 0,
    translateY: 0,
    dragging: false,
    startX: 0,
    startY: 0,
  })

  const transformStyle = computed(() => ({
    transform: `translate(${canvas.translateX}px, ${canvas.translateY}px) scale(${canvas.scale})`,
    transformOrigin: 'top center',
  }))

  function zoomIn() {
    canvas.scale = Math.min(maxScale, canvas.scale + step)
  }

  function zoomOut() {
    canvas.scale = Math.max(minScale, canvas.scale - step)
  }

  function resetView() {
    canvas.scale = 1
    canvas.translateX = 0
    canvas.translateY = 0
  }

  function onWheel(event: WheelEvent) {
    event.deltaY < 0 ? zoomIn() : zoomOut()
  }

  function onPointerDown(event: PointerEvent) {
    if (event.button !== 0) return
    canvas.dragging = true
    canvas.startX = event.clientX - canvas.translateX
    canvas.startY = event.clientY - canvas.translateY
    ;(event.currentTarget as HTMLElement).setPointerCapture?.(event.pointerId)
  }

  function onPointerMove(event: PointerEvent) {
    if (!canvas.dragging) return
    canvas.translateX = event.clientX - canvas.startX
    canvas.translateY = event.clientY - canvas.startY
  }

  function onPointerUp() {
    canvas.dragging = false
  }

  return {
    canvas,
    transformStyle,
    onPointerDown,
    onPointerMove,
    onPointerUp,
    onWheel,
    resetView,
    zoomIn,
    zoomOut,
  }
}
