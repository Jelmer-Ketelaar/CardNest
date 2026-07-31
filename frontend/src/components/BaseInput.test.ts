import { createApp, ref } from 'vue'
import BaseInput from './BaseInput.vue'

describe('BaseInput', () => {
  it('binds entered values', () => {
    const value = ref('')
    const element = document.createElement('div')
    const app = createApp(BaseInput, {
      id: 'email',
      label: 'E-mail',
      modelValue: value.value,
      'onUpdate:modelValue': (updatedValue: string) => { value.value = updatedValue },
    })
    app.mount(element)

    const input = element.querySelector('input')
    expect(input).not.toBeNull()
    if (input) {
      input.value = 'collector@example.com'
      input.dispatchEvent(new Event('input'))
    }

    expect(value.value).toBe('collector@example.com')
    app.unmount()
  })
})
