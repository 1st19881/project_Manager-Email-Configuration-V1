export interface MailTestPayload {
  email: string
  event: string
  reference_id?: string | null
}

export interface MailTestResult {
  message: string
  log_id: string
  status: string
  email_subject: string
}

export class MailTestValidationError extends Error {
  constructor(
    message: string,
    public readonly errors: Record<string, string[]>,
  ) {
    super(message)
  }
}

const apiBaseUrl = import.meta.env.VITE_API_BASE_URL?.replace(/\/$/, '') ?? ''

export async function sendTestMail(payload: MailTestPayload): Promise<MailTestResult> {
  const response = await fetch(`${apiBaseUrl}/api/v1/mail/test`, {
    method: 'POST',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  })

  if (response.ok) return (await response.json()) as MailTestResult

  const body = (await response.json().catch(() => null)) as {
    message?: string
    errors?: Record<string, string[]>
  } | null

  if (response.status === 422 && body?.errors) {
    throw new MailTestValidationError(body.message ?? 'ข้อมูลไม่ถูกต้อง', body.errors)
  }

  throw new Error(body?.message ?? `ส่งอีเมลทดสอบไม่สำเร็จ (${response.status})`)
}
