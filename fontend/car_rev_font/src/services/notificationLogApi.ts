import type { PaginationMeta } from '@/services/mailNotificationManagerApi'

export type LogStatus = 'PENDING' | 'PROCESSING' | 'SENT' | 'FAILED'

export type StatusCounts = Record<LogStatus, number>

export interface EventCount {
  event_code: string
  label: string
  total: number
  by_status: StatusCounts
}

export interface DailyCount {
  date: string
  total: number
  by_status: StatusCounts
}

export interface LogSummary {
  date_from: string | null
  date_to: string | null
  timezone: string
  total: number
  by_status: StatusCounts
  by_event: EventCount[]
  daily: DailyCount[]
}

export interface NotificationLog {
  log_id: string
  event_code: string
  reference_id: string
  mgr_select_id: string | null
  email_to: string | null
  email_cc: string | null
  email_subject: string | null
  status: LogStatus
  attempt_count: number
  error_message: string | null
  sent_at: string | null
  created_at: string | null
}

interface LogListResponse {
  data: NotificationLog[]
  meta: PaginationMeta
}

export interface LogFilters {
  dateFrom?: string
  dateTo?: string
  status?: string
  eventCode?: string
  page?: number
  perPage?: number
}

const apiBaseUrl = import.meta.env.VITE_API_BASE_URL?.replace(/\/$/, '') ?? ''

async function getJson<T>(path: string, filters: LogFilters): Promise<T> {
  const query = new URLSearchParams()

  if (filters.dateFrom) query.set('date_from', filters.dateFrom)
  if (filters.dateTo) query.set('date_to', filters.dateTo)
  if (filters.status) query.set('status', filters.status)
  if (filters.eventCode) query.set('event_code', filters.eventCode)
  if (filters.page) query.set('page', String(filters.page))
  if (filters.perPage) query.set('per_page', String(filters.perPage))

  const response = await fetch(`${apiBaseUrl}/api/v1/${path}?${query.toString()}`, {
    headers: { Accept: 'application/json' },
  })

  const body = (await response.json().catch(() => null)) as
    | (T & { message?: string, errors?: Record<string, string[]> })
    | null

  // IIS rewrites every status to 200, so a Laravel 422 can only be recognised by its body.
  if (body?.errors) {
    throw new Error(Object.values(body.errors).flat()[0] ?? body.message ?? 'ข้อมูลไม่ถูกต้อง')
  }

  if (response.ok && body !== null) return body

  throw new Error(body?.message ?? `ไม่สามารถโหลดข้อมูล Log ได้ (${response.status})`)
}

export async function getLogSummary(filters: LogFilters): Promise<LogSummary> {
  return (await getJson<{ data: LogSummary }>('notification-logs/summary', filters)).data
}

export async function getLogs(filters: LogFilters): Promise<LogListResponse> {
  return getJson<LogListResponse>('notification-logs', filters)
}
