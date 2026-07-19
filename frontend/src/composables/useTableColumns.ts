import { computed, ref, watch } from 'vue'

export interface TableColumn {
  title?: string
  dataIndex?: string
  key?: string
  width?: number
  defaultHidden?: boolean
  resizable?: boolean
  [key: string]: unknown
}

export function useTableColumns<T extends TableColumn>(storageKey: string, allColumns: readonly T[], defaultWidth = 1200) {
  const defaults = allColumns.filter(column => !column.defaultHidden).map(columnKey)
  const visibleCols = ref(readCols(storageKey, defaults))
  const tableWidth = ref(readWidth(storageKey, defaultWidth))
  const widths = ref(readWidths(storageKey))

  const colOptions = computed(() => allColumns.map(column => ({
    key: columnKey(column),
    title: String(column.title || columnKey(column)),
  })))
  const columns = computed(() => allColumns
    .filter(column => visibleCols.value.includes(columnKey(column)))
    .map(column => ({
      ...column,
      width: widths.value[columnKey(column)] || column.width,
      resizable: column.resizable !== false,
    })))

  watch(visibleCols, value => localStorage.setItem(`${storageKey}:cols`, JSON.stringify(value)), { deep: true })
  watch(tableWidth, value => localStorage.setItem(`${storageKey}:width`, String(value)))
  watch(widths, value => localStorage.setItem(`${storageKey}:column-widths`, JSON.stringify(value)), { deep: true })

  function resizeColumn(width: number, column: TableColumn) {
    const key = columnKey(column)
    widths.value = { ...widths.value, [key]: Math.max(60, Math.round(width)) }
    const total = columns.value.reduce((sum, item) => sum + Number(item.width || 0), 0)
    tableWidth.value = Math.max(600, total)
  }

  function resetColumns() {
    visibleCols.value = [...defaults]
    tableWidth.value = defaultWidth
    widths.value = {}
  }

  return { columns, visibleCols, colOptions, tableWidth, resizeColumn, resetColumns }
}

function columnKey(column: TableColumn) {
  return String(column.key || column.dataIndex || column.title || '')
}

function readCols(storageKey: string, defaults: string[]) {
  try {
    const raw = localStorage.getItem(`${storageKey}:cols`) || localStorage.getItem(storageKey)
    if (raw === null) return defaults
    const value = JSON.parse(raw)
    if (!Array.isArray(value)) return defaults
    return value.filter((key): key is string => typeof key === 'string' && defaults.includes(key))
  } catch {
    return defaults
  }
}

function readWidth(storageKey: string, defaultWidth: number) {
  const value = Number(localStorage.getItem(`${storageKey}:width`))
  return value >= 600 && value <= 4000 ? value : defaultWidth
}

function readWidths(storageKey: string): Record<string, number> {
  try {
    const value = JSON.parse(localStorage.getItem(`${storageKey}:column-widths`) || '{}')
    return value && typeof value === 'object' && !Array.isArray(value) ? value : {}
  } catch {
    return {}
  }
}
