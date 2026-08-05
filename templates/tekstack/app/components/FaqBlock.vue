<script setup lang="ts">
const olux = useOluxContent('faq')
const oluxFb: Record<string, string> = {"Subheadline":"Frequently Asked <strong>Questions</strong>","Text":"Lorem ipsum dolor sit amet, consectetur adipiscing elit"}
const answer = olux.tRef('Answer', 'Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem.')
const faqs = olux.list('Faq', [
  'What is web domain and hosting?',
  'Which server is best for websites linux or windows?',
  'Google cloud or Amazon server which one is best and fast?',
  'Why Organic SEO is important for all businesses?',
])
</script>

<template>
  <section id="faq" class="faq" v-if="!olux.hidden()" :style="olux.rootStyle.value" :class="olux.rootClass.value">
    <div class="container" data-aos="fade-up">
      <div class="row gy-4">
        <div class="col-lg-12">
          <div class="content text-center">
            <h3 v-html="olux.t('Subheadline', oluxFb['Subheadline'])"></h3>
            <p>{{ olux.t('Text', oluxFb['Text']) }}</p>
          </div>
        </div>
        <div class="col-lg-12">
          <div class="accordion accordion-flush" id="faqlist" data-aos="fade-up" data-aos-delay="100">
            <div v-for="(q, i) in faqs" :key="i" class="accordion-item">
              <h3 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" :data-bs-target="`#faq-content-${i + 1}`">
                  <span class="num"><i class="bi bi-arrow-right-square-fill"></i></span>
                  {{ q }}
                </button>
              </h3>
              <div :id="`faq-content-${i + 1}`" class="accordion-collapse collapse" data-bs-parent="#faqlist">
                <div class="accordion-body">{{ answer }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
