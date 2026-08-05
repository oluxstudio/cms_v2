<script setup lang="ts">
import SiteHeader from '~/components/SiteHeader.vue'
import AppointmentBlock from '~/components/AppointmentBlock.vue'
import TestimonialsBlock from '~/components/TestimonialsBlock.vue'
import SiteFooter from '~/components/SiteFooter.vue'

useHead({ title: 'Book an Appointment — Hair Co.' })

// Blocks render in the CMS-configured order (original order as fallback).
const oluxBlocks: Record<string, any> = { 'site-header': SiteHeader, 'appointment': AppointmentBlock, 'testimonials': TestimonialsBlock, 'site-footer': SiteFooter }
const oluxPage = useOluxPageOrder('/appointment', oluxBlocks)
</script>

<template>
  <div>
    <div id="preloader"></div>
    <component :is="b.comp" v-for="(b, i) in oluxPage" :key="`${b.key}-${i}`" />
  </div>
</template>
