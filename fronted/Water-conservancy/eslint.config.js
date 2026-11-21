import pluginVue from 'eslint-plugin-vue'
import tsParser from '@typescript-eslint/parser'
import tsPlugin from '@typescript-eslint/eslint-plugin'
import prettierConfig from 'eslint-config-prettier'
import prettierPlugin from 'eslint-plugin-prettier'

export default [
  // 忽略目录
  {
    ignores: ['node_modules/**', 'dist/**', 'public/**', '*.d.ts'],
  },

  // TypeScript & Vue 文件
  {
    files: ['src/**/*.ts', 'src/**/*.tsx', 'src/**/*.vue'],
    languageOptions: {
      parser: tsParser,
      parserOptions: {
        ecmaVersion: 'latest',
        sourceType: 'module',
      },
    },
    plugins: {
      '@typescript-eslint': tsPlugin,
      prettier: prettierPlugin,
    },
    rules: {
      ...tsPlugin.configs.recommended.rules,
      ...prettierConfig.rules,
      'prettier/prettier': 'warn',
      '@typescript-eslint/no-unused-vars': ['warn', { argsIgnorePattern: '^_' }],
      '@typescript-eslint/no-explicit-any': 'warn',
    },
  },

  // Vue 文件（应用 Vue 插件规则）
  ...pluginVue.configs['flat/recommended'],
  {
    files: ['src/**/*.vue'],
    languageOptions: {
      parserOptions: {
        parser: tsParser,
      },
    },
    rules: {
      'vue/multi-word-component-names': 'off',
      'vue/component-name-in-template-casing': ['error', 'PascalCase'],
    },
  },
]
