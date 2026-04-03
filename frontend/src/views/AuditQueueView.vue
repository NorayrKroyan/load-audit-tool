<template>
  <div class="page-shell">
    <div class="page-header">
      <div>
        <div class="page-title">Load Audit Queue</div>
      </div>

      <div class="header-actions">
        <button class="btn btn-light" type="button" @click="logoutNow">Logout</button>
      </div>
    </div>

    <div v-if="error" class="state-box state-error">{{ error }}</div>

    <div class="queue-card">
      <div v-if="loading" class="table-loading">Loading...</div>

      <div ref="toolbarParking" class="toolbar-parking">
        <div ref="toolbarWindowHost" class="toolbar-window-host">
          <label class="toolbar-inline-label">Window</label>
          <v-select
              v-model="selectedWindow"
              :options="windowOptions"
              label="label"
              :clearable="false"
              :searchable="false"
              :reduce="(option) => option.value"
              class="toolbar-window-select"
              @update:modelValue="reload"
          />
        </div>
      </div>

      <div class="queue-table-wrap">
        <table ref="queueTable" class="queue-table display compact stripe row-border hover nowrap">
          <thead>
          <tr>
            <th>Driver Name</th>
            <th>Entered By</th>
            <th>Journey</th>
            <th>Load #</th>
            <th>Ticket #</th>
            <th>Net Lbs</th>
            <th>Delivery Date</th>
            <th>Status</th>
          </tr>
          </thead>
          <tbody />
        </table>
      </div>
    </div>

    <AuditLoadModal
        v-if="activeLoadId"
        :load-id="activeLoadId"
        @close="activeLoadId = null"
        @saved="reload"
        @approved="handleApproved"
        @approved-next="handleApprovedNext"
    />
  </div>
</template>

<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import $ from 'jquery'
import 'datatables.net-dt'
import 'datatables.net-dt/css/dataTables.dataTables.css'

import AuditLoadModal from '@/components/AuditLoadModal.vue'
import { fetchQueue } from '@/api/audit'
import { logout } from '@/api/auth'

const router = useRouter()

const loading = ref(false)
const error = ref('')
const items = ref([])
const activeLoadId = ref(null)
const queueTable = ref(null)
const toolbarParking = ref(null)
const toolbarWindowHost = ref(null)

const selectedWindow = ref('all')

const windowOptions = [
  { label: 'Last 24 hours', value: '24' },
  { label: 'Last 48 hours', value: '48' },
  { label: 'Last 72 hours', value: '72' },
  { label: 'Last 1 week', value: '168' },
  { label: 'All', value: 'all' },
]

const DENVER_TIMEZONE = 'America/Denver'

let dataTableInstance = null

function escapeHtml(value) {
  return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;')
}

function parseDateParts(value) {
  if (value == null || value === '') return null

  const raw = String(value).trim()

  let match = raw.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T].*)?$/)
  if (match) {
    return {
      year: match[1],
      month: match[2],
      day: match[3],
    }
  }

  match = raw.match(/^(\d{4})\/(\d{2})\/(\d{2})(?:[ T].*)?$/)
  if (match) {
    return {
      year: match[1],
      month: match[2],
      day: match[3],
    }
  }

  match = raw.match(/^(\d{2})-(\d{2})-(\d{4})(?:[ T].*)?$/)
  if (match) {
    return {
      month: match[1],
      day: match[2],
      year: match[3],
    }
  }

  match = raw.match(/^(\d{2})\/(\d{2})\/(\d{4})(?:[ T].*)?$/)
  if (match) {
    return {
      month: match[1],
      day: match[2],
      year: match[3],
    }
  }

  return null
}

function parseServerDateTimeToUtcDate(value) {
  if (value == null || value === '') return null

  const raw = String(value).trim()

  let match = raw.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?$/)
  if (match) {
    const [, year, month, day, hour = '00', minute = '00', second = '00'] = match
    return new Date(`${year}-${month}-${day}T${hour}:${minute}:${second}Z`)
  }

  match = raw.match(/^(\d{4})\/(\d{2})\/(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?$/)
  if (match) {
    const [, year, month, day, hour = '00', minute = '00', second = '00'] = match
    return new Date(`${year}-${month}-${day}T${hour}:${minute}:${second}Z`)
  }

  const fallback = new Date(raw)
  return Number.isNaN(fallback.getTime()) ? null : fallback
}

function getDateSortValue(value) {
  const parts = parseDateParts(value)
  if (!parts) return ''

  return `${parts.year}${parts.month}${parts.day}`
}

function getDateTimeSortValue(value) {
  const date = parseServerDateTimeToUtcDate(value)
  if (!date) return ''
  return date.getTime()
}

function formatDateTimeToDenver(value) {
  const date = parseServerDateTimeToUtcDate(value)
  if (!date) return value == null || value === '' ? '-' : String(value)

  return new Intl.DateTimeFormat('en-US', {
    timeZone: DENVER_TIMEZONE,
    month: '2-digit',
    day: '2-digit',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  }).format(date)
}

function renderPlain(value, type) {
  const text = value == null || value === '' ? '-' : String(value)
  return type === 'display' ? escapeHtml(text) : text
}

function renderDateTimeDenver(value, type) {
  if (value == null || value === '') {
    return type === 'display' || type === 'filter' ? '-' : ''
  }

  const display = formatDateTimeToDenver(value)
  const sortValue = getDateTimeSortValue(value)

  if (type === 'sort' || type === 'type') {
    return sortValue || String(value)
  }

  if (type === 'display') {
    return escapeHtml(display)
  }

  return display
}

function getDriverName(row) {
  return (
      row?.driver_name ||
      row?.driver?.name ||
      row?.driver?.full_name ||
      row?.driver?.driver_name ||
      row?.driver_full_name ||
      row?.load_driver_name ||
      '-'
  )
}

function getEnteredBy(row) {
  return (
      row?.dispatcher_name ||
      row?.dispatcher?.name ||
      row?.entered_by ||
      row?.entered_by_name ||
      '-'
  )
}

function moveWindowControlBack() {
  if (
      toolbarParking.value &&
      toolbarWindowHost.value &&
      toolbarWindowHost.value.parentNode !== toolbarParking.value
  ) {
    toolbarParking.value.appendChild(toolbarWindowHost.value)
  }
}

function destroyDataTable() {
  moveWindowControlBack()

  if (queueTable.value) {
    $(queueTable.value).off('.auditQueue')
  }

  if (dataTableInstance) {
    dataTableInstance.destroy()
    dataTableInstance = null
  }

  if (queueTable.value) {
    const tbody = queueTable.value.querySelector('tbody')
    if (tbody) {
      tbody.innerHTML = ''
    }
  }

  const wrapper = queueTable.value?.closest('.dataTables_wrapper, .dt-container')
  const customToolbar = wrapper?.querySelector('.queue-dt-toolbar')
  if (customToolbar) {
    customToolbar.remove()
  }
}

function buildTopToolbar() {
  if (!queueTable.value) return

  const wrapper = queueTable.value.closest('.dataTables_wrapper, .dt-container')
  if (!wrapper) return

  const filterBlock = wrapper.querySelector('.dataTables_filter, .dt-search')
  const lengthBlock = wrapper.querySelector('.dataTables_length, .dt-length')

  if (!filterBlock || !lengthBlock) return

  let toolbar = wrapper.querySelector('.queue-dt-toolbar')

  if (!toolbar) {
    toolbar = document.createElement('div')
    toolbar.className = 'queue-dt-toolbar'

    const host = queueTable.value.parentElement
    if (host) {
      host.insertBefore(toolbar, queueTable.value)
    }
  }

  toolbar.appendChild(filterBlock)

  if (toolbarWindowHost.value) {
    toolbar.appendChild(toolbarWindowHost.value)
  }

  toolbar.appendChild(lengthBlock)
}

async function initDataTable() {
  await nextTick()

  if (!queueTable.value) return

  destroyDataTable()

  dataTableInstance = $(queueTable.value).DataTable({
    data: items.value,
    pageLength: 25,
    lengthMenu: [
      [10, 25, 50, 100, -1],
      [10, 25, 50, 100, 'All'],
    ],
    searching: true,
    paging: true,
    info: true,
    ordering: true,
    autoWidth: false,
    deferRender: true,
    responsive: false,
    order: [[6, 'desc']],
    language: {
      search: 'Search:',
      searchPlaceholder: 'Search all columns',
      emptyTable: 'No matching loads found.',
      zeroRecords: 'No matching loads found.',
      lengthMenu: 'Show _MENU_',
      info: 'Showing _START_ to _END_ of _TOTAL_ entries',
      infoEmpty: 'Showing 0 to 0 of 0 entries',
      paginate: {
        previous: 'Prev',
        next: 'Next',
      },
    },
    columns: [
      {
        data: null,
        title: 'Driver Name',
        render(data, type, row) {
          return renderPlain(getDriverName(row), type)
        },
      },
      {
        data: null,
        title: 'Entered By',
        render(data, type, row) {
          return renderPlain(getEnteredBy(row), type)
        },
      },
      {
        data: 'journey',
        title: 'Journey',
        render: renderPlain,
      },
      {
        data: null,
        title: 'Load #',
        render(data, type, row) {
          const loadNumber = row.load_number || '-'

          if (type !== 'display') {
            return loadNumber
          }

          if (row.can_audit) {
            return `<a href="#" class="load-link" data-load-id="${row.id_load}">${escapeHtml(loadNumber)}</a>`
          }

          return `<span class="load-text">${escapeHtml(loadNumber)}</span>`
        },
      },
      {
        data: 'ticket_number',
        title: 'Ticket #',
        render: renderPlain,
      },
      {
        data: 'net_lbs',
        title: 'Net Lbs',
        render: renderPlain,
      },
      {
        data: 'delivery_time',
        title: 'Delivery Date',
        render: renderDateTimeDenver,
      },
      {
        data: null,
        title: 'Status',
        render(data, type, row) {
          const text = row.is_own_load ? 'Own load' : 'Ready'

          if (type !== 'display') {
            return text
          }

          if (row.is_own_load) {
            return '<span class="status-tag status-own">Own load</span>'
          }

          return '<span class="status-tag status-ready">Ready</span>'
        },
      },
    ],
    createdRow(row, data) {
      row.classList.add('queue-row')

      if (data.is_own_load) {
        row.classList.add('queue-row-disabled')
      }

      if (data.can_audit) {
        row.classList.add('queue-row-clickable')
      }
    },
    initComplete() {
      buildTopToolbar()
    },
  })

  buildTopToolbar()

  $(queueTable.value).on('click.auditQueue', 'a.load-link', function (event) {
    event.preventDefault()
    event.stopPropagation()

    const loadId = Number($(this).data('load-id'))
    const row = items.value.find((item) => Number(item.id_load) === loadId)

    if (row) {
      openRow(row)
    }
  })

  $(queueTable.value).on('click.auditQueue', 'tbody tr', function (event) {
    if ($(event.target).closest('a.load-link').length) {
      return
    }

    const rowData = dataTableInstance.row(this).data()

    if (rowData) {
      openRow(rowData)
    }
  })
}

async function reload() {
  loading.value = true
  error.value = ''

  try {
    const response = await fetchQueue(selectedWindow.value)
    items.value = response.items || []
    await initDataTable()
  } catch (err) {
    error.value = err?.data?.message || err?.message || 'Failed to load queue.'
    items.value = []
    destroyDataTable()
  } finally {
    loading.value = false
  }
}

function openRow(row) {
  if (!row?.can_audit) return
  activeLoadId.value = row.id_load
}

function getAuditableLoadIds(excludeId = null) {
  const excluded = excludeId == null ? null : Number(excludeId)

  return items.value
      .filter((item) => item?.can_audit)
      .map((item) => Number(item.id_load))
      .filter((id) => excluded == null || id !== excluded)
}

async function handleApproved() {
  activeLoadId.value = null
  await reload()
}

async function handleApprovedNext(currentLoadId) {
  const currentId = Number(currentLoadId)
  const beforeIds = getAuditableLoadIds()
  const currentIndex = beforeIds.findIndex((id) => id === currentId)

  await reload()

  const afterIds = getAuditableLoadIds(currentId)

  if (!afterIds.length) {
    activeLoadId.value = null
    return
  }

  const safeIndex = currentIndex < 0 ? 0 : Math.min(currentIndex, afterIds.length - 1)
  activeLoadId.value = afterIds[safeIndex]
}

async function logoutNow() {
  try {
    await logout()
  } catch {
    // ignore
  } finally {
    localStorage.removeItem('load_audit_token')
    localStorage.removeItem('load_audit_user')
    router.push('/login')
  }
}

onMounted(reload)

onBeforeUnmount(() => {
  destroyDataTable()
})
</script>

<style scoped>
.page-shell {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.page-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.page-title {
  font-size: 22px;
  font-weight: 700;
  line-height: 1.1;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.queue-card {
  position: relative;
  background: #fff;
  border: 1px solid #dbe2ea;
  border-radius: 10px;
  overflow: hidden;
}

.queue-table-wrap {
  padding: 8px 12px 6px;
  overflow-x: auto;
}

.toolbar-parking {
  display: none;
}

.table-loading {
  padding: 10px 12px 0;
  font-size: 13px;
  font-weight: 600;
  color: #475569;
}

.state-box {
  padding: 10px 12px;
  border-radius: 8px;
  font-size: 13px;
}

.state-error {
  background: #fef2f2;
  color: #b91c1c;
  border: 1px solid #fecaca;
}

.queue-table {
  width: 100% !important;
}

:deep(.dataTables_wrapper),
:deep(.dt-container) {
  font-size: 13px;
  color: #0f172a;
}

:deep(.queue-dt-toolbar) {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  padding: 0 0 6px;
  flex-wrap: wrap;
}

:deep(.queue-dt-toolbar .dataTables_filter),
:deep(.queue-dt-toolbar .dataTables_length),
:deep(.queue-dt-toolbar .dt-search),
:deep(.queue-dt-toolbar .dt-length) {
  float: none !important;
  margin: 0 !important;
}

:deep(.queue-dt-toolbar .dataTables_filter label),
:deep(.queue-dt-toolbar .dataTables_length label),
:deep(.queue-dt-toolbar .dt-search label),
:deep(.queue-dt-toolbar .dt-length label) {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin: 0;
  white-space: nowrap;
  font-size: 13px;
  color: #475569;
}

:deep(.dataTables_wrapper .dataTables_info),
:deep(.dt-container .dt-info) {
  font-size: 13px;
  color: #475569;
}

:deep(.dataTables_wrapper .dataTables_filter input),
:deep(.dataTables_wrapper .dataTables_length select),
:deep(.dt-container .dt-search input),
:deep(.dt-container .dt-length select) {
  height: 32px;
  border: 1px solid #cbd5e1;
  border-radius: 7px;
  background: #fff;
  padding: 4px 8px;
  font-size: 13px;
  outline: none;
}

:deep(.dataTables_wrapper .dataTables_filter input) {
  min-width: 220px;
}

:deep(.dataTables_wrapper .dataTables_filter input:focus),
:deep(.dataTables_wrapper .dataTables_length select:focus),
:deep(.dt-container .dt-search input:focus),
:deep(.dt-container .dt-length select:focus) {
  border-color: #94a3b8;
  box-shadow: 0 0 0 2px rgba(148, 163, 184, 0.15);
}

:deep(.toolbar-window-host) {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

:deep(.toolbar-inline-label) {
  margin: 0;
  white-space: nowrap;
  font-size: 13px;
  color: #475569;
  font-weight: 600;
}

:deep(.toolbar-window-select) {
  min-width: 180px;
}

:deep(.toolbar-window-select .vs__dropdown-toggle) {
  min-height: 32px;
  height: 32px;
  border: 1px solid #cbd5e1;
  border-radius: 7px;
  background: #fff;
  padding: 0 8px;
  box-shadow: none;
}

:deep(.toolbar-window-select .vs__selected) {
  font-size: 13px;
  color: #0f172a;
  margin: 0;
}

:deep(.toolbar-window-select .vs__search) {
  font-size: 13px;
  margin: 0;
  padding: 0;
}

:deep(.toolbar-window-select .vs__actions) {
  padding: 0 2px 0 6px;
}

:deep(.toolbar-window-select .vs__dropdown-menu) {
  font-size: 13px;
}

:deep(table.dataTable thead th) {
  padding: 7px 10px !important;
  font-size: 13px !important;
  font-weight: 700;
  color: #334155;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0 !important;
  white-space: nowrap;
  line-height: 1.1 !important;
}

:deep(table.dataTable thead th:nth-child(3)),
:deep(table.dataTable tbody td:nth-child(3)) {
  min-width: 420px !important;
}

:deep(table.dataTable thead th:not(:first-child)) {
  text-align: center !important;
}

:deep(table.dataTable thead th:first-child) {
  text-align: left !important;
}

:deep(table.dataTable tbody td) {
  padding: 2px 10px !important;
  font-size: 14px !important;
  line-height: 1.05 !important;
  border-bottom: 1px solid #eef2f7 !important;
  white-space: nowrap;
  vertical-align: middle !important;
}

:deep(table.dataTable tbody td:not(:first-child)) {
  text-align: center !important;
}

:deep(table.dataTable tbody td:first-child) {
  text-align: left !important;
}

:deep(table.dataTable.no-footer) {
  border-bottom: 1px solid #e2e8f0 !important;
}

:deep(.queue-row-clickable) {
  cursor: pointer;
}

:deep(.queue-row-clickable:hover td) {
  background: #f8fbff !important;
}

:deep(.queue-row-disabled td) {
  background: #fafafa !important;
  color: #6b7280 !important;
}

:deep(.load-link) {
  color: #2563eb;
  text-decoration: underline;
  font-weight: 600;
  font-size: 14px;
}

:deep(.load-link:hover) {
  color: #1d4ed8;
}

:deep(.load-text) {
  color: #64748b;
  font-size: 14px;
}

:deep(.status-tag) {
  display: inline-flex;
  align-items: center;
  padding: 1px 8px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  line-height: 1.1;
}

:deep(.status-own) {
  color: #92400e;
  background: #fef3c7;
}

:deep(.status-ready) {
  color: #166534;
  background: #dcfce7;
}

:deep(.dataTables_wrapper .dataTables_paginate .paginate_button),
:deep(.dt-container .dt-paging .dt-paging-button) {
  min-width: 30px;
  padding: 4px 8px !important;
  margin-left: 4px;
  border: 1px solid #cbd5e1 !important;
  border-radius: 7px;
  background: #fff !important;
  color: #334155 !important;
  font-size: 13px;
}

:deep(.dataTables_wrapper .dataTables_paginate .paginate_button.current),
:deep(.dt-container .dt-paging .dt-paging-button.current) {
  border-color: #2563eb !important;
  background: #2563eb !important;
  color: #fff !important;
}

:deep(.dataTables_wrapper .dataTables_paginate .paginate_button.disabled),
:deep(.dt-container .dt-paging .dt-paging-button.disabled) {
  opacity: 0.5;
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

.btn-light {
  background: #fff;
  border-color: #d1d5db;
  color: #374151;
}

.btn-light:hover:not(:disabled) {
  background: #f8fafc;
}

@media (max-width: 768px) {
  .page-header,
  .header-actions {
    align-items: stretch;
  }

  :deep(.queue-dt-toolbar) {
    justify-content: flex-start;
  }

  :deep(.dataTables_wrapper .dataTables_info),
  :deep(.dataTables_wrapper .dataTables_paginate),
  :deep(.dt-container .dt-info),
  :deep(.dt-container .dt-paging) {
    float: none !important;
    text-align: left !important;
    margin-bottom: 8px;
  }
}
</style>
