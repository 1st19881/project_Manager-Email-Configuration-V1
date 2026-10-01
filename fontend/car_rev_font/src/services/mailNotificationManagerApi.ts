export interface MailNotificationManager {
  noti_id: string
  employee_code: string
  plant: string
  position_name: string | null
  department_name: string | null
  name_th: string | null
  name_en: string | null
  email: string
  active_flag: 'Y' | 'N' | null
  created_by: string | null
  created_at: string | null
  updated_by: string | null
  updated_at: string | null
}

export interface PaginationMeta {
  current_page: number
  from: number | null
  last_page: number
  per_page: number
  to: number | null
  total: number
}

interface ManagerListResponse {
  data: MailNotificationManager[]
  meta: PaginationMeta
}

interface ManagerResponse {
  data: MailNotificationManager
}

export interface Plant {
  id: string
  code: string
  name: string
}

interface PlantListResponse {
  data: Plant[]
}

export interface ImportResult {
  message: string
  imported: number
}

export interface ManagerPayload {
  employee_code: string
  plant: string
  position_name: string | null
  department_name: string | null
  name_th: string | null
  name_en: string | null
  email: string
  active_flag: 'Y' | 'N'
  created_by?: string | null
  updated_by?: string | null
}

export class ApiValidationError extends Error {
  constructor(
    message: string,
    public readonly errors: Record<string, string[]>,
    public readonly duplicates: string[] = [],
  ) {
    super(message)
  }
}

const apiBaseUrl = import.meta.env.VITE_API_BASE_URL?.replace(/\/$/, '') ?? ''

async function parseResponse<T>(response: Response): Promise<T> {
  const body = (await response.json().catch(() => null)) as {
    message?: string
    errors?: Record<string, string[]>
    duplicates?: string[]
  } | null

  // IIS rewrites every status to 200, so a Laravel 422 can only be recognised by its body.
  if (body?.errors) {
    throw new ApiValidationError(body.message ?? 'ข้อมูลไม่ถูกต้อง', body.errors, body.duplicates)
  }

  if (response.ok && body !== null) return body as T

  throw new Error(body?.message ?? `ไม่สามารถดำเนินการได้ (${response.status})`)
}

export async function getMailNotificationManagers(params: {
  search?: string
  plant?: string
  activeFlag?: string
  page?: number
  perPage?: number
}): Promise<ManagerListResponse> {
  const query = new URLSearchParams()

  if (params.search) query.set('search', params.search)
  if (params.plant) query.set('plant', params.plant)
  if (params.activeFlag) query.set('active_flag', params.activeFlag)
  if (params.page) query.set('page', String(params.page))
  if (params.perPage) query.set('per_page', String(params.perPage))

  const response = await fetch(
    `${apiBaseUrl}/api/v1/mail-notification-managers?${query.toString()}`,
    { headers: { Accept: 'application/json' } },
  )

  return parseResponse<ManagerListResponse>(response)
}

export async function getPlants(): Promise<Plant[]> {
  const response = await fetch(`${apiBaseUrl}/api/v1/plants`, {
    headers: { Accept: 'application/json' },
  })

  return (await parseResponse<PlantListResponse>(response)).data
}

export async function createMailNotificationManager(
  payload: ManagerPayload,
): Promise<MailNotificationManager> {
  const response = await fetch(`${apiBaseUrl}/api/v1/mail-notification-managers`, {
    method: 'POST',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  })

  return (await parseResponse<ManagerResponse>(response)).data
}

export async function updateMailNotificationManager(
  notiId: string,
  payload: ManagerPayload,
): Promise<MailNotificationManager> {
  const response = await fetch(
    `${apiBaseUrl}/api/v1/mail-notification-managers/${encodeURIComponent(notiId)}`,
    {
      // IIS on the intranet server has WebDAV enabled, which answers PUT/DELETE with 405
      // before PHP sees them. Laravel honours the override header on a POST instead.
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-HTTP-Method-Override': 'PUT',
      },
      body: JSON.stringify(payload),
    },
  )

  return (await parseResponse<ManagerResponse>(response)).data
}

export async function deleteMailNotificationManager(notiId: string): Promise<void> {
  const response = await fetch(
    `${apiBaseUrl}/api/v1/mail-notification-managers/${encodeURIComponent(notiId)}`,
    {
      method: 'POST',
      headers: { Accept: 'application/json', 'X-HTTP-Method-Override': 'DELETE' },
    },
  )

  if (!response.ok) {
    const body = (await response.json().catch(() => null)) as { message?: string } | null
    throw new Error(body?.message ?? `ไม่สามารถลบข้อมูลได้ (${response.status})`)
  }
}

export async function downloadMailNotificationManagerTemplate(): Promise<void> {
  const response = await fetch(`${apiBaseUrl}/api/v1/mail-notification-managers-import/template`, {
    headers: { Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' },
  })

  if (!response.ok) {
    throw new Error(`ไม่สามารถดาวน์โหลด Template ได้ (${response.status})`)
  }

  const url = URL.createObjectURL(await response.blob())
  const link = document.createElement('a')
  link.href = url
  link.download = 'mail-notification-managers-template.xlsx'
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}

export async function importMailNotificationManagers(file: File): Promise<ImportResult> {
  const formData = new FormData()
  formData.append('file', file)

  const response = await fetch(`${apiBaseUrl}/api/v1/mail-notification-managers-import`, {
    method: 'POST',
    headers: { Accept: 'application/json' },
    body: formData,
  })

  const result = await parseResponse<ImportResult>(response)

  if (typeof result.imported !== 'number') {
    throw new Error(result.message ?? 'Import ข้อมูลไม่สำเร็จ')
  }

  return result
}
