import { computed, ref, watch } from 'vue'

export interface TableColumn {
  title?: string
  dataIndex?: string
  key?: string
  [key: string]: unknown
}

export function useTableColumns<T extends TableColumn>(storageKey: string, allColumns: readonly T[], defaultWidth = 1200) {
  const defaults = allColumns.map(columnKey)
  const visibleCols = ref(readCols(storageKey, defaults))
  const tableWidth = ref(readWidth(storageKey, defaultWidth))

  const colOptions = computed(() => allColumns.map(column => ({
    key: columnKey(column),
    title: String(column.title || columnKey(column)),
  })))
  const columns = computed(() => allColumns.filter(column => visibleCols.value.includes(columnKey(column))))

  watch(visibleCols, value => localStorage.setItem(`${storageKey}:cols`, JSON.stringify(value)), { deep: true })
  watch(tableWidth, value => localStorage.setItem(`${storageKey}:width`, String(value)))

  function resetColumns() {
    visibleCols.value = [...defaults]
    tableWidth.value = defaultWidth
  }

  return { columns, visibleCols, colOptions, tableWidth, resetColumns }
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
