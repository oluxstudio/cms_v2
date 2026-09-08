<script setup lang="ts">
// Slide-in basket + floating cart button — rendered by both the shop grid
// and the product detail page; all state lives in useCart().
const { cartOpen, cartCount, cartLines, cartTotal, money, maxFor, setQty, email, errorMsg, paying, checkout,
  name, phone, fulfilment, address1, address2, city, postcode, notes, consent, canCheckout } = useCart()
</script>

<template>
  <div>
    <div v-if="cartOpen" class="cart-wrap" @click.self="cartOpen = false">
      <aside class="cart">
        <header>
          <h3 data-olx-skip>Your basket</h3>
          <button type="button" class="x" aria-label="Close basket" @click="cartOpen = false">✕</button>
        </header>
        <p v-if="!cartLines.length" class="note" data-olx-skip>Your basket is empty.</p>
        <ul v-else>
          <li v-for="l in cartLines" :key="l.p.slug">
            <NuxtLink class="nm" :to="`/shop/${l.p.slug}`" @click="cartOpen = false">{{ l.p.name }}</NuxtLink>
            <span class="qty">
              <button type="button" @click="setQty(l.p.slug, l.qty - 1)">−</button>
              <b>{{ l.qty }}</b>
              <button type="button" :disabled="l.qty >= maxFor(l.p)" @click="setQty(l.p.slug, l.qty + 1)">＋</button>
            </span>
            <b class="ln">{{ money(l.p.price_cents * l.qty) }}</b>
          </li>
        </ul>
        <div v-if="cartLines.length" class="foot">
          <div class="tot"><span data-olx-skip>Total</span><b>{{ money(cartTotal) }}</b></div>

          <p class="lbl" data-olx-skip>Your details</p>
          <input v-model="name" type="text" autocomplete="name" placeholder="Full name *" required>
          <input v-model="email" type="email" autocomplete="email" placeholder="Email *" required>
          <input v-model="phone" type="tel" autocomplete="tel" placeholder="Phone *" required>

          <div class="ful" data-olx-skip>
            <button type="button" :class="{ on: fulfilment === 'delivery' }" @click="fulfilment = 'delivery'">🚚 Delivery</button>
            <button type="button" :class="{ on: fulfilment === 'collection' }" @click="fulfilment = 'collection'">🏪 Collection</button>
          </div>

          <template v-if="fulfilment === 'delivery'">
            <input v-model="address1" type="text" autocomplete="address-line1" placeholder="Address line 1 *" required>
            <input v-model="address2" type="text" autocomplete="address-line2" placeholder="Address line 2">
            <div class="row2">
              <input v-model="city" type="text" autocomplete="address-level2" placeholder="City *" required>
              <input v-model="postcode" type="text" autocomplete="postal-code" placeholder="Postcode *" required>
            </div>
            <textarea v-model="notes" rows="2" placeholder="Delivery notes (e.g. leave with neighbour)"></textarea>
          </template>
          <p v-else class="hint" data-olx-skip>We'll email you when your order is ready to collect.</p>

          <label class="consent" data-olx-skip>
            <input v-model="consent" type="checkbox">
            <span>Email me offers and news (optional)</span>
          </label>

          <p v-if="errorMsg" class="note err">{{ errorMsg }}</p>
          <button type="button" class="btn pay" :disabled="paying || !canCheckout" @click="checkout">
            {{ paying ? 'Starting secure checkout…' : (canCheckout ? 'Checkout securely' : 'Fill in your details to pay') }}
          </button>
        </div>
      </aside>
    </div>

    <button v-if="cartCount && !cartOpen" type="button" class="cart-fab" @click="cartOpen = true">
      🛒 {{ cartCount }}
    </button>
  </div>
</template>

<style scoped>
.note { padding: 12px 18px; border-radius: var(--radius); background: var(--color-surface-2); display: inline-block; margin-bottom: 20px; }
.note.err { background: rgba(220,53,69,.1); color: #90222d; display: block; }
.btn { border: 1px solid var(--color-secondary); border-radius: var(--radius); font-family: var(--font-default); font-weight: 500; font-size: .72rem; letter-spacing: .14em; text-transform: uppercase; cursor: pointer; background: var(--color-secondary); color: #fff; transition: background .3s ease, border-color .3s ease; }
.btn:hover:not(:disabled) { background: var(--color-primary); border-color: var(--color-primary); }
.btn:disabled { opacity: .45; cursor: default; }
.cart-wrap { position: fixed; inset: 0; background: rgba(15,47,71,.4); z-index: 60; display: flex; justify-content: flex-end; }
.cart { width: min(400px, 92vw); background: #fff; color: var(--color-default); height: 100%; padding: 24px; overflow-y: auto; display: flex; flex-direction: column; }
.cart header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
.cart h3 { font-size: 1.4rem; }
.cart .x { border: 0; background: none; font-size: 16px; cursor: pointer; color: var(--color-muted); }
.cart ul { list-style: none; padding: 0; margin: 0; flex: 1; }
.cart li { display: flex; align-items: center; gap: 10px; padding: 12px 0; border-bottom: 1px solid rgba(20,36,46,.1); font-size: .9rem; }
.cart .nm { flex: 1; color: var(--color-secondary); }
.cart .nm:hover { color: var(--color-primary); }
.qty { display: inline-flex; align-items: center; gap: 8px; }
.qty button { width: 24px; height: 24px; border-radius: 999px; border: 1px solid rgba(20,36,46,.2); background: #fff; cursor: pointer; color: var(--color-secondary); }
.qty button:disabled { opacity: .4; cursor: default; }
.foot { margin-top: 16px; }
.tot { display: flex; justify-content: space-between; font-size: 1rem; color: var(--color-secondary); margin-bottom: 12px; }
.foot input { width: 100%; padding: 11px 13px; border-radius: var(--radius); border: 1px solid rgba(20,36,46,.2); margin-bottom: 10px; font-family: var(--font-default); font-size: .9rem; }
.foot input:focus { outline: none; border-color: var(--color-primary); box-shadow: 0 0 0 3px rgba(35,128,191,.14); }
.pay { width: 100%; padding: .95rem; }
.lbl { font-size: .68rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--color-muted); margin: 4px 0 6px; }
.foot textarea { width: 100%; padding: 11px 13px; border-radius: var(--radius); border: 1px solid rgba(20,36,46,.2); margin-bottom: 10px; font-family: var(--font-default); font-size: .9rem; resize: vertical; }
.foot textarea:focus { outline: none; border-color: var(--color-primary); box-shadow: 0 0 0 3px rgba(35,128,191,.14); }
.row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.ful { display: flex; gap: 8px; margin-bottom: 10px; }
.ful button { flex: 1; padding: 9px 10px; border-radius: 999px; border: 1px solid rgba(20,36,46,.2); background: #fff; font-size: .78rem; font-weight: 700; cursor: pointer; color: var(--color-secondary); }
.ful button.on { background: var(--color-secondary); color: #fff; border-color: var(--color-secondary); }
.hint { font-size: .8rem; color: var(--color-muted); margin: 0 0 10px; }
.consent { display: flex; align-items: center; gap: 8px; font-size: .8rem; color: var(--color-muted); margin-bottom: 10px; cursor: pointer; }
.consent input { width: auto; margin: 0; }
.cart-fab { position: fixed; right: 20px; bottom: 20px; z-index: 55; border: 0; border-radius: 999px; padding: 13px 20px; font-family: var(--font-default); font-weight: 600; letter-spacing: .06em; background: var(--color-secondary); color: #fff; cursor: pointer; box-shadow: 0 12px 30px -8px rgba(15,47,71,.5); }
.cart-fab:hover { background: var(--color-primary); }
</style>
