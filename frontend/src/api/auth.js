import { api } from './http'

export async function login(payload) {
  return api('/api/auth/login', {
    method: 'POST',
    body: JSON.stringify(payload),
  })
}

export async function me() {
  return api('/api/auth/me')
}

export async function logout() {
  return api('/api/auth/logout', {
    method: 'POST',
  })
}
