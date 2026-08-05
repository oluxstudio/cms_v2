<script setup lang="ts">
const olux = useOluxContent('contact')
const oluxFb: Record<string, string> = {"Headline":"Contact Us","Text":"Lorem ipsum dolor sit amet, consectetur adipiscing elit","Text B":"Send Message"}
const info = olux.items('Info', {"Title":"title"}, [
  { title: 'Address:', lines: ['11 West Town', 'PBo 12345, United States'] },
  { title: 'Phone:', lines: ['+1 1234 56 789', '+1 1234 56 780'] },
  { title: 'Email:', lines: ['info@example.com', 'email@example.com'] },
], {})
</script>

<template>
  <div id="contact" class="contact-section section" v-if="!olux.hidden()" :style="olux.rootStyle.value" :class="olux.rootClass.value">
    <div class="section-header">
      <h2>{{ olux.t('Headline', oluxFb['Headline']) }}</h2>
      <p>{{ olux.t('Text', oluxFb['Text']) }}</p>
    </div>
    <div class="container">
      <div class="row">
        <div class="col-lg-8 col-md-12" data-aos="fade-left">
          <div class="contact-form-box contact-form contact-form-3">
            <div class="form-container-box">
              <form class="contact-form form" @submit.prevent>
                <div class="controls">
                  <div class="row">
                    <div class="col-md-6">
                      <div class="form-group form-input-box">
                        <input type="text" class="form-control" name="name" placeholder="Name*">
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-group form-input-box">
                        <input type="email" class="form-control" name="email" placeholder="Email*">
                      </div>
                    </div>
                    <div class="col-md-12">
                      <div class="form-group form-input-box">
                        <input type="text" class="form-control" name="subject" placeholder="Subject">
                      </div>
                    </div>
                    <div class="col-12">
                      <div class="form-group form-input-box">
                        <textarea class="form-control" name="message" rows="7" placeholder="Write Your Message*"></textarea>
                      </div>
                    </div>
                    <div class="col-md-12">
                      <button type="submit" data-text="Send Message">{{ olux.t('Text B', oluxFb['Text B']) }}</button>
                    </div>
                  </div>
                </div>
              </form>
            </div>
          </div>
        </div>
        <div class="col-lg-4 col-md-12" data-aos="fade-right">
          <div class="contact-information-box-3">
            <div class="row">
              <div v-for="b in info" :key="b.title" class="col-lg-12">
                <div class="single-contact-info-box">
                  <div class="contact-info">
                    <h6>{{ b.title }}</h6>
                    <p v-for="(l, i) in b.lines" :key="i">{{ l }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
