<script setup lang="ts">
// anonymous prayer / question box
const message = ref('')
const kind = ref<'prayer' | 'question'>('prayer')
const sent = ref(false)
</script>

<template>
  <section class="yv-engage">
    <div class="container">
      <div class="yv-engage-grid single">
        <!-- anonymous prayer / question box -->
        <div class="yv-card">
          <p class="eyebrow">100% anonymous</p>
          <h3>Prayer & big questions</h3>
          <form v-if="!sent" class="yv-prayer" @submit.prevent="sent = true">
            <div class="kind-row">
              <label :class="{ on: kind === 'prayer' }"><input v-model="kind" type="radio" value="prayer">🙏 Prayer request</label>
              <label :class="{ on: kind === 'question' }"><input v-model="kind" type="radio" value="question">❓ Question</label>
            </div>
            <textarea v-model="message" required :placeholder="kind === 'prayer' ? 'What can we pray about? No names needed…' : 'Ask anything — nothing is off-limits…'"></textarea>
            <button class="btn" type="submit">Send Anonymously</button>
            <p class="yv-fine">Only the youth leaders see this. No login, no name, no trace.</p>
          </form>
          <div v-else class="yv-sent">
            <p>💛 Got it. You're not carrying this alone — the leaders will pray over every message this week.</p>
            <button class="btn ghost" type="button" @click="sent = false; message = ''">Send Another</button>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
