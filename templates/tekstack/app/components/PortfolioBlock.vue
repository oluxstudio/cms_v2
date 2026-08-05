<script setup lang="ts">
const olux = useOluxContent('portfolio')
const oluxFb: Record<string, string> = {"Headline":"Our Portfolio","Text":"Lorem ipsum dolor sit amet, consectetur adipiscing elit"}
const filters = olux.items('Filter', {"F":"f","Label":"label","Active":"active"}, [
  { f: '*', label: 'All', active: true },
  { f: '.filter-app', label: 'App Design' },
  { f: '.filter-product', label: 'App Development' },
  { f: '.filter-branding', label: 'Branding' },
  { f: '.filter-books', label: 'It Solutions' },
], {})
const items = olux.items('Portfolio', {"Image":"img","Cls":"cls","Title":"title","T Img":"tImg"}, [
  { img: 'portfolio/app-1.jpg', cls: 'filter-app', title: 'App 1', tImg: 'testimonials/testimonial-1.jpg' },
  { img: 'portfolio/product-1.jpg', cls: 'filter-product', title: 'Product 1', tImg: 'testimonials/testimonial-2.jpg' },
  { img: 'portfolio/branding-1.jpg', cls: 'filter-branding', title: 'Branding 1', tImg: 'testimonials/testimonial-3.jpg' },
  { img: 'portfolio/books-1.jpg', cls: 'filter-books', title: 'Books 1', tImg: 'testimonials/testimonial-4.jpg' },
  { img: 'portfolio/app-2.jpg', cls: 'filter-app', title: 'App 2', tImg: 'testimonials/testimonial-1.jpg' },
  { img: 'portfolio/product-2.jpg', cls: 'filter-product', title: 'Product 2', tImg: 'testimonials/testimonial-2.jpg' },
], {"img":"/assets/images/"})
const quote = olux.tRef('Quote', 'Export tempor illum tamen malis malis eram quae irure esse labore quem cillum quid cillum eram malis quorum velit fore eram velit sunt aliqua noster fugiat irure amet legam anim culpa.')
</script>

<template>
  <div id="portfolio" class="portfolio section" v-if="!olux.hidden()" :style="olux.rootStyle.value" :class="olux.rootClass.value">
    <div class="container" data-aos="fade-up">
      <div class="section-header">
        <h2>{{ olux.t('Headline', oluxFb['Headline']) }}</h2>
        <p>{{ olux.t('Text', oluxFb['Text']) }}</p>
      </div>
      <div class="portfolio-isotope" data-portfolio-filter="*" data-portfolio-layout="masonry" data-portfolio-sort="original-order" data-aos="fade-up" data-aos-delay="100">
        <div>
          <ul class="portfolio-flters">
            <li v-for="fl in filters" :key="fl.label" :data-filter="fl.f" :class="{ 'filter-active': fl.active }">{{ fl.label }}</li>
          </ul>
        </div>
        <div class="row gy-4 portfolio-container">
          <div v-for="(it, i) in items" :key="i" class="col-xl-4 col-md-6 portfolio-item" :class="it.cls">
            <div class="portfolio-wrap">
              <a :href="`/assets/images/${it.img}`" data-gallery="portfolio-gallery-app" class="glightbox">
                <img :src="`/assets/images/${it.img}`" class="img-fluid" alt="">
              </a>
              <div class="portfolio-info">
                <h4><a href="#" title="More Details">{{ it.title }}</a></h4>
                <div class="project-deatils-aside">
                  <div class="portfolio-details">
                    <ul>
                      <li><strong>Category</strong> <span>Web design</span></li>
                      <li><strong>Client</strong> <span>New Company</span></li>
                      <li><strong>Project date</strong> <span>12 February, 2022</span></li>
                      <li><strong>Project URL</strong> <a href="#">www.example.com</a></li>
                    </ul>
                  </div>
                  <div class="testimonial-item">
                    <p>
                      <i class="bi bi-quote quote-icon-left"></i>
                      {{ quote }}
                      <i class="bi bi-quote quote-icon-right"></i>
                    </p>
                    <div>
                      <img :src="`/assets/images/${it.tImg}`" class="testimonial-img" alt="">
                      <h3>Jhone Wilsson</h3>
                      <h4>Graphic Designer</h4>
                    </div>
                  </div>
                  <div class="project-btn"><a href="#" class="fill-btn">View Project</a></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
