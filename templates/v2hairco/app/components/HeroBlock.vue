<script setup lang="ts">
const oluxCms = useOluxContent('hero')
const oluxFb: Record<string, string> = {"Image":"/assets/images/hero.jpg","Image B":"/assets/images/hero-oval.jpg"}
import { computed } from 'vue'

const { field, items } = useCms()

// Handcoded fallback — the CMS "Hero Tags" collection overrides these pills.
const fallbackTags = oluxCms.items('Fallback Tag', {"Label":"label"}, [
  { label: 'Salon-grade products' },
  { label: 'Gentle formulas' },
  { label: 'Curl specialists' },
  { label: 'Personal care plans' },
], {})
const tags = computed(() => items('heroTags', fallbackTags))
</script>

<template>
  <section class="hero" data-olx-key="hero" data-olx-kind="component" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="hero-inner">

      <div class="left">
        <h1 data-olx-field="heading">{{ field('hero', 'heading', 'Feed Your Hair, Fuel Your Confidence') }}</h1>
        <a class="learn-more" href="/about" data-olx-field="learnMoreLabel">{{ field('hero', 'learnMoreLabel', 'Learn more') }}</a>
      </div>

      <div class="portrait">
        <div class="oval">
          <img :src="oluxCms.t('Image', oluxFb['Image'])" alt="Stylist trimming a client's hair" data-olx-field="image">
          <div class="chip">
            <b data-olx-field="statValue">{{ field('hero', 'statValue', '98%') }}</b>
            <span data-olx-field="statLabel">{{ field('hero', 'statLabel', 'Happy clients') }}</span>
          </div>
        </div>
        <a class="shop-badge" href="/appointment" data-olx-field="heroButton">{{ field('hero', 'heroButton', 'Book now') }}</a>
      </div>

      <div class="right">
        <p class="blurb" data-olx-field="lead">{{ field('hero', 'lead', 'Personalised hair care that combines science and beauty — healthier hair with every visit.') }}</p>
        <div class="product-card">
          <p class="card-eyebrow" data-olx-field="caption">{{ field('hero', 'caption', 'Our essentials') }}</p>
          <img :src="oluxCms.t('Image B', oluxFb['Image B'])" alt="Salon treatment product" data-olx-field="overlayImage">
          <p class="card-title" data-olx-field="collectionLabel">{{ field('hero', 'collectionLabel', 'Deep Repair Hair Serum') }}</p>
          <span class="spark" aria-hidden="true">✦</span>
        </div>
      </div>

      <p class="display" data-olx-field="accent">{{ field('hero', 'accent', 'Healthy hair rituals') }}</p>

      <div class="tags" data-olx-key="heroTags" data-olx-kind="collection">
        <span class="tag-lead" aria-hidden="true">✦</span>
        <span v-for="t in tags" :key="t.label" class="tag" data-olx-item>
          <i data-olx-field="label">{{ t.label }}</i>
        </span>
      </div>

    </div>
  </section>
</template>
