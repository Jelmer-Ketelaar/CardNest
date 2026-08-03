import js from '@eslint/js'
import pluginVue from 'eslint-plugin-vue'
import tseslint from 'typescript-eslint'
import globals from 'globals'

export default tseslint.config(
  {
    ignores: [
      'dist/',
      'node_modules/',
      'playwright-report/',
      'playwright-report-real/',
      'test-results/',
      'test-results-real/',
    ],
  },
  {
    files: ['src/**/*.{ts,vue}', 'tests/**/*.ts', 'playwright*.config.ts'],
    languageOptions: {
      globals: {
        ...globals.browser,
        ...globals.node,
      },
    },
  },
  js.configs.recommended,
  ...tseslint.configs.recommended,
  ...pluginVue.configs['flat/essential'],
  {
    files: ['**/*.vue'],
    languageOptions: {
      parserOptions: {
        parser: tseslint.parser,
      },
    },
  },
)
