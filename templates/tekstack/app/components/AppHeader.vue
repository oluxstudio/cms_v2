<script setup lang="ts">
const olux = useOluxContent('header')
const oluxFb: Record<string, string> = {"Email":"contact@example.com","Email Link":"mailto:contact@example.com","Caption":"+1 1234 56 789","Headline":"Tekstack<span>.</span>"}
const nav = olux.items('Nav', {"Label":"label","Href":"href"}, [
  { label: 'Home', href: '#hero' },
  { label: 'Services', href: '#services' },
  { label: 'Projects', href: '#portfolio', children: [
    { label: 'Projects', href: '#portfolio' },
    { label: 'Project Details', href: '#portfolio' },
  ] },
  { label: 'About', href: '#about' },
  { label: 'Pricing', href: '#pricing' },
  { label: 'Testimonials', href: '#testimonials' },
  { label: 'All Pages', href: '#', children: [
    { label: 'Login', href: '#' }, { label: 'Signup', href: '#' },
    { label: 'FAQs', href: '#faq' }, { label: 'Team', href: '#team' },
    { label: 'Blogs', href: '#recent-posts' }, { label: 'Terms & Conditions', href: '#' },
    { label: 'Privacy Policy', href: '#' },
  ] },
  { label: 'Blogs', href: '#recent-posts' },
  { label: 'Contact', href: '#contact' },
], {})
</script>

<template>
  <section id="topbar" class="topbar d-flex align-items-center" v-if="!olux.hidden()" :style="olux.rootStyle.value" :class="olux.rootClass.value">
    <div class="container d-flex justify-content-center justify-content-md-between">
      <div class="contact-info d-flex align-items-center">
        <i class="bi bi-envelope d-flex align-items-center"><a :href="olux.t('Email Link', oluxFb['Email Link'])">{{ olux.t('Email', oluxFb['Email']) }}</a></i>
        <i class="bi bi-phone d-flex align-items-center ms-4"><span>{{ olux.t('Caption', oluxFb['Caption']) }}</span></i>
      </div>
      <div class="social-links d-none d-md-flex align-items-center">
        <a href="#" class="twitter"><i class="bi bi-twitter"></i></a>
        <a href="#" class="facebook"><i class="bi bi-facebook"></i></a>
        <a href="#" class="instagram"><i class="bi bi-instagram"></i></a>
        <a href="#" class="linkedin"><i class="bi bi-linkedin"></i></a>
      </div>
    </div>
  </section>

  <header id="header" class="single-page-header header d-flex align-items-center">
    <div class="container container-xl d-flex align-items-center justify-content-between">
      <a href="#hero" class="logo d-flex align-items-center">
        <h1 v-html="olux.t('Headline', oluxFb['Headline'])"></h1>
      </a>
      <nav id="navbar" class="navbar">
        <ul>
          <li v-for="item in nav" :key="item.label" :class="{ dropdown: item.children }">
            <a :href="item.href">
              <span v-if="item.children">{{ item.label }}</span>
              <template v-else>{{ item.label }}</template>
              <i v-if="item.children" class="bi bi-chevron-down dropdown-indicator"></i>
            </a>
            <ul v-if="item.children">
              <li v-for="c in item.children" :key="c.label"><a :href="c.href">{{ c.label }}</a></li>
            </ul>
          </li>
        </ul>
      </nav>
      <i class="mobile-nav-toggle mobile-nav-show bi bi-list"></i>
      <i class="mobile-nav-toggle mobile-nav-hide d-none bi bi-x"></i>
    </div>
  </header>
</template>
