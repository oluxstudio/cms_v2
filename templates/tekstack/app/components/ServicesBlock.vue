<script setup lang="ts">
const olux = useOluxContent('services')
const oluxFb: Record<string, string> = {}
const services = olux.items('Service', {"Icon":"icon","Title":"title"}, [
  { icon: 'fa fa-pencil', title: 'Project creation' },
  { icon: 'fa fa-pie-chart', title: 'Software Development' },
  { icon: 'fa fa-tasks', title: 'Project Management' },
  { icon: 'fa fa-tachometer', title: 'Project Impliment' },
  { icon: 'fa fa-recycle', title: 'Software Update' },
  { icon: 'fa fa-handshake-o', title: '24/7 Support' },
], {})
const body = olux.tRef('Body', 'Lorem Ipsum is simply dummy text of the printing and typesetting industry.')
</script>

<template>
  <div id="services" class="the-main-service text-center section" v-if="!olux.hidden()" :style="olux.rootStyle.value" :class="olux.rootClass.value">
    <div class="container">
      <div class="row our-offer-items less-carousel">
        <div v-for="s in services" :key="s.title" class="col-md-4 col-sm-12 equal-height">
          <div class="item">
            <i :class="s.icon"></i>
            <h4>{{ s.title }}</h4>
            <p>{{ body }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
