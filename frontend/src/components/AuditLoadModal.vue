<template>
  <div class="audit-modal-backdrop" @click.self="closeModal">
    <div class="audit-modal">
      <div class="audit-modal-header">
        <div class="audit-modal-title-wrap">
          <div class="audit-modal-title">Audit Load #{{ props.loadId }}</div>
          <div class="audit-modal-subtitle">Entered by {{ enteredBy }}</div>
        </div>

        <button type="button" class="modal-close-btn" @click="closeModal">Close</button>
      </div>

      <div v-if="error" class="modal-alert modal-alert-error">{{ error }}</div>

      <div v-if="loading" class="modal-loading">Loading...</div>

      <div v-else class="audit-modal-body">
        <div
            class="audit-panel audit-details-panel"
            :class="{ 'audit-details-panel-editing': isEditing, 'audit-details-panel-clickable': !isEditing }"
            @click="enterEditMode"
        >
          <div v-if="!isEditing" class="details-readonly">
            <div class="details-row">
              <div class="details-label">Journey:</div>
              <div class="details-value">{{ item?.journey || '-' }}</div>
            </div>

            <div class="details-row">
              <div class="details-label">Load Date:</div>
              <div class="details-value">{{ formatDisplayDate(item?.fields?.load_date) }}</div>
            </div>

            <div class="details-row">
              <div class="details-label">Load Number:</div>
              <div class="details-value">{{ item?.fields?.load_number || '-' }}</div>
            </div>

            <div class="details-row">
              <div class="details-label">Ticket Number:</div>
              <div class="details-value">{{ item?.fields?.ticket_number || '-' }}</div>
            </div>

            <div class="details-row">
              <div class="details-label">Net Lbs:</div>
              <div class="details-value">{{ item?.fields?.net_lbs ?? '-' }}</div>
            </div>

            <div class="details-row">
              <div class="details-label">Delivery Date:</div>
              <div class="details-value">{{ formatDisplayDateTime(item?.fields?.delivery_time) }}</div>
            </div>

            <div class="details-row">
              <div class="details-label">Truck Number:</div>
              <div class="details-value">{{ item?.fields?.truck_number || '-' }}</div>
            </div>

            <div class="details-row">
              <div class="details-label">Trailer Number:</div>
              <div class="details-value">{{ item?.fields?.trailer_number || '-' }}</div>
            </div>

            <div class="details-row">
              <div class="details-label">Load Notes:</div>
              <div class="details-value">{{ item?.fields?.load_notes || '-' }}</div>
            </div>

            <div class="details-row">
              <div class="details-label">Accessorial Description:</div>
              <div class="details-value">{{ item?.fields?.acc_desc || '-' }}</div>
            </div>

            <div class="details-row">
              <div class="details-label">Accessorial Amount:</div>
              <div class="details-value">{{ item?.fields?.acc_amt ?? '-' }}</div>
            </div>
          </div>

          <div v-else class="details-edit" @click.stop>
            <div class="details-edit-note">Editing</div>

            <div class="form-row">
              <label class="form-label">Journey:</label>
              <div class="form-readonly input-journey">{{ item?.journey || '-' }}</div>
            </div>

            <div class="form-row">
              <label class="form-label" for="load_date">Load Date:</label>
              <input id="load_date" v-model="form.load_date" type="date" class="form-input input-date" />
            </div>

            <div class="form-row">
              <label class="form-label" for="load_number">Load Number:</label>
              <input id="load_number" v-model="form.load_number" type="text" class="form-input input-code" />
            </div>

            <div class="form-row">
              <label class="form-label" for="ticket_number">Ticket Number:</label>
              <input id="ticket_number" v-model="form.ticket_number" type="text" class="form-input input-code" />
            </div>

            <div class="form-row">
              <label class="form-label" for="net_lbs">Net Lbs:</label>
              <input id="net_lbs" v-model="form.net_lbs" type="number" class="form-input input-weight" />
            </div>

            <div class="form-row">
              <label class="form-label" for="delivery_time">Delivery Date:</label>
              <input id="delivery_time" v-model="form.delivery_time" type="datetime-local" class="form-input input-datetime" />
            </div>

            <div class="form-row">
              <label class="form-label" for="truck_number">Truck Number:</label>
              <input id="truck_number" v-model="form.truck_number" type="text" class="form-input input-vehicle" />
            </div>

            <div class="form-row">
              <label class="form-label" for="trailer_number">Trailer Number:</label>
              <input id="trailer_number" v-model="form.trailer_number" type="text" class="form-input input-vehicle" />
            </div>

            <div class="form-row">
              <label class="form-label" for="load_notes">Load Notes:</label>
              <input id="load_notes" v-model="form.load_notes" type="text" class="form-input input-notes" />
            </div>

            <div class="form-row">
              <label class="form-label" for="acc_desc">Accessorial Description:</label>
              <input id="acc_desc" v-model="form.acc_desc" type="text" class="form-input input-accessorial" />
            </div>

            <div class="form-row">
              <label class="form-label" for="acc_amt">Accessorial Amount:</label>
              <input id="acc_amt" v-model="form.acc_amt" type="number" step="0.01" class="form-input input-money" />
            </div>
          </div>
        </div>

        <div class="audit-panel audit-bol-panel">
          <div class="bol-title">BOL Viewer</div>

          <div class="bol-viewer">
            <div v-if="!bol" class="bol-empty">No BOL found.</div>

            <div v-else class="bol-stage">
              <iframe
                  v-if="isPdfBol"
                  :src="bolFrameSrc"
                  class="bol-frame"
                  title="BOL PDF"
              />

              <img
                  v-else
                  :src="bol.url"
                  alt="BOL"
                  class="bol-image"
                  :style="bolImageStyle"
              />
            </div>
          </div>

          <div class="bol-toolbar">
            <button type="button" class="btn btn-light" :disabled="!bol" @click="zoomOut">Zoom out</button>
            <button type="button" class="btn btn-light" :disabled="!bol" @click="zoomIn">Zoom in</button>
            <div class="bol-zoom-text" v-if="bol">{{ zoomPercent }}%</div>
          </div>
        </div>
      </div>

      <div class="audit-modal-footer">
        <div class="footer-left">
          <span v-if="item?.audited_at" class="footer-meta">Approved at {{ formatDisplayDateTime(item.audited_at) }}</span>
        </div>

        <div class="footer-actions">
          <button
              type="button"
              class="btn btn-primary"
              :disabled="saving || approving || approvingNext || !isEditing"
              @click="saveChanges"
          >
            {{ saving ? 'Saving...' : 'Save' }}
          </button>

          <button
              type="button"
              class="btn btn-success"
              :disabled="saving || approving || approvingNext || !canApprove"
              @click="approveLoad"
          >
            {{ approving ? 'Approving...' : 'Approve' }}
          </button>

          <button
              type="button"
              class="btn btn-success-alt"
              :disabled="saving || approving || approvingNext || !canApprove"
              @click="approveAndOpenNext"
          >
            {{ approvingNext ? 'Approving...' : 'Approve - Open Next Load' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'

const props = defineProps({
  loadId: {
    type: [Number, String],
    required: true,
  },
})

const emit = defineEmits(['close', 'saved', 'approved', 'approved-next'])

const AUDIT_BASE = '/api/audit/loads'

const loading = ref(false)
const saving = ref(false)
const approving = ref(false)
const approvingNext = ref(false)
const error = ref('')
const item = ref(null)
const isEditing = ref(false)
const zoomLevel = ref(1)

const form = ref({
  load_date: '',
  delivery_time: '',
  load_number: '',
  ticket_number: '',
  net_lbs: '',
  truck_number: '',
  trailer_number: '',
  load_notes: '',
  acc_desc: '',
  acc_amt: '',
})

const enteredBy = computed(() => {
  return item.value?.dispatcher?.name || item.value?.dispatcher_name || 'Unknown'
})

const bol = computed(() => item.value?.bol || null)

const isPdfBol = computed(() => {
  return bol.value?.type === 'pdf'
})

const zoomPercent = computed(() => Math.round(zoomLevel.value * 100))

const bolFrameSrc = computed(() => {
  if (!bol.value?.url) return ''
  if (isPdfBol.value) {
    return `${bol.value.url}#toolbar=0&navpanes=0&scrollbar=1&zoom=${zoomPercent.value}`
  }
  return bol.value.url
})

const bolImageStyle = computed(() => {
  return {
    width: `${zoomPercent.value}%`,
    maxWidth: 'none',
    height: 'auto',
  }
})

const canApprove = computed(() => {
  return !!item.value?.can_audit && !item.value?.audit_user_id
})

function getAuthHeaders(includeJson = false) {
  const headers = {
    Accept: 'application/json',
  }

  const token = localStorage.getItem('load_audit_token')
  if (token) {
    headers.Authorization = `Bearer ${token}`
  }

  if (includeJson) {
    headers['Content-Type'] = 'application/json'
  }

  return headers
}

async function requestJson(url, options = {}) {
  const response = await fetch(url, {
    ...options,
    headers: {
      ...getAuthHeaders(!!options.body),
      ...(options.headers || {}),
    },
  })

  const text = await response.text()
  let data = {}

  try {
    data = text ? JSON.parse(text) : {}
  } catch {
    data = text ? { message: text } : {}
  }

  if (!response.ok) {
    const err = new Error(data?.message || `Request failed (${response.status})`)
    err.status = response.status
    err.data = data
    throw err
  }

  return data
}

function parseDateParts(value) {
  if (value == null || value === '') return null

  const raw = String(value).trim()

  let match = raw.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?$/)
  if (match) {
    return {
      year: match[1],
      month: match[2],
      day: match[3],
      hour: match[4] || '00',
      minute: match[5] || '00',
      second: match[6] || '00',
    }
  }

  match = raw.match(/^(\d{2})-(\d{2})-(\d{4})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?$/)
  if (match) {
    return {
      month: match[1],
      day: match[2],
      year: match[3],
      hour: match[4] || '00',
      minute: match[5] || '00',
      second: match[6] || '00',
    }
  }

  return null
}

function formatDisplayDate(value) {
  const parts = parseDateParts(value)
  if (!parts) return value || '-'
  return `${parts.month}-${parts.day}-${parts.year}`
}

function formatDisplayDateTime(value) {
  const parts = parseDateParts(value)
  if (!parts) return value || '-'
  return `${parts.month}-${parts.day}-${parts.year} ${parts.hour}:${parts.minute}`
}

function dateToInput(value) {
  const parts = parseDateParts(value)
  if (!parts) return ''
  return `${parts.year}-${parts.month}-${parts.day}`
}

function dateTimeToInput(value) {
  const parts = parseDateParts(value)
  if (!parts) return ''
  return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`
}

function emptyToNull(value) {
  return value === '' || value == null ? null : value
}

function normalizeFormFromItem(data) {
  const fields = data?.fields || {}

  form.value = {
    load_date: dateToInput(fields.load_date),
    delivery_time: dateTimeToInput(fields.delivery_time),
    load_number: fields.load_number ?? '',
    ticket_number: fields.ticket_number ?? '',
    net_lbs: fields.net_lbs ?? '',
    truck_number: fields.truck_number ?? '',
    trailer_number: fields.trailer_number ?? '',
    load_notes: fields.load_notes ?? '',
    acc_desc: fields.acc_desc ?? '',
    acc_amt: fields.acc_amt ?? '',
  }
}

function buildPayload() {
  return {
    load_date: emptyToNull(form.value.load_date),
    delivery_time: emptyToNull(form.value.delivery_time ? form.value.delivery_time.replace('T', ' ') : null),
    load_number: emptyToNull(form.value.load_number),
    ticket_number: emptyToNull(form.value.ticket_number),
    net_lbs: form.value.net_lbs === '' ? null : Number(form.value.net_lbs),
    truck_number: emptyToNull(form.value.truck_number),
    trailer_number: emptyToNull(form.value.trailer_number),
    load_notes: emptyToNull(form.value.load_notes),
    acc_desc: emptyToNull(form.value.acc_desc),
    acc_amt: form.value.acc_amt === '' ? null : Number(form.value.acc_amt),
  }
}

function isAlreadyAuditedConflict(err) {
  const message = String(err?.message || '').toLowerCase()
  return err?.status === 409 || message.includes('already audited')
}

async function loadItem() {
  loading.value = true
  error.value = ''

  try {
    const data = await requestJson(`${AUDIT_BASE}/${props.loadId}`, {
      method: 'GET',
    })

    item.value = data.item || null
    normalizeFormFromItem(item.value)
    zoomLevel.value = 1
    isEditing.value = false
  } catch (err) {
    error.value = err?.message || 'Failed to load audit item.'
  } finally {
    loading.value = false
  }
}

function enterEditMode() {
  if (loading.value) return
  if (saving.value || approving.value || approvingNext.value) return
  if (isEditing.value) return
  isEditing.value = true
}

async function saveChanges() {
  if (!isEditing.value) return

  saving.value = true
  error.value = ''

  try {
    await requestJson(`${AUDIT_BASE}/${props.loadId}`, {
      method: 'PUT',
      body: JSON.stringify(buildPayload()),
    })

    await loadItem()
    emit('saved')
  } catch (err) {
    error.value = err?.message || 'Failed to save changes.'
  } finally {
    saving.value = false
  }
}

async function approveLoad() {
  approving.value = true
  error.value = ''

  try {
    await requestJson(`${AUDIT_BASE}/${props.loadId}/approve`, {
      method: 'POST',
    })

    emit('approved')
  } catch (err) {
    if (isAlreadyAuditedConflict(err)) {
      emit('approved')
      return
    }

    error.value = err?.message || 'Failed to approve load.'
  } finally {
    approving.value = false
  }
}

async function approveAndOpenNext() {
  approvingNext.value = true
  error.value = ''

  try {
    await requestJson(`${AUDIT_BASE}/${props.loadId}/approve`, {
      method: 'POST',
    })

    emit('approved-next', props.loadId)
  } catch (err) {
    if (isAlreadyAuditedConflict(err)) {
      emit('approved-next', props.loadId)
      return
    }

    error.value = err?.message || 'Failed to approve load.'
  } finally {
    approvingNext.value = false
  }
}

function zoomIn() {
  zoomLevel.value = Math.min(2.5, Number((zoomLevel.value + 0.1).toFixed(2)))
}

function zoomOut() {
  zoomLevel.value = Math.max(0.5, Number((zoomLevel.value - 0.1).toFixed(2)))
}

function closeModal() {
  emit('close')
}

watch(
    () => props.loadId,
    () => {
      if (props.loadId) {
        loadItem()
      }
    },
)

onMounted(() => {
  loadItem()
})
</script>

<style scoped>
.audit-modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 2000;
  background: rgba(15, 23, 42, 0.28);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 6px;
  overflow: auto;
}

.audit-modal {
  width: min(1540px, calc(100vw - 12px));
  height: min(900px, calc(100vh - 12px));
  min-width: 980px;
  min-height: 620px;
  max-width: calc(100vw - 12px);
  max-height: calc(100vh - 12px);
  background: #fff;
  border: 1px solid #cfd8e3;
  border-radius: 12px;
  box-shadow: 0 20px 50px rgba(15, 23, 42, 0.16);
  display: flex;
  flex-direction: column;
  resize: both;
  overflow: hidden;
}

.audit-modal-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  padding: 10px 12px;
  border-bottom: 1px solid #e5e7eb;
  flex: 0 0 auto;
}

.audit-modal-title-wrap {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.audit-modal-title {
  font-size: 18px;
  font-weight: 700;
  color: #1f2937;
  line-height: 1.15;
}

.audit-modal-subtitle {
  font-size: 13px;
  color: #6b7280;
  line-height: 1.2;
}

.modal-close-btn {
  height: 36px;
  padding: 0 14px;
  border: 1px solid #d1d5db;
  border-radius: 9px;
  background: #fff;
  color: #374151;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
}

.modal-close-btn:hover {
  background: #f8fafc;
}

.modal-alert {
  margin: 10px 12px 0;
  padding: 10px 12px;
  border-radius: 8px;
  font-size: 13px;
  flex: 0 0 auto;
}

.modal-alert-error {
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #b91c1c;
}

.modal-loading {
  padding: 18px 12px;
  font-size: 14px;
  color: #475569;
  flex: 1 1 auto;
  overflow: auto;
}

.audit-modal-body {
  flex: 1 1 auto;
  display: grid;
  grid-template-columns: 620px minmax(0, 1fr);
  gap: 14px;
  padding: 12px;
  min-height: 0;
  overflow: hidden;
}

.audit-panel {
  border: 1px solid #d8dee8;
  border-radius: 10px;
  background: #f8fafc;
  min-height: 0;
}

.audit-details-panel {
  padding: 10px 12px;
  overflow: auto;
}

.audit-details-panel-clickable {
  cursor: pointer;
}

.audit-details-panel-clickable:hover {
  border-color: #b9c5d3;
  background: #f7fbff;
}

.audit-details-panel-editing {
  background: #fff;
}

.details-readonly {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.details-row {
  display: grid;
  grid-template-columns: 180px 1fr;
  gap: 10px;
  align-items: start;
}

.details-label {
  font-size: 14px;
  font-weight: 700;
  color: #2f3b4a;
  text-align: right;
  line-height: 1.2;
}

.details-value {
  font-size: 14px;
  color: #111827;
  line-height: 1.2;
  word-break: break-word;
}

.details-edit {
  display: flex;
  flex-direction: column;
  gap: 8px;
  align-items: flex-start;
}

.details-edit-note {
  font-size: 12px;
  font-weight: 700;
  color: #2563eb;
  margin-bottom: 2px;
}

.form-row {
  display: grid;
  grid-template-columns: 180px minmax(260px, 610px);
  gap: 10px;
  align-items: center;
  justify-content: start;
}

.form-label {
  font-size: 14px;
  font-weight: 700;
  color: #2f3b4a;
  text-align: right;
  line-height: 1.2;
}

.form-input,
.form-readonly {
  width: 100%;
  max-width: 610px;
  min-height: 34px;
  border: 1px solid #cfd8e3;
  border-radius: 8px;
  padding: 6px 10px;
  font-size: 14px;
  line-height: 1.2;
  background: #fff;
  color: #111827;
  box-sizing: border-box;
  justify-self: start;
}

.form-readonly {
  background: #f8fafc;
  color: #4b5563;
}

.form-input[type="date"],
.form-input[type="datetime-local"] {
  min-width: 0;
}

.input-journey {
  width: 170px;
}

.input-date {
  width: 200px;
}

.input-code {
  width: 180px;
}

.input-weight {
  width: 160px;
}

.input-datetime {
  width: 260px;
}

.input-vehicle {
  width: 185px;
}

.input-notes {
  width: 375px;
}

.input-accessorial {
  width: 375px;
}

.input-money {
  width: 160px;
}

.form-input:focus {
  outline: none;
  border-color: #93c5fd;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
}

.audit-bol-panel {
  display: flex;
  flex-direction: column;
  padding: 10px 12px;
  min-height: 0;
  overflow: hidden;
}

.bol-title {
  font-size: 14px;
  font-weight: 700;
  color: #1f2937;
  margin-bottom: 10px;
  flex: 0 0 auto;
}

.bol-viewer {
  flex: 1 1 auto;
  min-height: 260px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #fff;
  overflow: auto;
  position: relative;
}

.bol-empty {
  padding: 10px 12px;
  font-size: 14px;
  color: #6b7280;
}

.bol-stage {
  position: relative;
  width: 100%;
  height: 100%;
  min-height: 260px;
  overflow: auto;
}

.bol-frame,
.bol-image {
  display: block;
  border: 0;
}

.bol-frame {
  width: 100%;
  height: 100%;
  min-height: 260px;
  background: #fff;
}

.bol-image {
  max-width: none;
}

.bol-toolbar {
  display: flex;
  align-items: center;
  gap: 10px;
  padding-top: 10px;
  flex: 0 0 auto;
}

.bol-zoom-text {
  font-size: 13px;
  font-weight: 600;
  color: #475569;
}

.audit-modal-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 12px 12px;
  border-top: 1px solid #e5e7eb;
  flex: 0 0 auto;
}

.footer-left {
  min-width: 0;
}

.footer-meta {
  font-size: 13px;
  color: #6b7280;
}

.footer-actions {
  margin-left: auto;
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  justify-content: flex-end;
}

.btn {
  height: 36px;
  padding: 0 16px;
  border-radius: 8px;
  border: 1px solid transparent;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
}

.btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.btn-light {
  background: #fff;
  border-color: #d1d5db;
  color: #374151;
}

.btn-light:hover:not(:disabled) {
  background: #f8fafc;
}

.btn-primary {
  background: #5b8def;
  border-color: #5b8def;
  color: #fff;
}

.btn-primary:hover:not(:disabled) {
  background: #4f7fe0;
}

.btn-success {
  background: #16a34a;
  border-color: #16a34a;
  color: #fff;
}

.btn-success:hover:not(:disabled) {
  background: #15803d;
}

.btn-success-alt {
  background: #15803d;
  border-color: #15803d;
  color: #fff;
}

.btn-success-alt:hover:not(:disabled) {
  background: #166534;
}

@media (max-width: 1200px) {
  .audit-modal {
    width: min(calc(100vw - 12px), 1200px);
    min-width: 0;
  }

  .audit-modal-body {
    grid-template-columns: 1fr;
  }

  .bol-viewer,
  .bol-stage,
  .bol-frame {
    min-height: 300px;
  }
}

@media (max-width: 768px) {
  .audit-modal-backdrop {
    padding: 0;
  }

  .audit-modal {
    width: 100%;
    height: 100vh;
    min-width: 0;
    min-height: 0;
    max-width: 100%;
    max-height: 100vh;
    border-radius: 0;
    resize: none;
  }

  .audit-modal-header,
  .audit-modal-footer {
    flex-wrap: wrap;
  }

  .details-row,
  .form-row {
    grid-template-columns: 1fr;
    gap: 4px;
  }

  .details-label,
  .form-label {
    text-align: left;
  }

  .form-input,
  .form-readonly,
  .input-journey,
  .input-date,
  .input-code,
  .input-weight,
  .input-datetime,
  .input-vehicle,
  .input-notes,
  .input-accessorial,
  .input-money {
    width: 100%;
    max-width: 100%;
  }

  .footer-actions {
    width: 100%;
    justify-content: flex-end;
  }
}
</style>
