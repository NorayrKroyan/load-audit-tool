<template>
  <div class="page-shell page-center">
    <div class="login-card">
      <div class="page-title">Load Audit Login</div>

      <div v-if="error" class="state-box state-error">{{ error }}</div>

      <form class="login-form" @submit.prevent="submit">
        <div class="login-row">
          <label class="login-label">Email:</label>
          <input v-model="form.email" class="form-input login-input" type="email" required />
        </div>

        <div class="login-row">
          <label class="login-label">Password:</label>
          <input v-model="form.password" class="form-input login-input" type="password" required />
        </div>

        <div class="login-actions">
          <button class="btn btn-primary" type="submit" :disabled="loading">
            {{ loading ? 'Signing in...' : 'Login' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { login } from '@/api/auth'

const router = useRouter()

const loading = ref(false)
const error = ref('')

const form = reactive({
  email: '',
  password: '',
})

async function submit() {
  loading.value = true
  error.value = ''

  try {
    const response = await login(form)
    localStorage.setItem('load_audit_token', response.token)
    localStorage.setItem('load_audit_user', JSON.stringify(response.user))
    router.push('/audit')
  } catch (err) {
    error.value = err?.data?.message || 'Login failed.'
  } finally {
    loading.value = false
  }
}
</script>
