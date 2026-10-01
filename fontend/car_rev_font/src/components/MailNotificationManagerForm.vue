<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue'
import type {
  MailNotificationManager,
  ManagerPayload,
  Plant,
} from '@/services/mailNotificationManagerApi'
import { getPlants } from '@/services/mailNotificationManagerApi'

const props = defineProps<{
  manager: MailNotificationManager | null
  saving: boolean
  errors: Record<string, string[]>
}>()

const emit = defineEmits<{
  close: []
  save: [payload: ManagerPayload]
}>()

const plants = ref<Plant[]>([])
const loadingPlants = ref(false)
const plantError = ref('')

const form = reactive<ManagerPayload>({
  employee_code: '',
  plant: '',
  position_name: null,
  department_name: null,
  name_th: null,
  name_en: null,
  email: '',
  active_flag: 'Y',
})

watch(
  () => props.manager,
  (manager) => {
    Object.assign(form, {
      employee_code: manager?.employee_code ?? '',
      plant: manager?.plant ?? '',
      position_name: manager?.position_name ?? null,
      department_name: manager?.department_name ?? null,
      name_th: manager?.name_th ?? null,
      name_en: manager?.name_en ?? null,
      email: manager?.email ?? '',
      active_flag: manager?.active_flag === 'N' ? 'N' : 'Y',
    })
  },
  { immediate: true },
)

function nullableValue(value: string | null): string | null {
  const normalized = value?.trim() ?? ''
  return normalized || null
}

function submit(): void {
  emit('save', {
    employee_code: form.employee_code.trim(),
    plant: form.plant.trim(),
    position_name: nullableValue(form.position_name),
    department_name: nullableValue(form.department_name),
    name_th: nullableValue(form.name_th),
    name_en: nullableValue(form.name_en),
    email: form.email.trim(),
    active_flag: form.active_flag,
  })
}

async function loadPlants(): Promise<void> {
  loadingPlants.value = true
  plantError.value = ''

  try {
    plants.value = await getPlants()
  } catch (error) {
    plantError.value = error instanceof Error ? error.message : 'โหลดข้อมูล Plant ไม่สำเร็จ'
  } finally {
    loadingPlants.value = false
  }
}

onMounted(loadPlants)
</script>

<template>
  <div class="modal-backdrop" role="presentation" @mousedown.self="emit('close')">
    <section class="modal" role="dialog" aria-modal="true" aria-labelledby="form-title">
      <header>
        <div>
          <p>{{ manager ? 'EDIT RECIPIENT' : 'NEW RECIPIENT' }}</p>
          <h2 id="form-title">{{ manager ? 'แก้ไขผู้รับอีเมล' : 'เพิ่มผู้รับอีเมล' }}</h2>
        </div>
        <button class="close-button" type="button" aria-label="ปิด" @click="emit('close')">×</button>
      </header>

      <form @submit.prevent="submit">
        <div class="form-grid">
          <label>
            <span>รหัสพนักงาน <b>*</b></span>
            <input v-model="form.employee_code" required maxlength="50" autocomplete="off" />
            <small v-if="errors.employee_code">{{ errors.employee_code[0] }}</small>
          </label>
          <label>
            <span>Plant <b>*</b></span>
            <select v-model="form.plant" required :disabled="loadingPlants" autofocus>
              <option value="" disabled>
                {{ loadingPlants ? 'กำลังโหลด Plant...' : 'เลือก Plant' }}
              </option>
              <option
                v-if="form.plant && !plants.some((plant) => plant.id === form.plant)"
                :value="form.plant"
              >
                {{ form.plant }}
              </option>
              <option v-for="plant in plants" :key="plant.id" :value="plant.id">
                {{ plant.name }} ({{ plant.code }})
              </option>
            </select>
            <small v-if="errors.plant">{{ errors.plant[0] }}</small>
            <small v-else-if="plantError">
              {{ plantError }}
              <button class="retry-button" type="button" @click="loadPlants">ลองอีกครั้ง</button>
            </small>
          </label>
          <label>
            <span>สถานะ</span>
            <select v-model="form.active_flag">
              <option value="Y">Active</option>
              <option value="N">Inactive</option>
            </select>
          </label>
          <label>
            <span>ชื่อภาษาไทย</span>
            <input v-model="form.name_th" maxlength="250" />
            <small v-if="errors.name_th">{{ errors.name_th[0] }}</small>
          </label>
          <label>
            <span>ชื่อภาษาอังกฤษ</span>
            <input v-model="form.name_en" maxlength="250" />
            <small v-if="errors.name_en">{{ errors.name_en[0] }}</small>
          </label>
          <label>
            <span>แผนก</span>
            <input v-model="form.department_name" maxlength="200" />
            <small v-if="errors.department_name">{{ errors.department_name[0] }}</small>
          </label>
          <label>
            <span>ตำแหน่ง</span>
            <input v-model="form.position_name" maxlength="200" />
            <small v-if="errors.position_name">{{ errors.position_name[0] }}</small>
          </label>
          <label class="full-width">
            <span>Email <b>*</b></span>
            <input v-model="form.email" type="email" required maxlength="255" placeholder="name@company.com" />
            <small v-if="errors.email">{{ errors.email[0] }}</small>
          </label>
        </div>

        <footer>
          <button class="cancel-button" type="button" :disabled="saving" @click="emit('close')">ยกเลิก</button>
          <button class="save-button" type="submit" :disabled="saving">
            {{ saving ? 'กำลังบันทึก...' : 'บันทึกข้อมูล' }}
          </button>
        </footer>
      </form>
    </section>
  </div>
</template>

<style scoped>
.modal-backdrop { align-items: center; background: rgba(15, 23, 42, .55); display: flex; inset: 0; justify-content: center; padding: 1rem; position: fixed; z-index: 100; }
.modal { animation: appear .18s ease-out; background: white; border-radius: 22px; box-shadow: 0 28px 80px rgba(15,23,42,.28); max-height: calc(100vh - 2rem); max-width: 720px; overflow: auto; width: 100%; }
header { align-items: flex-start; border-bottom: 1px solid #e8edf3; display: flex; justify-content: space-between; padding: 1.5rem; }
header p { color: #2563eb; font-size: .7rem; font-weight: 750; letter-spacing: .12em; }
h2 { color: #172033; font-size: 1.5rem; font-weight: 730; }
.close-button { background: #f1f5f9; border: 0; border-radius: 50%; color: #64748b; cursor: pointer; font-size: 1.5rem; height: 36px; line-height: 1; width: 36px; }
form { padding: 1.5rem; }
.form-grid { display: grid; gap: 1rem; grid-template-columns: 1fr 1fr; }
label { color: #475569; display: grid; font-size: .82rem; font-weight: 650; gap: .4rem; }
label b { color: #dc2626; }
input, select { background: #f8fafc; border: 1px solid #dce3eb; border-radius: 10px; color: #172033; font: inherit; height: 44px; outline: none; padding: 0 .8rem; }
input:focus, select:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.12); }
label small { color: #dc2626; font-size: .72rem; font-weight: 500; }
.retry-button { background: transparent; border: 0; color: #2563eb; cursor: pointer; font: inherit; padding: 0 .25rem; text-decoration: underline; }
.full-width { grid-column: 1 / -1; }
footer { border-top: 1px solid #e8edf3; display: flex; gap: .75rem; justify-content: flex-end; margin: 1.5rem -1.5rem -1.5rem; padding: 1.1rem 1.5rem; }
footer button { border-radius: 10px; cursor: pointer; font: inherit; font-weight: 650; height: 42px; padding: 0 1.2rem; }
footer button:disabled { cursor: wait; opacity: .6; }
.cancel-button { background: white; border: 1px solid #dce3eb; color: #475569; }
.save-button { background: #2563eb; border: 1px solid #2563eb; color: white; }
@keyframes appear { from { opacity: 0; transform: translateY(8px) scale(.985); } }
@media (max-width: 560px) {
  .modal-backdrop { align-items: flex-end; padding: 0; }
  .modal { border-radius: 18px 18px 0 0; max-height: 100dvh; }
  header, form { padding: 1rem; }
  .form-grid { grid-template-columns: 1fr; }
  .full-width { grid-column: auto; }
  footer { margin: 1rem -1rem -1rem; padding: 1rem; position: sticky; bottom: 0; background: white; }
  footer button { flex: 1; padding: 0 .75rem; }
}
</style>
