<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import AppIcon from './AppIcon.vue'
import type { PaginationMeta } from '@/services/mailNotificationManagerApi'
import {
  getLogs,
  getLogSummary,
  type DailyCount,
  type LogStatus,
  type LogSummary,
  type NotificationLog,
} from '@/services/notificationLogApi'

type RangeKey = 'today' | '7d' | '30d' | 'all' | 'custom'

const statusMeta: Record<LogStatus, { label: string, tone: string }> = {
  SENT: { label: 'ส่งสำเร็จ', tone: 'good' },
  FAILED: { label: 'ล้มเหลว', tone: 'critical' },
  PENDING: { label: 'รอส่ง', tone: 'warning' },
  PROCESSING: { label: 'กำลังส่ง', tone: 'warning' },
}

/** Bottom-to-top order of the stacked daily columns. */
const stackOrder: LogStatus[] = ['SENT', 'FAILED', 'PENDING', 'PROCESSING']

const rangeOptions: { key: Exclude<RangeKey, 'custom'>, label: string }[] = [
  { key: 'today', label: 'วันนี้' },
  { key: '7d', label: '7 วัน' },
  { key: '30d', label: '30 วัน' },
  { key: 'all', label: 'ทั้งหมด' },
]

const range = ref<RangeKey>('30d')
const dateFrom = ref('')
const dateTo = ref('')
const statusFilter = ref('')

const summary = ref<LogSummary | null>(null)
const loadingSummary = ref(false)
const summaryError = ref('')

const logs = ref<NotificationLog[]>([])
const meta = ref<PaginationMeta>({ current_page: 1, from: null, last_page: 1, per_page: 15, to: null, total: 0 })
const loadingLogs = ref(false)
const logsError = ref('')
const expandedLogId = ref('')

/** yyyy-mm-dd on the browser's clock (the office runs on Bangkok time). */
function isoDate(date: Date): string {
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')

  return `${date.getFullYear()}-${month}-${day}`
}

function daysAgo(days: number): string {
  const date = new Date()
  date.setDate(date.getDate() - days)

  return isoDate(date)
}

function applyRange(key: Exclude<RangeKey, 'custom'>): void {
  range.value = key
  dateTo.value = key === 'all' ? '' : isoDate(new Date())
  dateFrom.value = { today: isoDate(new Date()), '7d': daysAgo(6), '30d': daysAgo(29), all: '' }[key]
  reload()
}

function onCustomDate(): void {
  range.value = 'custom'

  if (dateFrom.value && dateTo.value && dateFrom.value > dateTo.value) return

  reload()
}

const numberFormat = new Intl.NumberFormat('th-TH')
const formatNumber = (value: number): string => numberFormat.format(value)

const dateTimeFormat = new Intl.DateTimeFormat('th-TH', {
  day: '2-digit', month: 'short', year: '2-digit', hour: '2-digit', minute: '2-digit',
})
const formatDateTime = (value: string | null): string => (value ? dateTimeFormat.format(new Date(value)) : '-')

const dayFormat = new Intl.DateTimeFormat('th-TH', { day: 'numeric', month: 'short' })
const formatDay = (value: string): string => dayFormat.format(new Date(`${value}T00:00:00`))

const total = computed(() => summary.value?.total ?? 0)
const waitingCount = computed(
  () => (summary.value?.by_status.PENDING ?? 0) + (summary.value?.by_status.PROCESSING ?? 0),
)

function share(count: number): string {
  if (total.value === 0) return '0%'

  return `${((count / total.value) * 100).toFixed(1)}%`
}

/** Every day in the range, so a quiet day shows as an empty slot instead of vanishing. */
const dailySeries = computed<DailyCount[]>(() => {
  const rows = summary.value?.daily ?? []

  if (rows.length === 0) return []

  const byDate = new Map(rows.map((row) => [row.date, row]))
  const first = dateFrom.value || rows[0]!.date
  const last = dateTo.value || rows[rows.length - 1]!.date
  const days: DailyCount[] = []
  const cursor = new Date(`${first}T00:00:00`)
  const end = new Date(`${last}T00:00:00`)

  while (cursor <= end && days.length < 400) {
    const date = isoDate(cursor)
    days.push(byDate.get(date) ?? {
      date,
      total: 0,
      by_status: { PENDING: 0, PROCESSING: 0, SENT: 0, FAILED: 0 },
    })
    cursor.setDate(cursor.getDate() + 1)
  }

  return days
})

/** A clean y-axis top: the next 1/2/5 × 10ⁿ above the busiest day. */
const chartMax = computed(() => {
  const peak = Math.max(0, ...dailySeries.value.map((day) => day.total))

  if (peak === 0) return 1

  const magnitude = 10 ** Math.floor(Math.log10(peak))

  return [1, 2, 5, 10].map((step) => step * magnitude).find((top) => top >= peak) ?? peak
})

const yTicks = computed(() => [chartMax.value, chartMax.value / 2, 0])

/** Label every Nth day so the axis never turns into a smear. */
const labelEvery = computed(() => Math.max(1, Math.ceil(dailySeries.value.length / 10)))

function dayTooltip(day: DailyCount): string {
  const parts = stackOrder
    .filter((status) => day.by_status[status] > 0)
    .map((status) => `${statusMeta[status].label} ${formatNumber(day.by_status[status])}`)

  return `${formatDay(day.date)} · รวม ${formatNumber(day.total)}${parts.length ? ` (${parts.join(', ')})` : ''}`
}

const hoveredDay = ref<DailyCount | null>(null)

const filters = computed(() => ({
  dateFrom: dateFrom.value || undefined,
  dateTo: dateTo.value || undefined,
}))

async function loadSummary(): Promise<void> {
  loadingSummary.value = true
  summaryError.value = ''

  try {
    summary.value = await getLogSummary(filters.value)
  } catch (error) {
    summaryError.value = error instanceof Error ? error.message : 'เกิดข้อผิดพลาดที่ไม่ทราบสาเหตุ'
  } finally {
    loadingSummary.value = false
  }
}

async function loadLogs(page = 1): Promise<void> {
  loadingLogs.value = true
  logsError.value = ''

  try {
    const response = await getLogs({
      ...filters.value,
      status: statusFilter.value || undefined,
      page,
      perPage: meta.value.per_page,
    })
    logs.value = response.data
    meta.value = response.meta
  } catch (error) {
    logsError.value = error instanceof Error ? error.message : 'เกิดข้อผิดพลาดที่ไม่ทราบสาเหตุ'
  } finally {
    loadingLogs.value = false
  }
}

function reload(): void {
  void loadSummary()
  void loadLogs(1)
}

function filterByStatus(status: string): void {
  statusFilter.value = status
  void loadLogs(1)
}

const resultSummary = computed(() => {
  if (meta.value.total === 0) return 'ไม่พบข้อมูล'

  return `แสดง ${meta.value.from}–${meta.value.to} จาก ${formatNumber(meta.value.total)} รายการ`
})

onMounted(() => applyRange('30d'))
</script>

<template>
  <section class="log-page" aria-labelledby="log-title">
    <header class="page-header">
      <div class="page-identity">
        <img class="summit-logo" src="/summit-logo.png" alt="Summit logo" />
        <div>
          <p class="eyebrow">Car Booking Notification System</p>
          <h1 id="log-title">Email Log</h1>
          <p class="subtitle">จำนวนอีเมลแจ้งเตือนที่ระบบบันทึกไว้ แยกตามสถานะ เหตุการณ์ และวัน</p>
        </div>
      </div>
      <div class="header-actions">
        <div class="record-count" aria-label="จำนวน Log ทั้งหมดในช่วงที่เลือก">
          <AppIcon class="record-icon" name="bar-chart" :size="22" />
          <strong>{{ formatNumber(total) }}</strong>
          <span>Logs</span>
        </div>
        <button class="secondary-button" type="button" :disabled="loadingSummary" @click="reload">
          <AppIcon name="refresh-cw" :size="16" />
          รีเฟรช
        </button>
      </div>
    </header>

    <div class="filter-bar panel">
      <div class="range-chips" role="group" aria-label="ช่วงเวลา">
        <button
          v-for="option in rangeOptions"
          :key="option.key"
          type="button"
          :class="{ active: range === option.key }"
          :aria-pressed="range === option.key"
          @click="applyRange(option.key)"
        >
          {{ option.label }}
        </button>
      </div>
      <div class="date-fields">
        <label>
          <span class="field-label">ตั้งแต่วันที่</span>
          <input v-model="dateFrom" type="date" :max="dateTo || undefined" @change="onCustomDate" />
        </label>
        <label>
          <span class="field-label">ถึงวันที่</span>
          <input v-model="dateTo" type="date" :min="dateFrom || undefined" @change="onCustomDate" />
        </label>
      </div>
    </div>

    <div v-if="summaryError" class="alert panel" role="alert">
      <span class="alert-message"><AppIcon name="alert-circle" />{{ summaryError }}</span>
      <button type="button" @click="loadSummary"><AppIcon name="refresh-cw" :size="15" />ลองอีกครั้ง</button>
    </div>

    <div class="stat-grid" :aria-busy="loadingSummary">
      <button class="stat-tile" type="button" :class="{ selected: statusFilter === '' }" @click="filterByStatus('')">
        <span class="stat-label"><AppIcon name="mail" :size="16" />Log ทั้งหมด</span>
        <strong class="stat-value">{{ formatNumber(total) }}</strong>
        <span class="stat-note">ในช่วงที่เลือก</span>
      </button>
      <button class="stat-tile" type="button" :class="{ selected: statusFilter === 'SENT' }" @click="filterByStatus('SENT')">
        <span class="stat-label"><span class="swatch good"></span>ส่งสำเร็จ</span>
        <strong class="stat-value">{{ formatNumber(summary?.by_status.SENT ?? 0) }}</strong>
        <span class="stat-note">{{ share(summary?.by_status.SENT ?? 0) }} ของทั้งหมด</span>
      </button>
      <button class="stat-tile" type="button" :class="{ selected: statusFilter === 'FAILED' }" @click="filterByStatus('FAILED')">
        <span class="stat-label"><span class="swatch critical"></span>ล้มเหลว</span>
        <strong class="stat-value">{{ formatNumber(summary?.by_status.FAILED ?? 0) }}</strong>
        <span class="stat-note">{{ share(summary?.by_status.FAILED ?? 0) }} ของทั้งหมด</span>
      </button>
      <button class="stat-tile" type="button" :class="{ selected: statusFilter === 'PENDING' }" @click="filterByStatus('PENDING')">
        <span class="stat-label"><span class="swatch warning"></span>รอส่ง / กำลังส่ง</span>
        <strong class="stat-value">{{ formatNumber(waitingCount) }}</strong>
        <span class="stat-note">{{ share(waitingCount) }} ของทั้งหมด</span>
      </button>
    </div>

    <div class="panel chart-panel">
      <header class="panel-header">
        <div>
          <h2>จำนวน Log รายวัน</h2>
          <p class="panel-note">นับตามเวลาประเทศไทย ({{ summary?.timezone ?? 'Asia/Bangkok' }})</p>
        </div>
        <ul class="legend" aria-label="คำอธิบายสี">
          <li v-for="status in stackOrder.slice(0, 3)" :key="status">
            <span :class="['swatch', statusMeta[status].tone]"></span>{{ statusMeta[status].label }}
          </li>
        </ul>
      </header>

      <p v-if="loadingSummary" class="state-block"><span class="spinner" aria-hidden="true"></span>กำลังโหลดข้อมูล...</p>
      <p v-else-if="dailySeries.length === 0" class="state-block">ไม่มี Log ในช่วงที่เลือก</p>
      <div v-else class="chart">
        <div class="y-axis" aria-hidden="true">
          <span v-for="tick in yTicks" :key="tick">{{ formatNumber(tick) }}</span>
        </div>
        <div class="plot-scroll">
          <div class="plot" :style="{ minWidth: `${dailySeries.length * 18}px` }" @mouseleave="hoveredDay = null">
            <div class="gridlines" aria-hidden="true"><span></span><span></span><span></span></div>
            <div
              v-for="(day, index) in dailySeries"
              :key="day.date"
              class="column"
              tabindex="0"
              :aria-label="dayTooltip(day)"
              @mouseenter="hoveredDay = day"
              @focus="hoveredDay = day"
              @blur="hoveredDay = null"
            >
              <div class="bar" :style="{ height: `${(day.total / chartMax) * 100}%` }">
                <span
                  v-for="status in stackOrder.filter((s) => day.by_status[s] > 0)"
                  :key="status"
                  :class="['segment', statusMeta[status].tone]"
                  :style="{ flexGrow: day.by_status[status] }"
                ></span>
              </div>
              <span class="x-label" aria-hidden="true">{{ index % labelEvery === 0 ? formatDay(day.date) : '' }}</span>
            </div>
          </div>
        </div>
        <p class="chart-tooltip" aria-live="polite">{{ hoveredDay ? dayTooltip(hoveredDay) : 'ชี้ที่แท่งเพื่อดูจำนวนของแต่ละวัน' }}</p>
      </div>
    </div>

    <div class="panel">
      <header class="panel-header">
        <h2>แยกตามเหตุการณ์</h2>
      </header>
      <div class="table-wrap">
        <table class="event-table">
          <thead>
            <tr>
              <th>เหตุการณ์</th>
              <th class="num">ทั้งหมด</th>
              <th class="num">ส่งสำเร็จ</th>
              <th class="num">ล้มเหลว</th>
              <th class="num">รอส่ง</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="event in summary?.by_event ?? []" :key="event.event_code" :class="{ muted: event.total === 0 }">
              <td data-label="เหตุการณ์">
                <code>{{ event.event_code }}</code>
                <small>{{ event.label }}</small>
              </td>
              <td class="num" data-label="ทั้งหมด"><strong>{{ formatNumber(event.total) }}</strong></td>
              <td class="num" data-label="ส่งสำเร็จ">{{ formatNumber(event.by_status.SENT) }}</td>
              <td class="num" data-label="ล้มเหลว">{{ formatNumber(event.by_status.FAILED) }}</td>
              <td class="num" data-label="รอส่ง">{{ formatNumber(event.by_status.PENDING + event.by_status.PROCESSING) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="panel">
      <header class="panel-header">
        <h2>รายการ Log ล่าสุด</h2>
        <label class="status-select">
          <span class="field-label">สถานะ</span>
          <select v-model="statusFilter" @change="loadLogs(1)">
            <option value="">ทั้งหมด</option>
            <option v-for="(item, status) in statusMeta" :key="status" :value="status">{{ item.label }}</option>
          </select>
        </label>
      </header>

      <div v-if="logsError" class="alert" role="alert">
        <span class="alert-message"><AppIcon name="alert-circle" />{{ logsError }}</span>
        <button type="button" @click="loadLogs(meta.current_page)"><AppIcon name="refresh-cw" :size="15" />ลองอีกครั้ง</button>
      </div>

      <div class="table-wrap">
        <table class="log-table">
          <thead>
            <tr>
              <th>เวลา</th>
              <th>เลขที่รายการ</th>
              <th>เหตุการณ์</th>
              <th>ผู้รับ</th>
              <th>สถานะ</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loadingLogs">
              <td colspan="5" class="state-cell"><span class="spinner" aria-hidden="true"></span>กำลังโหลดข้อมูล...</td>
            </tr>
            <tr v-else-if="logs.length === 0">
              <td colspan="5" class="state-cell">
                <span class="empty-state">
                  <span class="empty-icon"><AppIcon name="inbox" :size="28" :stroke-width="1.8" /></span>
                  ไม่พบ Log ตามเงื่อนไขที่เลือก
                </span>
              </td>
            </tr>
            <template v-for="log in logs" v-else :key="log.log_id">
              <tr
                class="log-row"
                :class="{ expandable: log.error_message }"
                @click="log.error_message && (expandedLogId = expandedLogId === log.log_id ? '' : log.log_id)"
              >
                <td data-label="เวลา" class="nowrap">{{ formatDateTime(log.created_at) }}</td>
                <td data-label="เลขที่รายการ"><strong>{{ log.reference_id }}</strong></td>
                <td data-label="เหตุการณ์"><code>{{ log.event_code }}</code></td>
                <td data-label="ผู้รับ" class="recipient-cell">
                  <span class="truncate" :title="log.email_to ?? ''">{{ log.email_to || '-' }}</span>
                  <small v-if="log.mgr_select_id">{{ log.mgr_select_id }}</small>
                </td>
                <td data-label="สถานะ">
                  <span :class="['status', statusMeta[log.status]?.tone]">
                    <span class="status-dot"></span>{{ statusMeta[log.status]?.label ?? log.status }}
                  </span>
                  <small v-if="log.error_message" class="error-hint">
                    {{ expandedLogId === log.log_id ? 'ซ่อนสาเหตุ' : 'ดูสาเหตุ' }}
                  </small>
                </td>
              </tr>
              <tr v-if="expandedLogId === log.log_id" class="error-row">
                <td colspan="5">{{ log.error_message }}</td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <footer class="pagination">
        <span>{{ resultSummary }}</span>
        <div class="page-controls">
          <button type="button" :disabled="loadingLogs || meta.current_page <= 1" @click="loadLogs(meta.current_page - 1)">
            <AppIcon name="chevron-left" :size="16" />ก่อนหน้า
          </button>
          <span>หน้า {{ meta.current_page }} / {{ meta.last_page }}</span>
          <button type="button" :disabled="loadingLogs || meta.current_page >= meta.last_page" @click="loadLogs(meta.current_page + 1)">
            ถัดไป<AppIcon name="chevron-right" :size="16" />
          </button>
        </div>
      </footer>
    </div>
  </section>
</template>

<style scoped>
.log-page { --good: #0ca30c; --critical: #d03b3b; --warning: #fab219; display: grid; gap: 1.25rem; width: 100%; }
.page-header { align-items: flex-end; display: flex; gap: 2rem; justify-content: space-between; margin-bottom: .75rem; }
.page-identity { align-items: center; display: flex; gap: 1rem; min-width: 0; }
.summit-logo { flex: 0 0 auto; height: 76px; object-fit: contain; width: 80px; }
.eyebrow { color: #2563eb; font-size: .78rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
h1 { color: #14213d; font-size: clamp(1.8rem, 4vw, 2.7rem); font-weight: 750; letter-spacing: -.035em; line-height: 1.1; }
h2 { color: #14213d; font-size: 1.05rem; font-weight: 700; }
.subtitle { color: #64748b; margin-top: .55rem; }
.header-actions { align-items: stretch; display: flex; flex-wrap: wrap; gap: .75rem; justify-content: flex-end; }
.record-count { align-items: flex-end; background: #14213d; border-radius: 18px; color: white; display: flex; flex-direction: column; min-width: 128px; padding: 1rem 1.2rem; position: relative; }
.record-icon { color: #60a5fa; left: 1rem; position: absolute; top: 1rem; }
.record-count strong { font-size: 1.7rem; line-height: 1; }
.record-count span { color: #a8b3c7; font-size: .72rem; letter-spacing: .08em; margin-top: .35rem; text-transform: uppercase; }
.panel { background: rgba(255,255,255,.94); border: 1px solid #e5eaf1; border-radius: 24px; box-shadow: 0 20px 60px rgba(26,45,80,.09); overflow: hidden; }
.panel-header { align-items: center; border-bottom: 1px solid #edf0f4; display: flex; flex-wrap: wrap; gap: 1rem; justify-content: space-between; padding: 1rem 1.25rem; }
.panel-note { color: #94a3b8; font-size: .78rem; margin-top: .2rem; }
.field-label { color: #64748b; font-size: .75rem; font-weight: 650; }
label { display: grid; gap: .4rem; }
input, select { background: #f8fafc; border: 1px solid #dce3eb; border-radius: 11px; color: #172033; font: inherit; height: 44px; outline: none; padding: 0 .85rem; transition: border-color .2s, box-shadow .2s; }
input:focus, select:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.13); }
.secondary-button, .page-controls button, .alert button { align-items: center; background: white; border: 1px solid #dce3eb; border-radius: 18px; color: #334155; cursor: pointer; display: inline-flex; font: inherit; font-weight: 700; gap: .4rem; justify-content: center; padding: 0 1.2rem; }
.secondary-button:hover:not(:disabled), .page-controls button:hover:not(:disabled) { background: #f1f5f9; }
.secondary-button:disabled { cursor: wait; opacity: .55; }

.filter-bar { align-items: end; display: flex; flex-wrap: wrap; gap: 1rem; justify-content: space-between; padding: 1rem 1.25rem; }
.range-chips { background: #f1f5f9; border-radius: 14px; display: inline-flex; gap: .25rem; padding: .25rem; }
.range-chips button { background: transparent; border: 0; border-radius: 11px; color: #475569; cursor: pointer; font: inherit; font-size: .88rem; font-weight: 650; height: 38px; padding: 0 1rem; }
.range-chips button:hover { color: #14213d; }
.range-chips button.active { background: white; box-shadow: 0 2px 8px rgba(26,45,80,.1); color: #1d4ed8; }
.date-fields { display: flex; gap: .75rem; }

.alert { align-items: center; background: #fff5f5; border-color: #fecaca; color: #b91c1c; display: flex; justify-content: space-between; padding: .9rem 1.25rem; }
.panel > .alert { border-radius: 0; border-width: 0 0 1px; box-shadow: none; }
.alert button { border-color: #fecaca; border-radius: 10px; color: #b91c1c; height: 34px; padding: 0 .8rem; }
.alert-message { align-items: center; display: inline-flex; gap: .5rem; }

.stat-grid { display: grid; gap: 1rem; grid-template-columns: repeat(4, minmax(0, 1fr)); }
.stat-tile { background: rgba(255,255,255,.94); border: 1px solid #e5eaf1; border-radius: 20px; box-shadow: 0 12px 34px rgba(26,45,80,.06); cursor: pointer; display: grid; font: inherit; gap: .35rem; padding: 1rem 1.15rem; text-align: left; transition: border-color .15s, box-shadow .15s; }
.stat-tile:hover { border-color: #bfdbfe; }
.stat-tile.selected { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.13); }
.stat-label { align-items: center; color: #64748b; display: flex; font-size: .82rem; font-weight: 650; gap: .45rem; }
.stat-value { color: #14213d; font-size: 1.9rem; font-variant-numeric: tabular-nums; font-weight: 750; letter-spacing: -.02em; line-height: 1.1; }
.stat-note { color: #94a3b8; font-size: .78rem; }

.swatch { border-radius: 3px; display: inline-block; flex: 0 0 auto; height: 10px; width: 10px; }
.swatch.good, .segment.good { background: var(--good); }
.swatch.critical, .segment.critical { background: var(--critical); }
.swatch.warning, .segment.warning { background: var(--warning); }
.legend { color: #475569; display: flex; flex-wrap: wrap; font-size: .8rem; gap: .9rem; list-style: none; margin: 0; padding: 0; }
.legend li { align-items: center; display: inline-flex; gap: .4rem; }

.chart { display: grid; gap: .25rem .6rem; grid-template-columns: auto minmax(0, 1fr); padding: 1.25rem 1.25rem 1rem; }
.y-axis { color: #94a3b8; display: flex; flex-direction: column; font-size: .72rem; font-variant-numeric: tabular-nums; height: 220px; justify-content: space-between; text-align: right; transform: translateY(-.45em); }
.plot-scroll { overflow-x: auto; padding-bottom: .25rem; }
.plot { align-items: flex-end; display: flex; gap: 2px; height: 220px; margin-bottom: 1.6rem; position: relative; }
.gridlines { display: flex; flex-direction: column; inset: 0; justify-content: space-between; pointer-events: none; position: absolute; }
.gridlines span { border-top: 1px dashed #e5eaf1; }
.gridlines span:last-child { border-top: 1px solid #cbd5e1; }
.column { align-items: stretch; cursor: default; display: flex; flex: 1 1 0; flex-direction: column; height: 100%; justify-content: flex-end; min-width: 10px; outline: none; position: relative; }
.column:hover, .column:focus-visible { background: rgba(37,99,235,.06); border-radius: 4px 4px 0 0; }
.bar { display: flex; flex-direction: column-reverse; gap: 2px; margin: 0 auto; max-width: 28px; width: 70%; }
.segment { flex-basis: 0; min-height: 2px; }
.bar .segment:last-child { border-radius: 4px 4px 0 0; }
.x-label { color: #94a3b8; font-size: .68rem; left: 0; position: absolute; top: calc(100% + .4rem); white-space: nowrap; }
.chart-tooltip { color: #475569; font-size: .82rem; grid-column: 2; min-height: 1.3em; }

.table-wrap { overflow-x: auto; }
table { border-collapse: collapse; width: 100%; }
th { background: #f8fafc; color: #718096; font-size: .72rem; font-weight: 700; letter-spacing: .065em; padding: .8rem 1.25rem; text-align: left; text-transform: uppercase; }
td { border-top: 1px solid #edf0f4; color: #334155; padding: .85rem 1.25rem; vertical-align: top; }
.num { font-variant-numeric: tabular-nums; text-align: right; }
code { color: #1d4ed8; font-size: .75rem; font-weight: 700; letter-spacing: .04em; }
small { color: #94a3b8; display: block; font-size: .78rem; margin-top: .2rem; }
.event-table tr.muted td { color: #94a3b8; }
.log-table { min-width: 820px; }
.log-row.expandable { cursor: pointer; }
.log-row:hover { background: #f9fbff; }
.nowrap { white-space: nowrap; }
.recipient-cell { max-width: 300px; }
.truncate { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.status { align-items: center; border-radius: 999px; display: inline-flex; font-size: .78rem; font-weight: 650; gap: .4rem; padding: .32rem .65rem; white-space: nowrap; }
.status.good { background: #ecfdf5; color: #047857; }
.status.critical { background: #fef2f2; color: #b91c1c; }
.status.warning { background: #fffbeb; color: #92400e; }
.status-dot { background: currentColor; border-radius: 50%; height: 6px; width: 6px; }
.error-hint { color: #2563eb; font-weight: 650; }
.error-row td { background: #fff5f5; border-top: 0; color: #b91c1c; font-size: .85rem; overflow-wrap: anywhere; }
.state-block, .state-cell { color: #7b8798; padding: 3rem 1.25rem; text-align: center; }
.empty-state { display: inline-grid; gap: .65rem; justify-items: center; }
.empty-icon { background: #f1f5f9; border-radius: 16px; color: #94a3b8; display: grid; height: 56px; place-items: center; width: 56px; }
.spinner { animation: spin .7s linear infinite; border: 2px solid #cbd5e1; border-radius: 50%; border-top-color: #2563eb; display: inline-block; height: 18px; margin-right: .5rem; vertical-align: middle; width: 18px; }
.pagination { align-items: center; border-top: 1px solid #edf0f4; color: #64748b; display: flex; font-size: .85rem; justify-content: space-between; padding: 1rem 1.25rem; }
.page-controls { align-items: center; display: flex; gap: .85rem; }
.page-controls button { border-radius: 10px; height: 36px; padding: 0 1rem; }
.page-controls button:disabled { cursor: not-allowed; opacity: .45; }
@keyframes spin { to { transform: rotate(360deg); } }

@media (max-width: 900px) {
  .stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 700px) {
  .log-table { min-width: 0; }
  .log-table thead { display: none; }
  .log-table tbody { display: grid; gap: .75rem; padding: .75rem; }
  .log-row { border: 1px solid #e5eaf1; border-radius: 14px; display: grid; overflow: hidden; }
  .log-row td { border-top: 1px solid #edf0f4; display: grid; gap: .75rem; grid-template-columns: 92px minmax(0, 1fr); padding: .65rem .85rem; }
  .log-row td:first-child { border-top: 0; }
  .log-row td::before { color: #718096; content: attr(data-label); font-size: .7rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
  .log-row td > * { min-width: 0; }
  .recipient-cell { max-width: none; }
  .log-row .truncate { white-space: normal; overflow-wrap: anywhere; }
  .error-row { display: block; margin-top: -.75rem; }
  .error-row td { border-radius: 0 0 14px 14px; display: block; }
  .state-cell { display: block; }
}
@media (max-width: 560px) {
  .page-header { align-items: flex-start; flex-direction: column; }
  .header-actions { display: grid; grid-auto-rows: 1fr; grid-template-columns: 1fr 1fr; width: 100%; }
  .record-count { align-items: flex-start; }
  .record-icon { left: auto; right: 1rem; }
  .page-identity { align-items: flex-start; }
  .summit-logo { height: 58px; width: 62px; }
  .filter-bar { align-items: stretch; flex-direction: column; }
  .range-chips { display: grid; grid-template-columns: repeat(4, 1fr); }
  .range-chips button { padding: 0 .4rem; }
  .date-fields { display: grid; grid-template-columns: 1fr 1fr; }
  .date-fields input { min-width: 0; width: 100%; }
  .stat-value { font-size: 1.5rem; }
  .event-table th, .event-table td { padding: .7rem .75rem; }
  .pagination { align-items: flex-start; flex-direction: column; gap: 1rem; }
}
</style>
