<script setup lang="ts">
const olux = useOluxContent('team')
const oluxFb: Record<string, string> = {"Headline":"Our Team","Text":"Lorem ipsum dolor sit amet, consectetur adipiscing elit"}
const members = olux.items('Member', {"Image":"img","Name":"name","Role":"role","Delay":"delay"}, [
  { img: 'team-1.jpg', name: 'Jhone Bi', role: 'Application Manager', delay: 100 },
  { img: 'team-2.jpg', name: 'Sani Awesome', role: 'Social Media', delay: 200 },
  { img: 'team-3.jpg', name: 'Andrio Willi', role: 'Content Writer', delay: 300 },
  { img: 'team-4.jpg', name: 'Afa Jonson', role: 'Business Manager', delay: 400 },
], {"img":"/assets/images/team/"})
</script>

<template>
  <section id="team" class="team sections-bg" v-if="!olux.hidden()" :style="olux.rootStyle.value" :class="olux.rootClass.value">
    <div class="container" data-aos="fade-up">
      <div class="section-header">
        <h2>{{ olux.t('Headline', oluxFb['Headline']) }}</h2>
        <p>{{ olux.t('Text', oluxFb['Text']) }}</p>
      </div>
      <div class="row gy-4">
        <div v-for="m in members" :key="m.name" class="col-xl-3 col-md-6 d-flex" data-aos="fade-up" :data-aos-delay="m.delay">
          <div class="member">
            <img :src="`/assets/images/team/${m.img}`" class="img-fluid" alt="">
            <div class="social">
              <a href="#"><i class="bi bi-twitter"></i></a>
              <a href="#"><i class="bi bi-facebook"></i></a>
              <a href="#"><i class="bi bi-linkedin"></i></a>
              <a href="#"><i class="bi bi-instagram"></i></a>
            </div>
            <h4>{{ m.name }}</h4>
            <span>{{ m.role }}</span>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
