<script setup lang="ts">
const { field } = useCms()
</script>

<template>
  <section class="cta" data-olx-key="cta" data-olx-kind="component">
    <div class="container">
      <div class="cta-band">
        <div>
          <h2 data-olx-field="heading">{{ field('cta', 'heading', 'Ready for a fresh look?') }}</h2>
          <p data-olx-field="body">{{ field('cta', 'body', 'Chairs fill fast on weekends — reserve yours in under a minute.') }}</p>
        </div>
        <a class="btn" href="/appointment">Book an appointment</a>
      </div>
    </div>
  </section>
</template>
