<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { getMailTemplates, type MailTemplate } from '@/services/mailTemplateApi'
import { MailTestValidationError, sendTestMail, type MailTestResult } from '@/services/mailTestApi'

const templates = ref<MailTemplate[]>([])
const email = ref('')
const event = ref('')
const referenceId = ref('')
const loadingTemplates = ref(false)
const sending = ref(false)
const errorMessage = ref('')
const fieldErrors = ref<Record<string, string[]>>({})
const result = ref<MailTestResult | null>(null)

const selectedTemplate = computed<MailTemplate | null>(
  () => templates.value.find((template) => template.event_code === event.value) ?? null,
)

const canSend = computed(() => email.value.trim() !== '' && event.value !== '' && !sending.value)

function fieldError(field: string): string {
  return fieldErrors.value[field]?.[0] ?? ''
}

async function loadTemplates(): Promise<void> {
  loadingTemplates.value = true
  errorMessage.value = ''

  try {
    const wording = await getMailTemplates()
    templates.value = wording.templates
    event.value = wording.templates[0]?.event_code ?? ''
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'โหลดรายการเหตุการณ์ไม่สำเร็จ'
  } finally {
    loadingTemplates.value = false
  }
}

async function submit(): Promise<void> {
  sending.value = true
  errorMessage.value = ''
  fieldErrors.value = {}
  result.value = null

  try {
    result.value = await sendTestMail({
      email: email.value.trim(),
      event: event.value,
      reference_id: referenceId.value.trim() || null,
    })
  } catch (error) {
    if (error instanceof MailTestValidationError) {
      fieldErrors.value = error.errors
      errorMessage.value = error.message
    } else {
      errorMessage.value = error instanceof Error ? error.message : 'ส่งอีเมลทดสอบไม่สำเร็จ'
    }
  } finally {
    sending.value = false
  }
}

onMounted(loadTemplates)
</script>

<template>
  <section class="test-page" aria-labelledby="test-title">
    <header class="page-header">
      <div class="page-identity">
        <img class="summit-logo" src="/summit-logo.png" alt="Summit logo" />
        <div>
          <p class="eyebrow">Car Booking Notification System</p>
          <h1 id="test-title">Test Email</h1>
          <p class="subtitle">ส่งอีเมลจริงผ่าน SMTP เพื่อตรวจสอบการตั้งค่าและรูปแบบข้อความ</p>
        </div>
      </div>
    </header>

    <div class="panel">
      <div v-if="errorMessage" class="alert" role="alert">
        <span>{{ errorMessage }}</span>
      </div>

      <div v-if="result" class="success-alert" role="status">
        <div>
          <strong>{{ result.message }}</strong>
          <p class="success-subject">หัวเรื่อง: {{ result.email_subject }}</p>
        </div>
        <button type="button" aria-label="ปิด" @click="result = null">×</button>
      </div>

      <form class="test-form" @submit.prevent="submit">
        <label class="form-field">
          <span class="field-label">ส่งไปที่อีเมล <em>*</em></span>
          <input
            v-model="email"
            type="email"
            placeholder="somchai@summitautogroup.com"
            autocomplete="off"
            required
          />
          <small v-if="fieldError('email')" class="field-error">{{ fieldError('email') }}</small>
        </label>

        <label class="form-field">
          <span class="field-label">เหตุการณ์ <em>*</em></span>
          <select v-model="event" :disabled="loadingTemplates" required>
            <option v-if="loadingTemplates" value="">กำลังโหลด...</option>
            <option
              v-for="template in templates"
              :key="template.event_code"
              :value="template.event_code"
            >
              {{ template.headline }} ({{ template.event_code }})
            </option>
          </select>
          <small v-if="fieldError('event')" class="field-error">{{ fieldError('event') }}</small>
        </label>

        <label class="form-field wide">
          <span class="field-label">เลขที่การจอง (ไม่บังคับ)</span>
          <input
            v-model="referenceId"
            type="text"
            placeholder="เช่น DOC-00000071"
            autocomplete="off"
          />
          <small v-if="fieldError('reference_id')" class="field-error">
            {{ fieldError('reference_id') }}
          </small>
          <small v-else class="field-help">
            ใส่เลขที่การจองจริงเพื่อดูข้อมูลจริงในอีเมล ถ้าเว้นว่างระบบจะใช้ข้อมูลตัวอย่าง
          </small>
        </label>

        <div class="form-actions">
          <p v-if="selectedTemplate" class="subject-hint">
            <span class="field-label">หัวเรื่องที่จะส่ง</span>
            <span class="subject-raw">[ทดสอบ] {{ selectedTemplate.subject }}</span>
          </p>
          <button class="add-button" type="submit" :disabled="!canSend">
            {{ sending ? 'กำลังส่ง...' : 'ส่งอีเมลทดสอบ' }}
          </button>
        </div>
      </form>

      <p class="note">
        อีเมลจะถูกส่งออกจริงทันทีโดยไม่ผ่านคิว และบันทึกผลลงตาราง TRC_MAIL_NOTI_LOG
        หากส่งไม่สำเร็จจะแสดงข้อความผิดพลาดจาก SMTP ให้เห็นบนหน้าจอนี้
      </p>
    </div>
  </section>
</template>

<style scoped>
.test-page { width: 100%; }
.page-header { display: flex; justify-content: space-between; align-items: flex-end; gap: 2rem; margin-bottom: 2rem; }
.page-identity { align-items: center; display: flex; gap: 1rem; min-width: 0; }
.summit-logo { flex: 0 0 auto; height: 76px; object-fit: contain; width: 80px; }
.eyebrow { color: #2563eb; font-size: .78rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
h1 { color: #14213d; font-size: clamp(1.8rem, 4vw, 2.7rem); font-weight: 750; letter-spacing: -.035em; line-height: 1.1; }
.subtitle { color: #64748b; margin-top: .55rem; }
.panel { background: rgba(255,255,255,.94); border: 1px solid #e5eaf1; border-radius: 24px; box-shadow: 0 20px 60px rgba(26,45,80,.09); overflow: hidden; }
.alert { align-items: center; background: #fff5f5; border-bottom: 1px solid #fecaca; color: #b91c1c; display: flex; justify-content: space-between; padding: .9rem 1.25rem; }
.success-alert { align-items: flex-start; background: #ecfdf5; border-bottom: 1px solid #a7f3d0; color: #047857; display: flex; gap: 1rem; justify-content: space-between; padding: .9rem 1.25rem; }
.success-alert button { background: transparent; border: 0; color: inherit; cursor: pointer; font-size: 1.25rem; line-height: 1; }
.success-subject { font-size: .84rem; margin-top: .3rem; opacity: .85; overflow-wrap: anywhere; }
.test-form { display: grid; gap: 1.1rem 1.25rem; grid-template-columns: repeat(2, minmax(0, 1fr)); padding: 1.5rem 1.25rem .5rem; }
.form-field { display: grid; gap: .35rem; }
.form-field.wide { grid-column: 1 / -1; }
.field-label { color: #64748b; font-size: .75rem; font-weight: 650; }
.field-label em { color: #dc2626; font-style: normal; }
.form-field input, .form-field select { background: white; border: 1px solid #dce3eb; border-radius: 11px; color: #172033; font: inherit; height: 46px; padding: 0 .85rem; width: 100%; }
.form-field input:focus, .form-field select:focus { border-color: #2563eb; outline: 2px solid #dbeafe; }
.field-error { color: #b91c1c; font-size: .78rem; }
.field-help { color: #94a3b8; font-size: .78rem; line-height: 1.5; }
.form-actions { align-items: flex-end; display: flex; flex-wrap: wrap; gap: 1rem; grid-column: 1 / -1; justify-content: space-between; }
.subject-hint { display: grid; gap: .3rem; min-width: 0; }
.subject-raw { background: #f8fafc; border: 1px solid #dce3eb; border-radius: 11px; color: #172033; font-weight: 650; overflow-wrap: anywhere; padding: .6rem .85rem; }
.add-button { background: #2563eb; border: 0; border-radius: 18px; color: white; cursor: pointer; font: inherit; font-weight: 700; height: 46px; padding: 0 1.6rem; }
.add-button:hover:not(:disabled) { background: #1d4ed8; }
.add-button:disabled { cursor: not-allowed; opacity: .55; }
.note { border-top: 1px solid #edf0f4; color: #94a3b8; font-size: .8rem; line-height: 1.7; margin-top: 1rem; padding: 1rem 1.25rem; }
@media (max-width: 720px) {
  .test-form { grid-template-columns: 1fr; }
  .page-header { align-items: flex-start; flex-direction: column; }
  .page-identity { align-items: flex-start; }
  .summit-logo { height: 58px; width: 62px; }
  .form-actions { align-items: stretch; flex-direction: column; }
  .add-button { width: 100%; }
}
</style>
