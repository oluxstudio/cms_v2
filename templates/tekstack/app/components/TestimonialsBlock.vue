<script setup lang="ts">
const olux = useOluxContent('testimonials')
const oluxFb: Record<string, string> = {"Headline":"Testimonials","Text":"Lorem ipsum dolor sit amet, consectetur adipiscing elit"}
const items = olux.items('Testimonial', {"Image":"img","Name":"name","Role":"role","Text":"text"}, [
  { img: 'testimonial-1.jpg', name: 'Jhone Doe', role: 'CFO', text: 'Proin iaculis purus consequat sem cure digni ssim donec porttitora entum suscipit rhoncus. Accusantium quam, ultricies eget id, aliquam eget nibh et. Maecen aliquam.' },
  { img: 'testimonial-2.jpg', name: 'Afa Rose', role: 'Web Designer', text: 'Export tempor illum tamen malis malis eram quae irure esse labore quem cillum quid cillum eram malis quorum velit fore eram velit sunt aliqua noster fugiat irure amet.' },
  { img: 'testimonial-3.jpg', name: 'Keena Lara', role: 'Store Owner', text: 'Enim nisi quem export duis labore cillum quae magna enim sint quorum nulla quem veniam duis minim tempor labore quem eram duis noster aute amet eram fore quis minim.' },
  { img: 'testimonial-4.jpg', name: 'Fizzi Brandon', role: 'Freelancer', text: 'Fugiat enim eram quae cillum dolore dolor amet nulla culpa multos export minim fugiat minim velit minim dolor enim duis veniam ipsum anim magna sunt elit dolore.' },
], {"img":"/assets/images/testimonials/"})
</script>

<template>
  <section id="testimonials" class="testimonials" v-if="!olux.hidden()" :style="olux.rootStyle.value" :class="olux.rootClass.value">
    <div class="container" data-aos="fade-up">
      <div class="section-header">
        <h2>{{ olux.t('Headline', oluxFb['Headline']) }}</h2>
        <p>{{ olux.t('Text', oluxFb['Text']) }}</p>
      </div>
      <div class="slides-3 swiper" data-aos="fade-up" data-aos-delay="100">
        <div class="swiper-wrapper">
          <div v-for="(t, i) in items" :key="i" class="swiper-slide">
            <div class="testimonial-wrap">
              <div class="testimonial-item">
                <div class="d-flex align-items-center info-box">
                  <img :src="`/assets/images/testimonials/${t.img}`" class="testimonial-img flex-shrink-0" alt="">
                  <div>
                    <h3>{{ t.name }}</h3>
                    <h4>{{ t.role }}</h4>
                    <div class="stars">
                      <i v-for="n in 5" :key="n" class="bi bi-star-fill"></i>
                    </div>
                  </div>
                </div>
                <p>
                  <i class="bi bi-quote quote-icon-left"></i>
                  {{ t.text }}
                  <i class="bi bi-quote quote-icon-right"></i>
                </p>
              </div>
            </div>
          </div>
        </div>
        <div class="swiper-pagination"></div>
      </div>
    </div>
  </section>
</template>
