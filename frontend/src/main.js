import './style.css'
import 'vue-select/dist/vue-select.css'

import { createApp } from 'vue'
import vSelect from 'vue-select'
import App from './App.vue'
import router from './router'

const app = createApp(App)

app.component('v-select', vSelect)
app.use(router)
app.mount('#app')
