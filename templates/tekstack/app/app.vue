<script setup lang="ts">
// Initialise the vendor libraries (loaded via CDN in nuxt.config) once the DOM is
// ready, mirroring the template's assets/javascripts/main.js behaviour.
import { onMounted, nextTick } from 'vue'

declare global {
  interface Window { AOS?: any; Swiper?: any; GLightbox?: any }
}

function waitFor(check: () => boolean, tries = 60): Promise<void> {
  return new Promise((resolve) => {
    let n = 0
    const id = setInterval(() => {
      if (check() || ++n >= tries) { clearInterval(id); resolve() }
    }, 50)
  })
}

onMounted(async () => {
  await nextTick()
  // Vendor libs may still be loading from the CDN — wait for them.
  await waitFor(() => !!(window.AOS && window.Swiper && window.GLightbox))

  window.AOS?.init({ duration: 600, easing: 'ease-in-out', once: true, mirror: false })
  window.GLightbox?.({ selector: '.glightbox' })

  if (window.Swiper) {
    new window.Swiper('.testimonials .swiper', {
      loop: true,
      speed: 600,
      autoplay: { delay: 5000 },
      slidesPerView: 'auto',
      spaceBetween: 40,
      pagination: { el: '.testimonials .swiper-pagination', type: 'bullets', clickable: true },
      breakpoints: { 320: { slidesPerView: 1 }, 1200: { slidesPerView: 3 } },
    })
    new window.Swiper('.clients .swiper', {
      loop: true,
      speed: 600,
      autoplay: { delay: 3000 },
      slidesPerView: 'auto',
      spaceBetween: 60,
      breakpoints: { 320: { slidesPerView: 2 }, 480: { slidesPerView: 3 }, 640: { slidesPerView: 4 }, 992: { slidesPerView: 6 } },
    })
  }

  // Mobile nav toggle (replicates main.js).
  const onBody = (e: Event) => {
    const t = e.target as HTMLElement
    if (t.closest('.mobile-nav-toggle')) {
      document.querySelector('#navbar')?.classList.toggle('navbar-mobile')
      t.closest('.mobile-nav-toggle')?.classList.toggle('bi-list')
      t.closest('.mobile-nav-toggle')?.classList.toggle('bi-x')
    }
  }
  document.addEventListener('click', onBody)

  // Scroll-to-top button.
  const scrollTop = document.querySelector('.scroll-top') as HTMLElement | null
  const toggleScrollTop = () => {
    if (scrollTop) scrollTop.style.display = window.scrollY > 100 ? 'flex' : 'none'
  }
  scrollTop?.addEventListener('click', (e) => { e.preventDefault(); window.scrollTo({ top: 0, behavior: 'smooth' }) })
  window.addEventListener('scroll', toggleScrollTop)
  toggleScrollTop()

  // Remove preloader.
  document.querySelector('#preloader')?.remove()
})
</script>

<template>
  <div>
    <NuxtPage />
  </div>
</template>
