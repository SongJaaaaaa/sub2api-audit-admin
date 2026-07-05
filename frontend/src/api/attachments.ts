import { http } from './http'

export interface AttachmentItem {
  id: number
  attachable_type: string
  attachable_id: number
  original_name: string
  mime: string
  size: number
  download_url: string
  created_at: string | null
}

export function getAttachments(params: { attachable_type: string; attachable_id: number }) {
  return http.get<unknown, { items: AttachmentItem[] }>('/attachments', { params })
}

export function uploadAttachment(data: FormData) {
  return http.post<unknown, { attachment: AttachmentItem; message: string }>('/attachments', data)
}

export function downloadAttachment(id: number) {
  return http.get<unknown, Blob>(`/attachments/${id}/download`, { responseType: 'blob' })
}
