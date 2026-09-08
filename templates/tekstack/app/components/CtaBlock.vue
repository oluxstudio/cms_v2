<script setup lang="ts">

const oluxCms = useOluxContent('cta')
const oluxFb: Record<string, string> = {"Subheadline":"Read to start something great?","Text":"Lorem ipsum dolor sit amet, consectetur adipiscing elit","Email":"Conatct Us","Email Link":"mailto:info@example.com"}
</script>

<template>
  <section id="call-to-action" class="call-to-action" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container text-center" data-aos="zoom-out">
      <h3 data-olx-field="subheadline">{{ oluxCms.t('Subheadline', oluxFb['Subheadline']) }}</h3>
      <p data-olx-field="text">{{ oluxCms.t('Text', oluxFb['Text']) }}</p>
      <a data-olx-field="email" class="cta-btn" :href="oluxCms.t('Email Link', oluxFb['Email Link'])">{{ oluxCms.t('Email', oluxFb['Email']) }}</a>
    </div>
  </section>
</template>
