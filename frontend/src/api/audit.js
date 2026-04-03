import { api } from './http'

export async function fetchQueue(windowHours) {
  return api(`/api/audit/loads?window=${windowHours}`)
}

export async function fetchLoad(id) {
  return api(`/api/audit/loads/${id}`)
}

export async function saveLoad(id, payload) {
  return api(`/api/audit/loads/${id}`, {
    method: 'PUT',
    body: JSON.stringify(payload),
  })
}

export async function approveLoad(id) {
  return api(`/api/audit/loads/${id}/approve`, {
    method: 'POST',
  })
}
