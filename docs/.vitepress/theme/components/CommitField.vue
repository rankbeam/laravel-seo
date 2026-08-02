<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { MOSAIC, MOSAIC_COLUMNS, MOSAIC_ROWS } from './commitField'

// Painted, not animated — but it answers the pointer, exactly as the field on
// rankbeam.dev does (rankbeam-site/assets/js/pattern.js). The squares do not
// move, grow or bend: each one simply fades between its resting dimness and its
// OWN full colour, so the matrix keeps its texture and the ones nearest the
// cursor reach full while the rest taper off. Motion the visitor causes, not
// motion performed at them.
//
// The canvas is decorative: it carries no information, so it is hidden from
// assistive tech and nothing on the page depends on it having rendered. If the
// script never runs, the hero is simply a flat night surface — which is a
// perfectly good hero.
const canvas = ref<HTMLCanvasElement | null>(null)
const greens = [0, 42, 76, 112, 148]

const RADIUS = 200 // px of influence around the pointer

let values: Uint8Array
let observer: ResizeObserver | null = null
let host: HTMLElement | null = null
let frame = 0
// The easing loop keeps its own frame id, separate from the repaint one: docs
// is an SPA, so the hero unmounts on the first navigation and a loop that
// cannot be cancelled would keep waking for frames nobody is looking at.
let pointerFrame = 0
let ticking = false
let alive = true

// Geometry + colour, refreshed on every measure().
let width = 0
let height = 0
let pitch = 18
let tile = 0
let inset = 0
let columns = 0
let rows = 0
let color = ''
// The resting floor is drawn INTO the canvas rather than applied as CSS opacity
// on top of it. With `opacity: .3` on the element a "lit" square could still
// only ever be 30% of itself, and the effect would be invisible.
let rest = 1

const pointer = { x: 0, y: 0, strength: 0, target: 0, moved: false }
let lastBox: { x0: number; y0: number; x1: number; y1: number } | null = null

function context(): CanvasRenderingContext2D | null {
  return canvas.value?.getContext('2d') ?? null
}

function measure(): boolean {
  const field = canvas.value
  const ctx = context()
  if (!field || !ctx) return false

  width = field.clientWidth
  height = field.clientHeight
  if (!width || !height) return false

  const style = getComputedStyle(field)
  const ratio = Math.min(window.devicePixelRatio || 1, 2)
  const declaredRest = parseFloat(style.getPropertyValue('--commit-rest'))

  pitch = parseFloat(style.getPropertyValue('--commit-pitch')) || 18
  tile = (pitch * 24) / 28
  inset = (pitch * 4) / 28
  columns = Math.ceil(width / pitch)
  rows = Math.ceil(height / pitch)
  color = style.color
  rest = isFinite(declaredRest) ? declaredRest : 1

  field.width = Math.round(width * ratio)
  field.height = Math.round(height * ratio)
  ctx.setTransform(ratio, 0, 0, ratio, 0, 0)
  return true
}

/* Paint one rectangle of the field. The whole field is simply the rectangle
   that happens to be the whole canvas. */
function paintRegion(x0: number, y0: number, x1: number, y1: number) {
  const ctx = context()
  if (!ctx) return

  x0 = Math.max(0, x0)
  y0 = Math.max(0, y0)
  x1 = Math.min(width, x1)
  y1 = Math.min(height, y1)
  if (x1 <= x0 || y1 <= y0) return

  const strength = pointer.strength

  ctx.save()
  ctx.beginPath()
  ctx.rect(x0, y0, x1 - x0, y1 - y0)
  ctx.clip()
  ctx.clearRect(x0, y0, x1 - x0, y1 - y0)

  // The backdrop stays at its resting level. Only the squares light up.
  ctx.globalAlpha = rest
  ctx.fillStyle = color
  ctx.fillRect(x0, y0, x1 - x0, y1 - y0)

  const firstColumn = Math.max(0, Math.floor(x0 / pitch) - 1)
  const lastColumn = Math.min(columns, Math.ceil(x1 / pitch) + 1)
  const firstRow = Math.max(0, Math.floor(y0 / pitch) - 1)
  const lastRow = Math.min(rows, Math.ceil(y1 / pitch) + 1)

  for (let row = firstRow; row < lastRow; row += 1) {
    for (let column = firstColumn; column < lastColumn; column += 1) {
      const value = values[(row % MOSAIC_ROWS) * MOSAIC_COLUMNS + (column % MOSAIC_COLUMNS)]
      const x = column * pitch + inset
      const y = row * pitch + inset

      // level: 0 = resting, 1 = fully on. Geometry never changes — the square
      // sits exactly where it sits, at exactly the size it is.
      let level = 0

      if (strength > 0.002) {
        const dx = pointer.x - (x + tile / 2)
        const dy = pointer.y - (y + tile / 2)
        const distance = Math.sqrt(dx * dx + dy * dy)

        if (distance < RADIUS) {
          const t = 1 - distance / RADIUS
          level = t * t * (3 - 2 * t) * strength // smoothstep falloff
        }
      }

      ctx.globalAlpha = rest + (1 - rest) * level
      ctx.fillStyle = `rgb(0 ${greens[value] ?? 0} 255)`
      ctx.fillRect(x, y, tile, tile)
    }
  }

  ctx.globalAlpha = 1
  ctx.restore()
}

function draw() {
  if (!measure()) return
  lastBox = null
  paintRegion(0, 0, width, height)
}

function schedule() {
  cancelAnimationFrame(frame)
  frame = requestAnimationFrame(draw)
}

/* --- pointer reactivity ------------------------------------------------- */

function repaintAroundPointer() {
  const margin = RADIUS + pitch
  const box = {
    x0: pointer.x - margin,
    y0: pointer.y - margin,
    x1: pointer.x + margin,
    y1: pointer.y + margin,
  }
  const last = lastBox

  /* Repaint the union of where the light was and where it now is, so the
     squares it has just left switch off in the same pass. */
  paintRegion(
    last ? Math.min(box.x0, last.x0) : box.x0,
    last ? Math.min(box.y0, last.y0) : box.y0,
    last ? Math.max(box.x1, last.x1) : box.x1,
    last ? Math.max(box.y1, last.y1) : box.y1,
  )

  lastBox = pointer.strength > 0.002 ? box : null
}

function tick() {
  if (!alive) {
    ticking = false
    return
  }

  const gap = pointer.target - pointer.strength
  const settled = Math.abs(gap) < 0.003
  let running = false

  if (!settled) {
    // On quickly, off slowly.
    pointer.strength += gap * (gap > 0 ? 0.25 : 0.12)
    running = true
  } else if (pointer.strength !== pointer.target) {
    pointer.strength = pointer.target
    pointer.moved = true
  }

  if (pointer.moved || !settled) {
    repaintAroundPointer()
    pointer.moved = false
    if (pointer.strength > 0.002) running = true
  }

  ticking = running
  if (running) pointerFrame = requestAnimationFrame(tick)
}

function wake() {
  if (ticking || !alive) return
  ticking = true
  pointerFrame = requestAnimationFrame(tick)
}

// The canvas is pointer-events:none, so the pointer is tracked on its PARENT:
// hovering anywhere in the hero drives it.
function onPointerMove(event: PointerEvent) {
  if (event.pointerType !== 'mouse' && event.pointerType !== 'pen') return
  const field = canvas.value
  if (!field) return
  const rect = field.getBoundingClientRect()
  pointer.x = event.clientX - rect.left
  pointer.y = event.clientY - rect.top
  pointer.target = 1
  pointer.moved = true
  wake()
}

function onPointerLeave() {
  pointer.target = 0
  wake()
}

onMounted(() => {
  alive = true
  values = Uint8Array.from(atob(MOSAIC), (character) => character.charCodeAt(0))
  draw()
  window.addEventListener('resize', schedule)
  if ('ResizeObserver' in window && canvas.value) {
    observer = new ResizeObserver(schedule)
    observer.observe(canvas.value)
  }

  // Off entirely under prefers-reduced-motion and on coarse pointers: there is
  // no hover on a phone, and repainting a background for a finger that is not
  // there is just battery.
  const reactiveOk =
    !window.matchMedia('(prefers-reduced-motion: reduce)').matches &&
    window.matchMedia('(pointer: fine)').matches

  if (reactiveOk) {
    host = canvas.value?.parentElement ?? null
    host?.addEventListener('pointermove', onPointerMove, { passive: true })
    host?.addEventListener('pointerleave', onPointerLeave, { passive: true })
  }
})

onBeforeUnmount(() => {
  alive = false
  cancelAnimationFrame(frame)
  cancelAnimationFrame(pointerFrame)
  window.removeEventListener('resize', schedule)
  observer?.disconnect()
  host?.removeEventListener('pointermove', onPointerMove)
  host?.removeEventListener('pointerleave', onPointerLeave)
  host = null
  ticking = false
})
</script>

<template>
  <canvas ref="canvas" class="rb-commit-field" aria-hidden="true" />
</template>

<style scoped>
.rb-commit-field {
  --commit-pitch: 18px;
  /* The resting floor is drawn into the canvas, not applied as opacity on top
     of it — see the note in the script. 0.3 is the floor, 1.0 the ceiling. */
  --commit-rest: .30;
  position: absolute;
  inset: 0;
  z-index: 0;
  display: block;
  width: 100%;
  height: 100%;
  color: #001b66;
  opacity: 1;
  pointer-events: none;
  -webkit-mask-image: radial-gradient(ellipse 120% 105% at 0 0, #000 0 52%, rgba(0, 0, 0, .78) 72%, transparent 100%);
  mask-image: radial-gradient(ellipse 120% 105% at 0 0, #000 0 52%, rgba(0, 0, 0, .78) 72%, transparent 100%);
}
</style>
