<script setup lang="ts">
const olux = useOluxContent('pricing')
const oluxFb: Record<string, string> = {"Headline":"Our Pricing","Text":"Lorem ipsum dolor sit amet, consectetur adipiscing elit"}
const features = olux.list('Feature', ['<b>Free</b> Security Service', '<b>1x</b> Security Service', '<b>Unlimited</b> Security Service', '<b>1x</b> Dashboard Access', '<b>3x</b> Job Listings'])
const plans = olux.items('Plan', {"Title":"title","Price":"price","Featured":"featured"}, [
  { title: 'Personal', price: '10', featured: false },
  { title: 'Business', price: '40', featured: true },
  { title: 'Enterprise', price: '77', featured: false },
], {})
</script>

<template>
  <div id="pricing" class="section bg-gray" v-if="!olux.hidden()" :style="olux.rootStyle.value" :class="olux.rootClass.value">
    <div class="container">
      <div class="section-header">
        <h2>{{ olux.t('Headline', oluxFb['Headline']) }}</h2>
        <p>{{ olux.t('Text', oluxFb['Text']) }}</p>
      </div>
      <div class="price-plan-wrapper">
        <div class="row">
          <div v-for="p in plans" :key="p.title" class="col-lg-4 col-md-6">
            <div class="pricing-table" :class="{ 'bg-orange': p.featured }">
              <div class="price-header">
                <h3 class="title">{{ p.title }}</h3>
                <div class="price"><span class="dollar">$</span>{{ p.price }}<span class="month">/Mo</span></div>
              </div>
              <div class="price-body">
                <ul>
                  <li v-for="(f, i) in features" :key="i" v-html="f"></li>
                </ul>
              </div>
              <div class="price-footer">
                <a class="order-btn" href="#">Buy Now</a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
