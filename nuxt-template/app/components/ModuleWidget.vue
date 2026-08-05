<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'

const props = defineProps<{ siteName: string; moduleKey: string; headline?: string; intro?: string }>()

const api = (useRuntimeConfig().public.apiBase || '/').replace(/\/$/, '')
const base = `${api}/api/sites/${encodeURIComponent(props.siteName)}/modules/${encodeURIComponent(props.moduleKey)}`

type Field = { key: string; label: string; type: string; required: boolean; placeholder?: string; options?: string[] }
type Item = { id: number; data: Record<string, any> }

const fields = ref<Field[]>([])
const caps = ref<{ list?: boolean; submit?: boolean }>({})
const items = ref<Item[]>([])
const loading = ref(true)
const form = reactive<Record<string, any>>({})
const status = ref<'idle' | 'saving' | 'done' | 'error'>('idle')
const errors = ref<Record<string, string>>({})
const message = ref('')

const listFields = computed(() => fields.value.slice(0, 4)) // keep cards compact

function resetForm() {
  for (const f of fields.value) form[f.key] = f.type === 'checkbox' ? false : ''
}

async function loadItems() {
  if (!caps.value.list) return
  try { const r: any = await $fetch(`${base}/items`); items.value = r.items || [] } catch (_) { items.value = [] }
}

async function submit() {
  status.value = 'saving'; errors.value = {}; message.value = ''
  try {
    await $fetch(`${base}/items`, {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: { ...form },
    })
    status.value = 'done'
    resetForm()
    await loadItems()
  } catch (e: any) {
    status.value = 'error'
    if (e?.data?.errors) { for (const k in e.data.errors) errors.value[k] = e.data.errors[k][0] }
    message.value = e?.data?.message || 'Could not submit. Please check the form and try again.'
  }
}

onMounted(async () => {
  try {
    const s: any = await $fetch(`${base}/schema`)
    fields.value = s.fields || []
    caps.value = s.capabilities || {}
    resetForm()
    await loadItems()
  } catch (_) { /* module off or missing */ }
  loading.value = false
})
</script>

<template>
  <div class="mw">
    <div class="mw-head">
      <h2 class="mw-title">{{ headline || 'Submit an entry' }}</h2>
      <p v-if="intro" class="mw-intro">{{ intro }}</p>
    </div>

    <div v-if="loading" class="mw-muted">Loading…</div>
    <div v-else-if="!fields.length" class="mw-muted">This module isn’t available right now.</div>

    <div v-else class="mw-grid">
      <!-- Submit form -->
      <div v-if="caps.submit" class="mw-col">
        <div v-if="status === 'done'" class="mw-success">
          <div class="mw-check">✓</div>
          <p>Thanks — your entry was submitted.</p>
          <button type="button" class="mw-link" @click="status = 'idle'">Submit another</button>
        </div>
        <form v-else class="mw-form" @submit.prevent="submit">
          <div v-for="f in fields" :key="f.key" class="mw-field">
            <label class="mw-label">{{ f.label }}<span v-if="f.required" class="mw-req">*</span></label>
            <textarea v-if="f.type === 'textarea'" v-model="form[f.key]" :placeholder="f.placeholder || ''" rows="3" class="mw-input"></textarea>
            <select v-else-if="f.type === 'select' || f.type === 'radio'" v-model="form[f.key]" class="mw-input">
              <option value="">Choose…</option>
              <option v-for="o in (f.options || [])" :key="o" :value="o">{{ o }}</option>
            </select>
            <label v-else-if="f.type === 'checkbox'" class="mw-check-row">
              <input type="checkbox" v-model="form[f.key]"> <span>Yes</span>
            </label>
            <input v-else :type="['email','number','date','url','tel'].includes(f.type) ? f.type : 'text'"
                   v-model="form[f.key]" :placeholder="f.placeholder || ''" class="mw-input">
            <p v-if="errors[f.key]" class="mw-err">{{ errors[f.key] }}</p>
          </div>
          <p v-if="status === 'error' && message" class="mw-err">{{ message }}</p>
          <button type="submit" class="mw-submit" :disabled="status === 'saving'">
            {{ status === 'saving' ? 'Submitting…' : 'Submit' }}
          </button>
        </form>
      </div>

      <!-- Public list -->
      <div v-if="caps.list" class="mw-col">
        <p class="mw-label">Entries</p>
        <div v-if="!items.length" class="mw-muted">No entries yet.</div>
        <div v-else class="mw-cards">
          <div v-for="it in items" :key="it.id" class="mw-card">
            <div v-for="f in listFields" :key="f.key" class="mw-cell">
              <span class="mw-cell-label">{{ f.label }}</span>
              <span class="mw-cell-val">{{ it.data[f.key] || '—' }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.mw { width: 100%; max-width: 900px; margin: 0 auto; }
.mw-head { text-align: center; margin-bottom: 1.5rem; }
.mw-title { font-size: clamp(1.6rem, 3vw, 2.2rem); font-weight: 800; }
.mw-intro { color: #6b7280; margin-top: .4rem; }
.mw-muted { color: #6b7280; font-size: .9rem; }
.mw-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; }
.mw-grid > .mw-col:only-child { grid-column: 1 / -1; max-width: 520px; margin: 0 auto; }
@media (max-width: 700px) { .mw-grid { grid-template-columns: 1fr; } }
.mw-form { display: flex; flex-direction: column; gap: .8rem; }
.mw-field { display: flex; flex-direction: column; gap: .3rem; }
.mw-label { font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; }
.mw-req { color: var(--accent, #6366f1); margin-left: 2px; }
.mw-input { padding: .6rem .75rem; border: 1px solid rgba(0,0,0,.16); border-radius: 10px; font-size: .9rem; font-family: inherit; width: 100%; }
.mw-check-row { display: inline-flex; align-items: center; gap: .5rem; font-size: .9rem; }
.mw-submit { margin-top: .4rem; padding: .7rem; border: 0; border-radius: 10px; background: var(--accent, #6366f1); color: #fff; font-weight: 700; font-size: .9rem; cursor: pointer; }
.mw-submit:disabled { opacity: .6; }
.mw-err { color: #dc2626; font-size: .78rem; }
.mw-cards { display: flex; flex-direction: column; gap: .6rem; }
.mw-card { border: 1px solid rgba(0,0,0,.1); border-radius: 12px; padding: .8rem 1rem; }
.mw-cell { display: flex; justify-content: space-between; gap: 1rem; font-size: .85rem; padding: .15rem 0; }
.mw-cell-label { color: #9ca3af; font-weight: 600; }
.mw-cell-val { color: #1f2937; text-align: right; overflow-wrap: anywhere; }
.mw-success { text-align: center; padding: 1.5rem; }
.mw-check { width: 48px; height: 48px; margin: 0 auto .8rem; border-radius: 50%; background: #d1fae5; color: #059669; font-size: 1.4rem; display: flex; align-items: center; justify-content: center; }
.mw-link { background: none; border: 0; color: var(--accent, #6366f1); font-weight: 600; cursor: pointer; margin-top: .5rem; }
</style>
