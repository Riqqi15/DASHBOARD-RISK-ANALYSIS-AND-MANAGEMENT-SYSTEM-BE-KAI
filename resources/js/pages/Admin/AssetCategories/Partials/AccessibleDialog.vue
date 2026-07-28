<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue'

const props = defineProps({
  labelledby: { type: String, required: true },
  describedby: { type: String, default: undefined },
  processing: { type: Boolean, default: false },
  panelClass: { type: String, default: '' },
})

const emit = defineEmits(['close'])
const overlay = ref(null)
const dialogRoot = ref(null)
const backgroundState = new Map()
let previousFocus = typeof document !== 'undefined' ? document.activeElement : null

const focusableSelector = [
  'button:not([disabled])',
  'input:not([disabled])',
  'select:not([disabled])',
  'textarea:not([disabled])',
  'a[href]',
  '[tabindex]:not([tabindex="-1"])',
].join(',')

const focusables = () => [...(dialogRoot.value?.querySelectorAll(focusableSelector) ?? [])]
  .filter((element) => element.getAttribute('aria-hidden') !== 'true' && element.tabIndex >= 0)

const focusFirst = () => {
  const initial = dialogRoot.value?.querySelector('[data-dialog-initial-focus]')
  const target = initial?.matches(focusableSelector) && initial.tabIndex >= 0
    ? initial
    : focusables()[0] ?? dialogRoot.value
  target?.focus()
}

const requestClose = () => {
  if (!props.processing) emit('close')
}

const handleKeydown = (event) => {
  if (event.key === 'Escape') {
    event.preventDefault()
    event.stopPropagation()
    requestClose()
    return
  }

  if (event.key !== 'Tab') return
  const available = focusables()
  if (!available.length) {
    event.preventDefault()
    dialogRoot.value?.focus()
    return
  }

  const first = available[0]
  const last = available[available.length - 1]
  const active = document.activeElement
  if (event.shiftKey && (active === first || !dialogRoot.value?.contains(active))) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && (active === last || !dialogRoot.value?.contains(active))) {
    event.preventDefault()
    first.focus()
  }
}

const handleFocusIn = (event) => {
  if (dialogRoot.value && !dialogRoot.value.contains(event.target)) focusFirst()
}

const isolateBackground = () => {
  if (typeof document === 'undefined' || !overlay.value) return
  for (const element of document.body.children) {
    if (element.contains(overlay.value)) continue
    backgroundState.set(element, {
      inert: element.hasAttribute('inert'),
      ariaHidden: element.getAttribute('aria-hidden'),
    })
    element.setAttribute('inert', '')
    element.setAttribute('aria-hidden', 'true')
  }
}

const restoreBackground = () => {
  for (const [element, state] of backgroundState) {
    if (state.inert) element.setAttribute('inert', '')
    else element.removeAttribute('inert')
    if (state.ariaHidden === null) element.removeAttribute('aria-hidden')
    else element.setAttribute('aria-hidden', state.ariaHidden)
  }
  backgroundState.clear()
}

onMounted(async () => {
  document.addEventListener('keydown', handleKeydown, true)
  document.addEventListener('focusin', handleFocusIn, true)
  isolateBackground()
  await nextTick()
  focusFirst()
})

onBeforeUnmount(() => {
  document.removeEventListener('keydown', handleKeydown, true)
  document.removeEventListener('focusin', handleFocusIn, true)
  restoreBackground()
  const focusTarget = previousFocus?.isConnected
    ? previousFocus
    : document.querySelector('[data-dialog-focus-fallback]')
  if (focusTarget?.isConnected && typeof focusTarget.focus === 'function') focusTarget.focus()
  previousFocus = null
})
</script>

<template>
  <Teleport to="body">
    <div ref="overlay" class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-[1px]" @click.self="requestClose">
      <section
        ref="dialogRoot"
        tabindex="-1"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="labelledby"
        :aria-describedby="describedby"
        :aria-busy="processing"
        class="max-h-[calc(100vh-2rem)] overflow-y-auto rounded-2xl border border-slate-200 bg-white outline-none shadow-2xl shadow-slate-950/20"
        :class="panelClass"
      >
        <slot />
      </section>
    </div>
  </Teleport>
</template>
