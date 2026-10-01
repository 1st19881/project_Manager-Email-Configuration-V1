<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import AppIcon from './AppIcon.vue'
import MailNotificationManagerForm from './MailNotificationManagerForm.vue'
import {
  ApiValidationError,
  createMailNotificationManager,
  deleteMailNotificationManager,
  downloadMailNotificationManagerTemplate,
  getMailNotificationManagers,
  getPlants,
  importMailNotificationManagers,
  updateMailNotificationManager,
  type MailNotificationManager,
  type ManagerPayload,
  type PaginationMeta,
  type Plant,
} from '@/services/mailNotificationManagerApi'

const managers = ref<MailNotificationManager[]>([])
const search = ref('')
const plant = ref('')
const plants = ref<Plant[]>([])
const loadingPlants = ref(false)
const activeFlag = ref('')
const loading = ref(false)
const errorMessage = ref('')
const formOpen = ref(false)
const selectedManager = ref<MailNotificationManager | null>(null)
const saving = ref(false)
const deletingId = ref<string | null>(null)
const formErrors = ref<Record<string, string[]>>({})
const importOpen = ref(false)
const importFile = ref<File | null>(null)
const importing = ref(false)
const importErrors = ref<string[]>([])
const successMessage = ref('')
const viewedManager = ref<MailNotificationManager | null>(null)
const meta = ref<PaginationMeta>({
  current_page: 1,
  from: null,
  last_page: 1,
  per_page: 15,
  to: null,
  total: 0,
})

const resultSummary = computed(() => {
  if (meta.value.total === 0) return 'ไม่พบข้อมูล'

  return `แสดง ${meta.value.from}–${meta.value.to} จาก ${meta.value.total} รายการ`
})

let searchTimer: ReturnType<typeof setTimeout> | undefined

async function loadManagers(page = 1): Promise<void> {
  loading.value = true
  errorMessage.value = ''

  try {
    const response = await getMailNotificationManagers({
      search: search.value.trim(),
      plant: plant.value.trim(),
      activeFlag: activeFlag.value,
      page,
      perPage: meta.value.per_page,
    })

    managers.value = response.data
    meta.value = response.meta
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'เกิดข้อผิดพลาดที่ไม่ทราบสาเหตุ'
  } finally {
    loading.value = false
  }
}

async function loadPlants(): Promise<void> {
  loadingPlants.value = true

  try {
    plants.value = await getPlants()
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'โหลดข้อมูล Plant ไม่สำเร็จ'
  } finally {
    loadingPlants.value = false
  }
}

function scheduleSearch(): void {
  window.clearTimeout(searchTimer)
  searchTimer = window.setTimeout(() => loadManagers(1), 350)
}

function resetFilters(): void {
  search.value = ''
  plant.value = ''
  activeFlag.value = ''
  void loadManagers(1)
}

function displayName(manager: MailNotificationManager): string {
  return manager.name_th || manager.name_en || '—'
}

function formatDateTime(value: string | null): string {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value

  return date.toLocaleString('th-TH', { dateStyle: 'medium', timeStyle: 'short' })
}

function openDetail(manager: MailNotificationManager): void {
  viewedManager.value = manager
}

function editFromDetail(): void {
  if (!viewedManager.value) return
  const manager = viewedManager.value
  viewedManager.value = null
  openEditForm(manager)
}

function openCreateForm(): void {
  selectedManager.value = null
  formErrors.value = {}
  formOpen.value = true
}

function openEditForm(manager: MailNotificationManager): void {
  selectedManager.value = manager
  formErrors.value = {}
  formOpen.value = true
}

function closeForm(): void {
  if (saving.value) return
  formOpen.value = false
}

async function saveManager(payload: ManagerPayload): Promise<void> {
  saving.value = true
  formErrors.value = {}

  try {
    if (selectedManager.value) {
      await updateMailNotificationManager(selectedManager.value.noti_id, payload)
    } else {
      await createMailNotificationManager(payload)
    }

    formOpen.value = false
    await loadManagers(selectedManager.value ? meta.value.current_page : 1)
  } catch (error) {
    if (error instanceof ApiValidationError) {
      formErrors.value = error.errors
    } else {
      errorMessage.value = error instanceof Error ? error.message : 'บันทึกข้อมูลไม่สำเร็จ'
    }
  } finally {
    saving.value = false
  }
}

async function removeManager(manager: MailNotificationManager): Promise<void> {
  const name = displayName(manager)
  if (!window.confirm(`ยืนยันการลบ ${name} (${manager.email}) หรือไม่?`)) return

  deletingId.value = manager.noti_id
  errorMessage.value = ''

  try {
    await deleteMailNotificationManager(manager.noti_id)
    const targetPage = managers.value.length === 1 && meta.value.current_page > 1
      ? meta.value.current_page - 1
      : meta.value.current_page
    await loadManagers(targetPage)
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'ลบข้อมูลไม่สำเร็จ'
  } finally {
    deletingId.value = null
  }
}

async function downloadTemplate(): Promise<void> {
  errorMessage.value = ''

  try {
    await downloadMailNotificationManagerTemplate()
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'ดาวน์โหลด Template ไม่สำเร็จ'
  }
}

function openImport(): void {
  importFile.value = null
  importErrors.value = []
  importOpen.value = true
}

function selectImportFile(event: Event): void {
  importFile.value = (event.target as HTMLInputElement).files?.[0] ?? null
  importErrors.value = []
}

async function submitImport(): Promise<void> {
  if (!importFile.value) {
    importErrors.value = ['กรุณาเลือกไฟล์ .xlsx หรือ .csv']
    return
  }

  importing.value = true
  importErrors.value = []
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const result = await importMailNotificationManagers(importFile.value)
    importOpen.value = false
    successMessage.value = `Import สำเร็จ ${result.imported} รายการ`
    await loadManagers(1)
  } catch (error) {
    if (error instanceof ApiValidationError) {
      importErrors.value = Object.values(error.errors).flat()

      if (error.duplicates.length) {
        const shown = error.duplicates.slice(0, 10)
        const more = error.duplicates.length - shown.length
        window.alert(
          `พบอีเมลซ้ำ ${error.duplicates.length} รายการ ยังไม่มีข้อมูลถูก Import\n\n${shown.join('\n')}`
            + (more > 0 ? `\n...และอีก ${more} รายการ (ดูทั้งหมดในหน้าต่าง Import)` : ''),
        )
      }
    } else {
      importErrors.value = [error instanceof Error ? error.message : 'Import ข้อมูลไม่สำเร็จ']
    }
  } finally {
    importing.value = false
  }
}

onMounted(() => {
  void loadManagers()
  void loadPlants()
})
</script>

<template>
  <section class="manager-page" aria-labelledby="page-title">
    <header class="page-header">
      <div class="page-identity">
        <img class="summit-logo" src="/summit-logo.png" alt="Summit logo" />
        <div>
          <p class="eyebrow">Car Booking Notification System</p>
          <h1 id="page-title">Manager Email Configuration</h1>
          <p class="subtitle">รายชื่อผู้รับอีเมลแจ้งเตือนตามโรงงานและแผนก</p>
        </div>
      </div>
      <div class="header-actions">
        <div class="record-count" aria-label="จำนวนรายการทั้งหมด">
          <AppIcon class="record-icon" name="users" :size="22" />
          <strong>{{ meta.total }}</strong>
          <span>Recipients</span>
        </div>
        <button class="template-button" type="button" @click="downloadTemplate">
          <AppIcon name="download" />
          ดาวน์โหลด Template
        </button>
        <button class="import-button" type="button" @click="openImport">
          <AppIcon name="file-spreadsheet" />
          Import Excel
        </button>
        <button class="add-button" type="button" @click="openCreateForm">
          <AppIcon name="plus" :size="20" :stroke-width="2.4" />
          เพิ่มผู้รับอีเมล
        </button>
      </div>
    </header>

    <div class="panel">
      <div v-if="successMessage" class="success-alert" role="status">
        <span class="alert-message"><AppIcon name="check-circle" />{{ successMessage }}</span>
        <button type="button" aria-label="ปิด" @click="successMessage = ''"><AppIcon name="x" /></button>
      </div>
      <div class="toolbar">
        <label class="search-field">
          <span class="sr-only">ค้นหา</span>
          <AppIcon class="search-icon" name="search" />
          <input
            v-model="search"
            type="search"
            placeholder="ค้นหารหัส ชื่อ แผนก ตำแหน่ง หรืออีเมล"
            @input="scheduleSearch"
          />
        </label>

        <label>
          <span class="field-label">Plant</span>
          <select v-model="plant" :disabled="loadingPlants" @change="loadManagers(1)">
            <option value="">{{ loadingPlants ? 'กำลังโหลด...' : 'ทั้งหมด' }}</option>
            <option v-for="plantOption in plants" :key="plantOption.id" :value="plantOption.id">
              {{ plantOption.name }} ({{ plantOption.code }})
            </option>
          </select>
        </label>

        <label>
          <span class="field-label">สถานะ</span>
          <select v-model="activeFlag" @change="loadManagers(1)">
            <option value="">ทั้งหมด</option>
            <option value="Y">Active</option>
            <option value="N">Inactive</option>
          </select>
        </label>

        <button class="secondary-button" type="button" @click="resetFilters">
          <AppIcon name="rotate-ccw" :size="16" />
          ล้างตัวกรอง
        </button>
      </div>

      <div v-if="errorMessage" class="alert" role="alert">
        <span class="alert-message"><AppIcon name="alert-circle" />{{ errorMessage }}</span>
        <button type="button" @click="loadManagers(meta.current_page)">
          <AppIcon name="refresh-cw" :size="15" />
          ลองอีกครั้ง
        </button>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>รหัสพนักงาน</th>
              <th>Plant</th>
              <th>Manager</th>
              <th>Email</th>
              <th>Status</th>
              <th><span class="sr-only">จัดการ</span></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="6" class="state-cell">
                <span class="spinner" aria-hidden="true"></span>
                กำลังโหลดข้อมูล...
              </td>
            </tr>
            <tr v-else-if="managers.length === 0">
              <td colspan="6" class="state-cell">
                <span class="empty-state">
                  <span class="empty-icon"><AppIcon name="inbox" :size="28" :stroke-width="1.8" /></span>
                  ยังไม่มีรายชื่อผู้รับอีเมล
                </span>
              </td>
            </tr>
            <tr
              v-for="manager in managers"
              v-else
              :key="manager.noti_id"
              class="manager-row"
              @click="openDetail(manager)"
            >
              <td data-label="รหัสพนักงาน"><strong>{{ manager.employee_code }}</strong></td>
              <td data-label="Plant">
                <span class="plant-badge"><AppIcon name="factory" :size="13" />{{ manager.plant }}</span>
              </td>
              <td data-label="Manager" class="manager-cell">
                <div class="manager-info">
                  <span class="avatar" aria-hidden="true"><AppIcon name="user" :size="16" /></span>
                  <div class="manager-text">
                    <strong class="manager-name truncate" :title="displayName(manager)">{{ displayName(manager) }}</strong>
                    <small v-if="manager.department_name" class="truncate" :title="manager.department_name">
                      {{ manager.department_name }}
                    </small>
                  </div>
                </div>
              </td>
              <td data-label="Email" class="email-cell">
                <a class="email-link" :href="`mailto:${manager.email}`" :title="manager.email" @click.stop>
                  <AppIcon name="mail" :size="15" />
                  <span class="truncate">{{ manager.email }}</span>
                </a>
              </td>
              <td data-label="Status">
                <span :class="['status', manager.active_flag === 'Y' ? 'active' : 'inactive']">
                  <span class="status-dot"></span>
                  {{ manager.active_flag === 'Y' ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="row-actions" data-label="จัดการ" @click.stop>
                <button class="view-button" type="button" @click="openDetail(manager)">
                  <AppIcon name="eye" :size="15" />ดู
                </button>
                <button class="edit-button" type="button" @click="openEditForm(manager)">
                  <AppIcon name="pencil" :size="15" />แก้ไข
                </button>
                <button
                  class="delete-button"
                  type="button"
                  :disabled="deletingId === manager.noti_id"
                  @click="removeManager(manager)"
                >
                  <AppIcon name="trash" :size="15" />
                  {{ deletingId === manager.noti_id ? 'กำลังลบ...' : 'ลบ' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <footer class="pagination">
        <span>{{ resultSummary }}</span>
        <div class="page-controls">
          <button
            type="button"
            :disabled="loading || meta.current_page <= 1"
            @click="loadManagers(meta.current_page - 1)"
          >
            <AppIcon name="chevron-left" :size="16" />
            ก่อนหน้า
          </button>
          <span>หน้า {{ meta.current_page }} / {{ meta.last_page }}</span>
          <button
            type="button"
            :disabled="loading || meta.current_page >= meta.last_page"
            @click="loadManagers(meta.current_page + 1)"
          >
            ถัดไป
            <AppIcon name="chevron-right" :size="16" />
          </button>
        </div>
      </footer>
    </div>

    <MailNotificationManagerForm
      v-if="formOpen"
      :manager="selectedManager"
      :saving="saving"
      :errors="formErrors"
      @close="closeForm"
      @save="saveManager"
    />

    <div v-if="viewedManager" class="import-backdrop" role="presentation" @mousedown.self="viewedManager = null">
      <section class="import-dialog detail-dialog" role="dialog" aria-modal="true" aria-labelledby="detail-title">
        <header>
          <div>
            <p class="eyebrow">รายละเอียดผู้รับอีเมล</p>
            <h2 id="detail-title">{{ displayName(viewedManager) }}</h2>
            <small v-if="viewedManager.name_th && viewedManager.name_en">{{ viewedManager.name_en }}</small>
          </div>
          <button class="dialog-close" type="button" aria-label="ปิด" @click="viewedManager = null"><AppIcon name="x" :size="22" /></button>
        </header>

        <dl class="detail-list">
          <div><dt><AppIcon name="id-card" :size="14" />รหัสพนักงาน</dt><dd>{{ viewedManager.employee_code }}</dd></div>
          <div>
            <dt><AppIcon name="factory" :size="14" />Plant</dt>
            <dd><span class="plant-badge"><AppIcon name="factory" :size="13" />{{ viewedManager.plant }}</span></dd>
          </div>
          <div class="full"><dt><AppIcon name="building" :size="14" />Department</dt><dd>{{ viewedManager.department_name || '—' }}</dd></div>
          <div class="full"><dt><AppIcon name="briefcase" :size="14" />Position</dt><dd>{{ viewedManager.position_name || '—' }}</dd></div>
          <div class="full">
            <dt><AppIcon name="mail" :size="14" />Email</dt>
            <dd><a :href="`mailto:${viewedManager.email}`">{{ viewedManager.email }}</a></dd>
          </div>
          <div>
            <dt><AppIcon name="toggle-right" :size="14" />Status</dt>
            <dd>
              <span :class="['status', viewedManager.active_flag === 'Y' ? 'active' : 'inactive']">
                <span class="status-dot"></span>
                {{ viewedManager.active_flag === 'Y' ? 'Active' : 'Inactive' }}
              </span>
            </dd>
          </div>
          <div><dt><AppIcon name="hash" :size="14" />Noti ID</dt><dd>{{ viewedManager.noti_id }}</dd></div>
          <div>
            <dt><AppIcon name="calendar" :size="14" />สร้างเมื่อ</dt>
            <dd>
              {{ formatDateTime(viewedManager.created_at) }}
              <small v-if="viewedManager.created_by">โดย {{ viewedManager.created_by }}</small>
            </dd>
          </div>
          <div>
            <dt><AppIcon name="clock" :size="14" />แก้ไขล่าสุด</dt>
            <dd>
              {{ formatDateTime(viewedManager.updated_at) }}
              <small v-if="viewedManager.updated_by">โดย {{ viewedManager.updated_by }}</small>
            </dd>
          </div>
        </dl>

        <footer>
          <button class="secondary-button" type="button" @click="viewedManager = null">ปิด</button>
          <button class="add-button import-submit" type="button" @click="editFromDetail">
            <AppIcon name="pencil" :size="16" />
            แก้ไข
          </button>
        </footer>
      </section>
    </div>

    <div v-if="importOpen" class="import-backdrop" role="presentation" @mousedown.self="!importing && (importOpen = false)">
      <section class="import-dialog" role="dialog" aria-modal="true" aria-labelledby="import-title">
        <header>
          <div>
            <p class="eyebrow">Bulk import</p>
            <h2 id="import-title">Import รายชื่อจาก Excel</h2>
          </div>
          <button class="dialog-close" type="button" :disabled="importing" aria-label="ปิด" @click="importOpen = false"><AppIcon name="x" :size="22" /></button>
        </header>

        <p class="import-help">
          ใช้ไฟล์ Template โดยคงชื่อและลำดับคอลัมน์เดิม รองรับ .xlsx และ .csv สูงสุด 1,000 รายการ
        </p>
        <button class="template-link" type="button" @click="downloadTemplate">
          <AppIcon name="download" :size="16" />
          ดาวน์โหลด Template Excel
        </button>

        <label class="file-picker">
          <span class="file-picker-label"><AppIcon name="file-spreadsheet" :size="16" />เลือกไฟล์</span>
          <input type="file" accept=".xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" @change="selectImportFile" />
          <strong>{{ importFile?.name || 'ยังไม่ได้เลือกไฟล์' }}</strong>
        </label>

        <div v-if="importErrors.length" class="import-errors" role="alert">
          <strong class="alert-message"><AppIcon name="alert-circle" :size="16" />กรุณาตรวจสอบข้อมูล</strong>
          <ul>
            <li v-for="message in importErrors" :key="message">{{ message }}</li>
          </ul>
        </div>

        <footer>
          <button class="secondary-button" type="button" :disabled="importing" @click="importOpen = false">ยกเลิก</button>
          <button class="add-button import-submit" type="button" :disabled="importing || !importFile" @click="submitImport">
            <span v-if="importing" class="spinner spinner-light" aria-hidden="true"></span>
            <AppIcon v-else name="upload" :size="16" />
            {{ importing ? 'กำลัง Import...' : 'Import ข้อมูล' }}
          </button>
        </footer>
      </section>
    </div>
  </section>
</template>

<style scoped>
.manager-page { width: 100%; }
.page-header { display: flex; justify-content: space-between; align-items: flex-end; gap: 2rem; margin-bottom: 2rem; }
.page-identity { align-items: center; display: flex; gap: 1rem; min-width: 0; }
.summit-logo { flex: 0 0 auto; height: 76px; object-fit: contain; width: 80px; }
.eyebrow { color: #2563eb; font-size: .78rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
h1 { color: #14213d; font-size: clamp(1.8rem, 4vw, 2.7rem); font-weight: 750; letter-spacing: -.035em; line-height: 1.1; }
.subtitle { color: #64748b; margin-top: .55rem; }
.header-actions { align-items: stretch; display: flex; flex-wrap: wrap; gap: .75rem; justify-content: flex-end; }
.record-count { align-items: flex-end; background: #14213d; border-radius: 18px; color: white; display: flex; flex-direction: column; min-width: 128px; padding: 1rem 1.2rem; position: relative; }
.record-icon { color: #60a5fa; left: 1rem; position: absolute; top: 1rem; }
.record-count strong { font-size: 1.7rem; line-height: 1; }
.record-count span { color: #a8b3c7; font-size: .72rem; letter-spacing: .08em; margin-top: .35rem; text-transform: uppercase; }
.add-button { align-items: center; background: #2563eb; border: 0; border-radius: 18px; color: white; cursor: pointer; display: flex; font: inherit; font-weight: 700; gap: .45rem; padding: 0 1.2rem; }
.add-button:hover { background: #1d4ed8; }
.template-button, .import-button { align-items: center; border: 1px solid #dce3eb; border-radius: 18px; cursor: pointer; display: inline-flex; font: inherit; font-weight: 700; gap: .45rem; padding: 0 1.1rem; }
.template-button { background: white; color: #334155; }
.template-button:hover { background: #f8fafc; }
.import-button { background: #eaf2ff; border-color: #bfdbfe; color: #1d4ed8; }
.import-button:hover { background: #dbeafe; }
.panel { background: rgba(255,255,255,.94); border: 1px solid #e5eaf1; border-radius: 24px; box-shadow: 0 20px 60px rgba(26,45,80,.09); overflow: hidden; }
.toolbar { align-items: end; border-bottom: 1px solid #edf0f4; display: grid; gap: 1rem; grid-template-columns: minmax(260px, 1fr) 150px 150px auto; padding: 1.25rem; }
label { display: grid; gap: .4rem; }
.toolbar > label { min-width: 0; }
.field-label { color: #64748b; font-size: .75rem; font-weight: 650; }
input, select { background: #f8fafc; border: 1px solid #dce3eb; border-radius: 11px; color: #172033; font: inherit; height: 44px; outline: none; padding: 0 .85rem; transition: border-color .2s, box-shadow .2s; }
.toolbar input, .toolbar select { min-width: 0; width: 100%; }
input:focus, select:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.13); }
.search-field { position: relative; }
.search-field input { padding-left: 2.5rem; width: 100%; }
.search-icon { color: #8a98aa; left: .85rem; pointer-events: none; position: absolute; top: 13px; z-index: 1; }
.secondary-button, .page-controls button, .alert button { align-items: center; background: white; border: 1px solid #dce3eb; border-radius: 10px; color: #334155; cursor: pointer; display: inline-flex; font-weight: 650; gap: .4rem; height: 44px; justify-content: center; padding: 0 1rem; }
.alert-message { align-items: center; display: inline-flex; gap: .5rem; }
.secondary-button:hover, .page-controls button:hover:not(:disabled) { background: #f1f5f9; }
.alert { align-items: center; background: #fff5f5; border-bottom: 1px solid #fecaca; color: #b91c1c; display: flex; justify-content: space-between; padding: .9rem 1.25rem; }
.alert button { color: #b91c1c; height: 34px; }
.success-alert { align-items: center; background: #ecfdf5; border-bottom: 1px solid #a7f3d0; color: #047857; display: flex; justify-content: space-between; padding: .9rem 1.25rem; }
.success-alert button { background: transparent; border: 0; border-radius: 8px; color: inherit; cursor: pointer; display: grid; padding: .25rem; }
.success-alert button:hover { background: #d1fae5; }
.table-wrap { overflow-x: auto; }
table { border-collapse: collapse; min-width: 900px; width: 100%; }
th { background: #f8fafc; color: #718096; font-size: .72rem; font-weight: 700; letter-spacing: .065em; padding: .85rem 1.25rem; text-align: left; text-transform: uppercase; }
td { border-top: 1px solid #edf0f4; color: #334155; padding: 1rem 1.25rem; }
tbody tr { transition: background .15s; }
.manager-row { cursor: pointer; }
.manager-cell { max-width: 280px; }
.email-cell { max-width: 300px; }
.truncate { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
tbody tr:hover { background: #f9fbff; }
td a { color: #2563eb; font-weight: 550; }
.plant-badge { align-items: center; background: #eaf2ff; border-radius: 8px; color: #1d4ed8; display: inline-flex; font-size: .78rem; font-weight: 750; gap: .3rem; padding: .3rem .55rem; }
.manager-info { align-items: center; display: flex; gap: .7rem; min-width: 0; }
.manager-text { min-width: 0; }
.avatar { background: linear-gradient(135deg, #eaf2ff, #dbeafe); border-radius: 50%; color: #2563eb; display: grid; flex: 0 0 auto; height: 34px; place-items: center; width: 34px; }
.email-link { align-items: center; display: flex; gap: .4rem; min-width: 0; }
.email-link .app-icon { color: #94a3b8; }
.empty-state { color: #7b8798; display: inline-grid; gap: .65rem; justify-items: center; }
.empty-icon { background: #f1f5f9; border-radius: 16px; color: #94a3b8; display: grid; height: 56px; place-items: center; width: 56px; }
.manager-name { color: #172033; display: block; font-weight: 650; }
small { color: #94a3b8; display: block; margin-top: .2rem; }
.status { align-items: center; border-radius: 999px; display: inline-flex; font-size: .78rem; font-weight: 650; gap: .4rem; padding: .32rem .65rem; }
.status.active { background: #ecfdf5; color: #047857; }
.status.inactive { background: #f1f5f9; color: #64748b; }
.status-dot { background: currentColor; border-radius: 50%; height: 6px; width: 6px; }
.row-actions { white-space: nowrap; }
.row-actions button { align-items: center; background: transparent; border: 0; border-radius: 7px; cursor: pointer; display: inline-flex; font: inherit; gap: .3rem; font-size: .78rem; font-weight: 650; padding: .4rem .55rem; }
.view-button { color: #475569; }
.view-button:hover { background: #f1f5f9; }
.edit-button { color: #2563eb; margin-left: .2rem; }
.edit-button:hover { background: #eff6ff; }
.delete-button { color: #dc2626; margin-left: .2rem; }
.delete-button:hover:not(:disabled) { background: #fef2f2; }
.delete-button:disabled { cursor: wait; opacity: .55; }
.state-cell { color: #7b8798; height: 190px; text-align: center; }
.spinner { animation: spin .7s linear infinite; border: 2px solid #cbd5e1; border-radius: 50%; border-top-color: #2563eb; display: inline-block; height: 18px; margin-right: .5rem; vertical-align: middle; width: 18px; }
.spinner-light { border-color: rgba(255,255,255,.4); border-top-color: white; height: 16px; margin-right: 0; width: 16px; }
.pagination { align-items: center; border-top: 1px solid #edf0f4; color: #64748b; display: flex; font-size: .85rem; justify-content: space-between; padding: 1rem 1.25rem; }
.page-controls { align-items: center; display: flex; gap: .85rem; }
.page-controls button { height: 36px; }
.page-controls button:disabled { cursor: not-allowed; opacity: .45; }
.sr-only { height: 1px; margin: -1px; overflow: hidden; padding: 0; position: absolute; width: 1px; clip: rect(0,0,0,0); }
.import-backdrop { align-items: center; background: rgba(15,23,42,.55); display: flex; inset: 0; justify-content: center; padding: 1rem; position: fixed; z-index: 50; }
.import-dialog { background: white; border-radius: 22px; box-shadow: 0 24px 80px rgba(15,23,42,.25); max-height: calc(100vh - 2rem); max-width: 620px; overflow: auto; padding: 1.5rem; width: 100%; }
.import-dialog header { align-items: flex-start; display: flex; justify-content: space-between; }
.import-dialog h2 { color: #14213d; font-size: 1.45rem; margin-top: .2rem; }
.dialog-close { background: transparent; border: 0; border-radius: 10px; color: #64748b; cursor: pointer; display: grid; padding: .35rem; }
.dialog-close:hover:not(:disabled) { background: #f1f5f9; color: #14213d; }
.import-help { color: #64748b; line-height: 1.6; margin-top: 1rem; }
.template-link { align-items: center; background: transparent; border: 0; color: #2563eb; cursor: pointer; display: inline-flex; font: inherit; gap: .35rem; font-weight: 700; margin-top: .35rem; padding: 0; }
.file-picker { background: #f8fafc; border: 1px dashed #94a3b8; border-radius: 14px; margin-top: 1.25rem; padding: 1rem; }
.file-picker span { color: #475569; font-size: .8rem; font-weight: 700; }
.file-picker-label { align-items: center; display: inline-flex; gap: .4rem; }
.file-picker input { background: white; height: auto; padding: .65rem; }
.file-picker strong { color: #334155; font-size: .85rem; overflow-wrap: anywhere; }
.import-errors { background: #fff5f5; border: 1px solid #fecaca; border-radius: 12px; color: #b91c1c; margin-top: 1rem; max-height: 190px; overflow: auto; padding: .8rem 1rem; }
.import-errors ul { margin: .45rem 0 0 1.15rem; }
.import-errors li + li { margin-top: .25rem; }
.import-dialog footer { display: flex; gap: .75rem; justify-content: flex-end; margin-top: 1.25rem; }
.import-submit { min-height: 44px; }
.detail-dialog h2 { overflow-wrap: anywhere; }
.detail-list { display: grid; gap: .85rem 1.25rem; grid-template-columns: 1fr 1fr; margin-top: 1.25rem; }
.detail-list > div { background: #f8fafc; border: 1px solid #edf0f4; border-radius: 12px; min-width: 0; padding: .7rem .9rem; }
.detail-list .full { grid-column: 1 / -1; }
.detail-list dt { align-items: center; color: #718096; display: flex; font-size: .7rem; gap: .35rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
.detail-list dd { color: #172033; margin: .3rem 0 0; overflow-wrap: anywhere; }
.detail-list dd a { color: #2563eb; font-weight: 550; }
.import-submit:disabled { cursor: not-allowed; opacity: .55; }
@keyframes spin { to { transform: rotate(360deg); } }
@media (max-width: 850px) {
  .toolbar { grid-template-columns: 1fr 1fr; }
  .search-field { grid-column: 1 / -1; }
}
@media (max-width: 700px) {
  table { min-width: 0; }
  thead { display: none; }
  tbody { display: grid; gap: .75rem; padding: .75rem; }
  .manager-row { border: 1px solid #e5eaf1; border-radius: 14px; display: grid; overflow: hidden; }
  .manager-row:hover { background: white; }
  .manager-row td { align-items: start; border-top: 1px solid #edf0f4; display: grid; gap: .75rem; grid-template-columns: 92px minmax(0, 1fr); padding: .7rem .85rem; }
  .manager-row td:first-child { border-top: 0; }
  .manager-row td::before { color: #718096; content: attr(data-label); font-size: .7rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
  .manager-row td > * { min-width: 0; }
  .manager-row td a { overflow-wrap: anywhere; }
  .manager-cell, .email-cell { max-width: none; }
  .manager-row .truncate { white-space: normal; }
  .row-actions { white-space: normal; }
  .state-cell { display: table-cell; }
}
@media (max-width: 560px) {
  .page-header { align-items: flex-start; flex-direction: column; }
  /* grid-auto-rows keeps every cell as tall as the Recipients card. Without it
     the buttons lose the flex stretch they relied on and collapse to ~24px. */
  .header-actions { display: grid; grid-auto-rows: 1fr; grid-template-columns: 1fr 1fr; width: 100%; }
  .add-button, .template-button, .import-button { justify-content: center; }
  .record-count { align-items: flex-start; }
  .record-icon { left: auto; right: 1rem; }
  .page-identity { align-items: flex-start; }
  .summit-logo { height: 58px; width: 62px; }
  .toolbar { grid-template-columns: 1fr; }
  .search-field { grid-column: auto; }
  .detail-list { grid-template-columns: 1fr; }
  .pagination { align-items: flex-start; flex-direction: column; gap: 1rem; }
}
@media (max-width: 380px) {
  .header-actions { grid-template-columns: 1fr; }
  .record-count { align-items: center; }
  .add-button { min-height: 56px; }
  .manager-row td { grid-template-columns: 78px minmax(0, 1fr); }
}
</style>
