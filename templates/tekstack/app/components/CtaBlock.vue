<script setup lang="ts">

const olux = useOluxContent('cta')
const oluxFb: Record<string, string> = {"Subheadline":"Read to start something great?","Text":"Lorem ipsum dolor sit amet, consectetur adipiscing elit","Email":"Conatct Us","Email Link":"mailto:info@example.com"}
</script>

<template>
  <section id="call-to-action" class="call-to-action" v-if="!olux.hidden()" :style="olux.rootStyle.value" :class="olux.rootClass.value">
    <div class="container text-center" data-aos="zoom-out">
      <h3>{{ olux.t('Subheadline', oluxFb['Subheadline']) }}</h3>
      <p>{{ olux.t('Text', oluxFb['Text']) }}</p>
      <a class="cta-btn" :href="olux.t('Email Link', oluxFb['Email Link'])">{{ olux.t('Email', oluxFb['Email']) }}</a>
    </div>
  </section>
</template>
