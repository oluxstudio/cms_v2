<script setup lang="ts">
const olux = useOluxContent('footer')
const oluxFb: Record<string, string> = {"Headline":"Tekstack<span>.</span>","Text":"Lorem ipsum dolor amet consectetur adi pisicing elit sed eiusm tempor incididunt ut labore dolore magna aliqua enim ad minim veniam quis.nostrud exercita.laboris nisi ut aliquip ea commodo conse quatuis aute irure.","CTA Label":"Tekstack","CTA Link":"#","CTA B Label":"Terms of Service","CTA B Link":"#","CTA C Label":"Privacy Policy","CTA C Link":"#"}
const services = olux.list('Service', ['Web Design', 'App Developemnt', 'Cloud Services', 'Domain adn Hosting', 'Seo Optimization', 'Social Media'])
const contacts = olux.list('Contact', ['101 West Town', 'PBo 12345, United States', '+1 1234 56 789', 'contact@example.com'])
</script>

<template>
  <footer id="footer" class="main-footer" v-if="!olux.hidden()" :style="olux.rootStyle.value" :class="olux.rootClass.value">
    <div class="container">
      <div class="footer-content">
        <div class="row">
          <div class="col-lg-4 col-md-6 col-sm-12 footer-column">
            <div class="logo-widget footer-widget">
              <a href="#hero" class="logo d-flex align-items-center"><h1 v-html="olux.t('Headline', oluxFb['Headline'])"></h1></a>
              <div class="text">
                <p>{{ olux.t('Text', oluxFb['Text']) }}</p>
              </div>
              <ul class="footer-social">
                <li><a href="#"><i class="fa fa-facebook"></i></a></li>
                <li><a href="#"><i class="fa fa-twitter"></i></a></li>
                <li><a href="#"><i class="fa fa-google"></i></a></li>
              </ul>
            </div>
          </div>
          <div class="col-lg-3 col-md-6 col-sm-12 offset-lg-2 footer-column">
            <div class="service-widget footer-widget">
              <div class="footer-title">Services</div>
              <ul class="list">
                <li v-for="s in services" :key="s"><a href="#">{{ s }}</a></li>
              </ul>
            </div>
          </div>
          <div class="col-lg-3 col-md-6 col-sm-12 footer-column">
            <div class="contact-widget footer-widget">
              <div class="footer-title">Contacts</div>
              <div class="text">
                <p v-for="(c, i) in contacts" :key="i">{{ c }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </footer>

  <div class="footer-bottom">
    <div class="container">
      <div class="row">
        <div class="col-lg-6 col-md-6 col-sm-12 column">
          <div class="copyright"><a :href="olux.t('CTA Link', oluxFb['CTA Link'])">{{ olux.t('CTA Label', oluxFb['CTA Label']) }}</a> &copy; 2022 All Right Reserved</div>
        </div>
        <div class="col-lg-6 col-md-6 col-sm-12 column">
          <ul class="footer-nav">
            <li><a :href="olux.t('CTA B Link', oluxFb['CTA B Link'])">{{ olux.t('CTA B Label', oluxFb['CTA B Label']) }}</a></li>
            <li><a :href="olux.t('CTA C Link', oluxFb['CTA C Link'])">{{ olux.t('CTA C Label', oluxFb['CTA C Label']) }}</a></li>
          </ul>
        </div>
      </div>
    </div>
  </div>

  <a href="#" class="scroll-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>
</template>
