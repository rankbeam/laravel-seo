<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { MOSAIC, MOSAIC_COLUMNS, MOSAIC_ROWS } from './commitField'

// Painted, not animated. The canvas is decorative: it carries no information,
// so it is hidden from assistive tech and nothing on the page depends on it
// having rendered. If the script never runs, the hero is simply a flat night
// surface — which is a perfectly good hero.
const canvas = ref<HTMLCanvasElement | null>(null)
const greens = [0, 42, 76, 112, 148]
let values: Uint8Array
let observer: ResizeObserver | null = null
let frame = 0

function draw() {
  const field = canvas.value
  if (!field) return
  const context = field.getContext('2d')
  const width = field.clientWidth
  const height = field.clientHeight
  if (!context || !width || !height) return

  const ratio = Math.min(window.devicePixelRatio || 1, 2)
  const pitch = parseFloat(getComputedStyle(field).getPropertyValue('--commit-pitch')) || 18
  const tile = (pitch * 24) / 28
  const inset = (pitch * 4) / 28
  const columns = Math.ceil(width / pitch)
  const rows = Math.ceil(height / pitch)

  field.width = Math.round(width * ratio)
  field.height = Math.round(height * ratio)
  context.setTransform(ratio, 0, 0, ratio, 0, 0)
  context.clearRect(0, 0, width, height)

  context.fillStyle = getComputedStyle(field).color
  context.fillRect(0, 0, width, height)

  for (let row = 0; row < rows; row += 1) {
    for (let column = 0; column < columns; column += 1) {
      const value = values[(row % MOSAIC_ROWS) * MOSAIC_COLUMNS + (column % MOSAIC_COLUMNS)]
      context.fillStyle = `rgb(0 ${greens[value] ?? 0} 255)`
      context.fillRect(column * pitch + inset, row * pitch + inset, tile, tile)
    }
  }
}

function schedule() {
  cancelAnimationFrame(frame)
  frame = requestAnimationFrame(draw)
}

onMounted(() => {
  values = Uint8Array.from(atob(MOSAIC), (character) => character.charCodeAt(0))
  draw()
  window.addEventListener('resize', schedule)
  if ('ResizeObserver' in window && canvas.value) {
    observer = new ResizeObserver(schedule)
    observer.observe(canvas.value)
  }
})

onBeforeUnmount(() => {
  cancelAnimationFrame(frame)
  window.removeEventListener('resize', schedule)
  observer?.disconnect()
})
</script>

<template>
  <canvas ref="canvas" class="rb-commit-field" aria-hidden="true" />
</template>

<style scoped>
.rb-commit-field {
  --commit-pitch: 18px;
  position: absolute;
  inset: 0;
  z-index: 0;
  display: block;
  width: 100%;
  height: 100%;
  color: #001b66;
  opacity: .3;
  pointer-events: none;
  -webkit-mask-image: radial-gradient(ellipse 120% 105% at 0 0, #000 0 52%, rgba(0, 0, 0, .78) 72%, transparent 100%);
  mask-image: radial-gradient(ellipse 120% 105% at 0 0, #000 0 52%, rgba(0, 0, 0, .78) 72%, transparent 100%);
}
</style>
