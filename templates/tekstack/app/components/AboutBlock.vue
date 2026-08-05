<script setup lang="ts">
const olux = useOluxContent('about')
const oluxFb: Record<string, string> = {"Headline":"About Us","Text":"Lorem ipsum dolor sit amet, consectetur adipiscing elit","Image":"/assets/images/aboutus.png"}
const bars = olux.items('Bar', {"Cls":"cls","Skill":"skill"}, [
  { cls: 'bar learning', skill: 'TDD' },
  { cls: 'bar back basic', skill: 'Python' },
  { cls: 'bar back intermediate', skill: 'C#' },
  { cls: 'bar front advanced', skill: 'CSS3' },
  { cls: 'bar front expert', skill: 'HTML5' },
  { cls: 'bar front expert', skill: 'JQuery' },
  { cls: 'bar front advanced', skill: 'React' },
  { cls: 'bar front basic', skill: 'Javascripts' },
  { cls: 'bar front intermediate', skill: 'Angular Js' },
], {})
</script>

<template>
  <section id="about" class="about sections-bg" v-if="!olux.hidden()" :style="olux.rootStyle.value" :class="olux.rootClass.value">
    <div class="container" data-aos="fade-up">
      <div class="section-header">
        <h2>{{ olux.t('Headline', oluxFb['Headline']) }}</h2>
        <p>{{ olux.t('Text', oluxFb['Text']) }}</p>
      </div>
      <div class="row gy-4">
        <div class="col-lg-6">
          <div class="content ps-0" data-aos="fade-right">
            <div v-for="(b, i) in bars" :key="i" :class="b.cls" :data-skill="b.skill"></div>
          </div>
        </div>
        <div class="col-lg-6">
          <img :src="olux.t('Image', oluxFb['Image'])" class="img-fluid rounded-4 mb-4" alt="">
        </div>
      </div>
    </div>
  </section>
</template>
