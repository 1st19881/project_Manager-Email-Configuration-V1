<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import {
  getMailTemplates,
  type MailTemplate,
  type MailTemplateWording,
} from '@/services/mailTemplateApi'

const wording = ref<MailTemplateWording | null>(null)
const selectedEvent = ref('')
const loading = ref(false)
const errorMessage = ref('')

const sampleDocId = 'DOC-00000071'

/** Stand-in values so the preview reads like a real mail instead of a form. */
const sampleValues: Record<string, string> = {
  doc_id: sampleDocId,
  requester: 'สมชาย ใจดี (60012345)',
  department: 'ฝ่ายจัดซื้อ',
  plant: 'โรงงานบางปู',
  tel: '02-123-4567 ต่อ 890',
  used_at: '25/09/2569 08:30',
  back_at: '25/09/2569 17:00',
  destination: 'นิคมอุตสาหกรรมอมตะนคร ชลบุรี',
  purpose: 'เข้าพบผู้ผลิตชิ้นส่วน',
  car_license: '1กก 1234 กรุงเทพมหานคร',
}

const templates = computed<MailTemplate[]>(() => wording.value?.templates ?? [])

const selectedTemplate = computed<MailTemplate | null>(
  () => templates.value.find((template) => template.event_code === selectedEvent.value) ?? null,
)

const greetingPreview = computed(() => {
  const layout = wording.value?.layout

  if (!layout) return ''

  return layout.greeting.replace(':name', 'สมหญิง รักงาน')
})

const subjectPreview = computed(
  () => selectedTemplate.value?.subject.replace(':reference', sampleDocId) ?? '',
)

async function loadWording(): Promise<void> {
  loading.value = true
  errorMessage.value = ''

  try {
    wording.value = await getMailTemplates()
    selectedEvent.value = wording.value.templates[0]?.event_code ?? ''
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'เกิดข้อผิดพลาดที่ไม่ทราบสาเหตุ'
  } finally {
    loading.value = false
  }
}

onMounted(loadWording)
</script>

<template>
  <section class="wording-page" aria-labelledby="wording-title">
    <header class="page-header">
      <div class="page-identity">
        <img class="summit-logo" src="/summit-logo.png" alt="Summit logo" />
        <div>
          <p class="eyebrow">Car Booking Notification System</p>
          <h1 id="wording-title">Email Wording</h1>
          <p class="subtitle">ข้อความที่ระบบใช้ส่งอีเมลแจ้งเตือนในแต่ละเหตุการณ์</p>
        </div>
      </div>
      <div class="header-actions">
        <div class="record-count" aria-label="จำนวนรูปแบบอีเมล">
          <strong>{{ templates.length }}</strong>
          <span>Templates</span>
        </div>
        <button class="secondary-button" type="button" :disabled="loading" @click="loadWording">
          รีเฟรช
        </button>
      </div>
    </header>

    <div class="panel">
      <div v-if="errorMessage" class="alert" role="alert">
        <span>{{ errorMessage }}</span>
        <button type="button" @click="loadWording">ลองใหม่</button>
      </div>

      <p v-if="loading" class="state-block">
        <span class="spinner" aria-hidden="true"></span> กำลังโหลดข้อความอีเมล...
      </p>

      <div v-else-if="wording" class="wording-layout">
        <nav class="event-list" aria-label="เลือกเหตุการณ์">
          <button
            v-for="template in templates"
            :key="template.event_code"
            class="event-item"
            :class="{ active: template.event_code === selectedEvent }"
            type="button"
            @click="selectedEvent = template.event_code"
          >
            <code>{{ template.event_code }}</code>
            <span>{{ template.headline }}</span>
          </button>
        </nav>

        <div v-if="selectedTemplate" class="preview-column">
          <div class="subject-row">
            <span class="field-label">หัวเรื่อง (Subject)</span>
            <p class="subject-raw">{{ selectedTemplate.subject }}</p>
            <p class="subject-rendered">ตัวอย่าง: {{ subjectPreview }}</p>
          </div>

          <div class="mail-preview">
            <p class="mail-greeting">{{ greetingPreview }}</p>
            <p class="mail-headline">{{ selectedTemplate.headline }}</p>

            <table class="mail-table">
              <tbody>
                <tr v-for="field in wording.layout.fields" :key="field.key">
                  <th scope="row">{{ field.label }}</th>
                  <td>{{ sampleValues[field.key] ?? wording.layout.empty_value }}</td>
                </tr>
              </tbody>
            </table>

            <p class="mail-closing">{{ wording.layout.closing }}</p>
            <p v-if="wording.layout.cta_url" class="mail-cta">
              <a :href="wording.layout.cta_url" target="_blank" rel="noopener">{{ wording.layout.cta }}</a>
              <small>ลิงก์ปุ่ม: {{ wording.layout.cta_url }}</small>
            </p>
            <p class="mail-footer">{{ wording.layout.footer }}</p>
          </div>

          <dl class="placeholder-help">
            <template v-for="(meaning, token) in wording.placeholders" :key="token">
              <dt><code>{{ token }}</code></dt>
              <dd>{{ meaning }}</dd>
            </template>
          </dl>

          <p class="preview-note">
            ค่าที่แสดงในตารางเป็นข้อมูลตัวอย่าง ระบบจะแทนด้วยข้อมูลจริงของรายการจอง
            และเว้นว่างจะแสดงเป็น "{{ wording.layout.empty_value }}"
          </p>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.wording-page { width: 100%; }
.page-header { display: flex; justify-content: space-between; align-items: flex-end; gap: 2rem; margin-bottom: 2rem; }
.page-identity { align-items: center; display: flex; gap: 1rem; min-width: 0; }
.summit-logo { flex: 0 0 auto; height: 76px; object-fit: contain; width: 80px; }
.eyebrow { color: #2563eb; font-size: .78rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
h1 { color: #14213d; font-size: clamp(1.8rem, 4vw, 2.7rem); font-weight: 750; letter-spacing: -.035em; line-height: 1.1; }
.subtitle { color: #64748b; margin-top: .55rem; }
.header-actions { align-items: stretch; display: flex; flex-wrap: wrap; gap: .75rem; justify-content: flex-end; }
.record-count { align-items: flex-end; background: #14213d; border-radius: 18px; color: white; display: flex; flex-direction: column; min-width: 128px; padding: 1rem 1.2rem; }
.record-count strong { font-size: 1.7rem; line-height: 1; }
.record-count span { color: #a8b3c7; font-size: .72rem; letter-spacing: .08em; margin-top: .35rem; text-transform: uppercase; }
.secondary-button { background: white; border: 1px solid #dce3eb; border-radius: 18px; color: #334155; cursor: pointer; font: inherit; font-weight: 700; padding: 0 1.2rem; }
.secondary-button:hover:not(:disabled) { background: #f1f5f9; }
.secondary-button:disabled { cursor: wait; opacity: .55; }
.panel { background: rgba(255,255,255,.94); border: 1px solid #e5eaf1; border-radius: 24px; box-shadow: 0 20px 60px rgba(26,45,80,.09); overflow: hidden; }
.alert { align-items: center; background: #fff5f5; border-bottom: 1px solid #fecaca; color: #b91c1c; display: flex; justify-content: space-between; padding: .9rem 1.25rem; }
.alert button { background: white; border: 1px solid #fecaca; border-radius: 10px; color: #b91c1c; cursor: pointer; font: inherit; font-weight: 650; height: 34px; padding: 0 .8rem; }
.state-block { color: #7b8798; padding: 3rem 1.25rem; text-align: center; }
.spinner { animation: spin .7s linear infinite; border: 2px solid #cbd5e1; border-radius: 50%; border-top-color: #2563eb; display: inline-block; height: 18px; margin-right: .5rem; vertical-align: middle; width: 18px; }
.wording-layout { display: grid; grid-template-columns: minmax(240px, 300px) minmax(0, 1fr); }
.event-list { border-right: 1px solid #edf0f4; display: flex; flex-direction: column; padding: 1rem; }
.event-item { background: transparent; border: 1px solid transparent; border-radius: 14px; cursor: pointer; display: grid; font: inherit; gap: .3rem; padding: .8rem .9rem; text-align: left; }
.event-item:hover { background: #f9fbff; }
.event-item.active { background: #eaf2ff; border-color: #bfdbfe; }
.event-item code { color: #1d4ed8; font-size: .72rem; font-weight: 700; letter-spacing: .05em; }
.event-item span { color: #334155; font-size: .88rem; }
.preview-column { display: grid; gap: 1.25rem; padding: 1.25rem; }
.field-label { color: #64748b; font-size: .75rem; font-weight: 650; }
.subject-row { display: grid; gap: .4rem; }
.subject-raw { background: #f8fafc; border: 1px solid #dce3eb; border-radius: 11px; color: #172033; font-weight: 650; overflow-wrap: anywhere; padding: .7rem .85rem; }
.subject-rendered { color: #94a3b8; font-size: .82rem; overflow-wrap: anywhere; }
.mail-preview { background: #f4f5f7; border: 1px solid #e4e7eb; border-radius: 16px; color: #1f2933; padding: 1.5rem; }
.mail-greeting { font-size: 1rem; margin-bottom: 1rem; }
.mail-headline { font-size: .95rem; margin-bottom: 1.25rem; }
.mail-table { border-collapse: collapse; font-size: .88rem; width: 100%; }
.mail-table th { border-bottom: 1px solid #e4e7eb; color: #616e7c; font-weight: 400; padding: .5rem .75rem; text-align: left; width: 35%; }
.mail-table td { border-bottom: 1px solid #e4e7eb; overflow-wrap: anywhere; padding: .5rem .75rem; }
.mail-closing { font-size: .88rem; margin-top: 1.25rem; }
.mail-cta { margin-top: 1rem; }
.mail-cta a { background: #1f6feb; border-radius: 6px; color: white; display: inline-block; font-size: .88rem; padding: .6rem 1.25rem; }
.mail-cta a:hover { background: #1a5fd0; }
.mail-cta small { color: #7b8794; display: block; font-size: .75rem; margin-top: .45rem; overflow-wrap: anywhere; }
.mail-footer { color: #7b8794; font-size: .78rem; margin-top: 1.5rem; }
.placeholder-help { border: 1px dashed #cbd5e1; border-radius: 14px; display: grid; gap: .4rem .9rem; grid-template-columns: auto minmax(0, 1fr); padding: .9rem 1.1rem; }
.placeholder-help dt code { color: #1d4ed8; font-size: .82rem; font-weight: 700; }
.placeholder-help dd { color: #64748b; font-size: .82rem; }
.preview-note { color: #94a3b8; font-size: .8rem; line-height: 1.6; }
@keyframes spin { to { transform: rotate(360deg); } }
@media (max-width: 850px) {
  .wording-layout { grid-template-columns: 1fr; }
  .event-list { border-bottom: 1px solid #edf0f4; border-right: 0; display: grid; gap: .5rem; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); }
  .event-item { border-color: #e5eaf1; }
}
@media (max-width: 560px) {
  .page-header { align-items: flex-start; flex-direction: column; }
  .header-actions { display: grid; grid-template-columns: 1fr 1fr; width: 100%; }
  .record-count { align-items: flex-start; }
  .page-identity { align-items: flex-start; }
  .summit-logo { height: 58px; width: 62px; }
  .secondary-button { min-height: 44px; }
  .mail-preview { padding: 1rem; }
  .mail-table th, .mail-table td { display: block; width: auto; }
  .mail-table th { border-bottom: 0; font-size: .72rem; letter-spacing: .04em; padding-bottom: .1rem; text-transform: uppercase; }
  .placeholder-help { grid-template-columns: 1fr; }
}
</style>
