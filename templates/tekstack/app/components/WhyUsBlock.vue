<script setup lang="ts">
const olux = useOluxContent('why-us')
const oluxFb: Record<string, string> = {"Headline":"Why Us","Text":"Lorem ipsum dolor sit amet, consectetur adipiscing elit","Image":"/assets/images/whyus.jpg","Text B":"Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et\n              dolore magna aliqua. Xonsectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore\n              magna aliqua."}
const points = olux.list('Point', [
  'Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit.',
  'Sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt.',
  'Nemo enim ipsam voluptatem quia voluptas sit aspernatur',
  'Lorem ipsum dolor sit amet, consectetur adipiscing elit',
  'Sed quia consequuntur magni dolores eos qui ratione',
  'Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet.',
  'Consectetur, adipisci velit, sed quia non numquam eius.',
  'Rempora incidunt ut labore et dolore magnam aliquam',
])
</script>

<template>
  <section id="whyus" class="whyus" v-if="!olux.hidden()" :style="olux.rootStyle.value" :class="olux.rootClass.value">
    <div class="container" data-aos="fade-up">
      <div class="section-header">
        <h2>{{ olux.t('Headline', oluxFb['Headline']) }}</h2>
        <p>{{ olux.t('Text', oluxFb['Text']) }}</p>
      </div>
      <div class="row gy-4">
        <div class="col-lg-6">
          <img :src="olux.t('Image', oluxFb['Image'])" class="img-fluid rounded-4 mb-4" alt="">
        </div>
        <div class="col-lg-6">
          <div class="content ps-0 ps-lg-5">
            <p>{{ olux.t('Text B', oluxFb['Text B']) }}</p>
            <ul>
              <li v-for="(p, i) in points" :key="i"><i class="bi bi-arrow-right-square-fill"></i> {{ p }}</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
