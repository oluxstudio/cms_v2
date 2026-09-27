<script setup lang="ts">
const oluxCms = useOluxContent('books')
const oluxFb: Record<string, string> = {}
// copy and image come from the global data source
const { books } = useSiteContent()
</script>

<template>
  <section id="store" class="books highlighted-section" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <div class="books-grid">
        <div class="books-copy">
          <h2 class="mega" data-olx-field="title">{{ oluxCms.t('Title', books.title) }} <span class="hl" data-olx-field="titleHighlight">{{ oluxCms.t('Title Highlight', books.titleHighlight) }}</span></h2>
          <p class="sub" data-olx-field="sub">{{ oluxCms.t('Sub', books.sub) }}</p>
          <!-- <CtaButton :to="books.cta.to" :label="books.cta.label" /> -->
        </div>
        <div class="books-collage">
          <img :src="oluxCms.t('Img Src', books.img.src)" data-olx-field="imgSrc" :alt="oluxCms.t('Img Alt', books.img.alt)">
        </div>
      </div>
    </div>
  </section>
</template>
