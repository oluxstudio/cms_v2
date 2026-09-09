<script setup lang="ts">
const oluxCms = useOluxContent('faq')
const oluxFb: Record<string, string> = {"Text":"Good to know","Headline":"Frequently Asked Questions","Text B":"Everything about visits, bookings and our products \u2014 and we\u2019re one call away for anything else."}
import { ref, computed } from 'vue'

const { items } = useCms()

// Handcoded fallback — the CMS "faqs" collection overrides these rows.
const fallbackFaqs = oluxCms.items('Fallback Faq', {"Title":"title","Text":"text"}, [
  { title: 'Do I need to book an appointment?', text: 'Walk-ins are welcome when a chair is free, but booking through our appointment page guarantees your slot with your preferred stylist.' },
  { title: 'How early should I arrive?', text: 'Five to ten minutes before your appointment is perfect — it gives us time for a quick consultation before we start.' },
  { title: 'Can I reschedule or cancel?', text: 'Of course. Use the link in your confirmation email, or give us a call at least 24 hours ahead so we can offer the slot to someone else.' },
  { title: 'Do you sell the products you use?', text: 'Yes — everything we use in the salon is available in our shop, with delivery straight to your door.' },
], {})
const faqs = computed(() => items('faqs', fallbackFaqs))
const openIdx = ref(0)
</script>

<template>
  <section class="faq" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <div class="section-head">
        <p data-olx-field="text" class="eyebrow">{{ oluxCms.t('Text', oluxFb['Text']) }}</p>
        <h2 data-olx-field="headline">{{ oluxCms.t('Headline', oluxFb['Headline']) }}</h2>
        <p data-olx-field="textB" class="lead">{{ oluxCms.t('Text B', oluxFb['Text B']) }}</p>
      </div>

      <div class="list" data-olx-key="faqs" data-olx-kind="collection">
        <div v-for="(f, i) in faqs" :key="f.title" class="item" :class="{ on: openIdx === i }" data-olx-item>
          <button type="button" class="q" @click="openIdx = openIdx === i ? -1 : i">
            <span data-olx-field="title">{{ f.title }}</span>
            <b aria-hidden="true">{{ openIdx === i ? '−' : '+' }}</b>
          </button>
          <p class="a" data-olx-field="text">{{ f.text }}</p>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.faq { padding: 96px 0; }
.container { max-width: 820px; margin: 0 auto; padding: 0 24px; }
.eyebrow { letter-spacing: .18em; text-transform: uppercase; font-size: 12px; opacity: .6; }
h2 { font-size: clamp(28px, 4vw, 40px); margin: 8px 0 10px; }
.lead { opacity: .7; margin-bottom: 36px; }
.item { border-bottom: 1px solid rgba(0,0,0,.08); }
.q { width: 100%; display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 18px 0; background: none; border: 0; cursor: pointer; font: inherit; font-weight: 700; font-size: 16px; text-align: left; color: inherit; }
.q b { font-size: 20px; opacity: .5; }
.a { display: none; padding: 0 0 18px; opacity: .7; line-height: 1.6; margin: 0; }
.item.on .a { display: block; }
</style>
