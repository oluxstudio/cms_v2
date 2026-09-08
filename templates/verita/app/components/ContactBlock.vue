<script setup lang="ts">

const oluxCms = useOluxContent('contact')
const oluxFb: Record<string, string> = {"Text":"Get in touch","Headline":"Tell us where it hurts","Text B":"Send a short note about your situation and we will reply within one business day with next steps.","Email":"hello@verita.example","Email Link":"mailto:hello@verita.example"}
</script>

<template>
  <section class="contact" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <p data-olx-field="text" class="eyebrow">{{ oluxCms.t('Text', oluxFb['Text']) }}</p>
      <h2 data-olx-field="headline">{{ oluxCms.t('Headline', oluxFb['Headline']) }}</h2>
      <p data-olx-field="textB">{{ oluxCms.t('Text B', oluxFb['Text B']) }}</p>
      <a data-olx-field="email" class="btn" :href="oluxCms.t('Email Link', oluxFb['Email Link'])">{{ oluxCms.t('Email', oluxFb['Email']) }}</a>
    </div>
  </section>
</template>
