<script setup lang="ts">
const oluxCms = useOluxContent('blog')
const oluxFb: Record<string, string> = {"Headline":"Recent Blog Posts","Text":"Lorem ipsum dolor sit amet, consectetur adipiscing elit"}
const posts = oluxCms.items('Post', {"Image":"img","Category":"category","Title":"title","Author":"author","Date":"date","Datetime":"datetime"}, [
  { img: 'blog-1.jpg', category: 'Domain & Hosting', title: 'How to host website on any hosting provider?', author: 'William Bla', date: 'Feb 1, 2022', datetime: '2022-02-01' },
  { img: 'blog-2.jpg', category: 'Advertisement', title: 'How to create add on google adwords?', author: 'Jobi Ret', date: 'Oct 5, 2022', datetime: '2022-10-05' },
  { img: 'blog-3.jpg', category: 'Marketing', title: 'What is digital marketing and why is important?', author: 'Main Dow', date: 'Dec 22, 2022', datetime: '2022-12-22' },
], {"img":"/assets/images/blog/"})
</script>

<template>
  <section id="recent-posts" class="recent-posts sections-bg" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container" data-aos="fade-up">
      <div class="section-header">
        <h2 data-olx-field="headline">{{ oluxCms.t('Headline', oluxFb['Headline']) }}</h2>
        <p data-olx-field="text">{{ oluxCms.t('Text', oluxFb['Text']) }}</p>
      </div>
      <div class="row gy-4">
        <div v-for="(p, i) in posts" :key="i" class="col-lg-4">
          <article>
            <div class="post-img">
              <img :src="`/assets/images/blog/${p.img}`" alt="" class="img-fluid">
            </div>
            <p class="post-category">{{ p.category }}</p>
            <h2 class="title"><a href="#">{{ p.title }}</a></h2>
            <div class="d-flex align-items-center">
              <div class="post-meta">
                <p class="post-author">{{ p.author }}</p>
                <p class="post-date"><time :datetime="p.datetime">{{ p.date }}</time></p>
              </div>
            </div>
          </article>
        </div>
      </div>
    </div>
  </section>
</template>
