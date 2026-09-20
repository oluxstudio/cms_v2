<template>
  <div class="app-loader">
    <div class="loader-badge">
      <!-- rotating light rays -->
      <svg class="loader-rays" viewBox="0 0 200 200" aria-hidden="true">
        <g fill="#f7941e" opacity=".35">
          <polygon v-for="n in 12" :key="n" points="100,100 94,0 106,0" :transform="`rotate(${n * 30} 100 100)`" />
        </g>
      </svg>
      <!-- badge ring -->
      <span class="loader-ring"></span>
      <!-- glowing cross -->
      <svg class="loader-cross" viewBox="0 0 60 80" aria-hidden="true">
        <path d="M26 0 h8 v24 h20 v8 h-20 v48 h-8 v-48 h-20 v-8 h20 z" fill="#f7941e" />
      </svg>
    </div>
    <p class="loader-title">Christ Apostolic Church</p>
    <p class="loader-motto">One Fold, One Shepherd</p>
    <p class="loader-verse">John 10:16</p>
  </div>
</template>
