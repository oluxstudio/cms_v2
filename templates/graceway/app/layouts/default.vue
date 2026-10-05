<template>
  <!-- Teal page frame with the floating white canvas — the Graceway look.
       SiteHeader / SiteFooter stay in each page: the CMS builds a page's
       editable blocks from the component tags in the page file only, so
       chrome moved here would drop out of the Connect editor. -->
  <div class="canvas">
    <slot />
  </div>
</template>
