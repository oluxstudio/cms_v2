<script setup lang="ts">

const olux = useOluxContent('contact')
const oluxFb: Record<string, string> = {"Text":"Get in touch","Headline":"Tell us where it hurts","Text B":"Send a short note about your situation and we will reply within one business day with next steps.","Email":"hello@verita.example","Email Link":"mailto:hello@verita.example"}
</script>

<template>
  <section class="contact" v-if="!olux.hidden()" :style="olux.rootStyle.value" :class="olux.rootClass.value">
    <div class="container">
      <p class="eyebrow">{{ olux.t('Text', oluxFb['Text']) }}</p>
      <h2>{{ olux.t('Headline', oluxFb['Headline']) }}</h2>
      <p>{{ olux.t('Text B', oluxFb['Text B']) }}</p>
      <a class="btn" :href="olux.t('Email Link', oluxFb['Email Link'])">{{ olux.t('Email', oluxFb['Email']) }}</a>
    </div>
  </section>
</template>
