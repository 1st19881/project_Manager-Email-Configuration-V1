export interface MailTemplate {
  event_code: string
  subject: string
  headline: string
}

export interface MailTemplateField {
  key: string
  label: string
}

export interface MailTemplateLayout {
  greeting: string
  greeting_fallback: string
  fields: MailTemplateField[]
  empty_value: string
  closing: string
  cta: string
  cta_url: string | null
  footer: string
}

export interface MailTemplateWording {
  templates: MailTemplate[]
  layout: MailTemplateLayout
  placeholders: Record<string, string>
}

interface MailTemplateResponse {
  data: MailTemplateWording
}

const apiBaseUrl = import.meta.env.VITE_API_BASE_URL?.replace(/\/$/, '') ?? ''

export async function getMailTemplates(): Promise<MailTemplateWording> {
  const response = await fetch(`${apiBaseUrl}/api/v1/mail-templates`, {
    headers: { Accept: 'application/json' },
  })

  if (!response.ok) {
    const body = (await response.json().catch(() => null)) as { message?: string } | null

    throw new Error(body?.message ?? `ไม่สามารถโหลดข้อความอีเมลได้ (${response.status})`)
  }

  return (await response.json() as MailTemplateResponse).data
}
