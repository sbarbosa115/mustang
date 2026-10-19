import js from '@eslint/js';
import tseslint from 'typescript-eslint';
import react from 'eslint-plugin-react';
import reactHooks from 'eslint-plugin-react-hooks';
import globals from 'globals';
import prettier from 'eslint-config-prettier';
import fsd from './eslint-fsd-boundaries.mjs';
import google from './eslint-google-rules.mjs';

export default tseslint.config(
  {
    ignores: [
      'public/**',
      'vendor/**',
      'var/**',
      'node_modules/**',
      'assets/types/**',
      'e2e/.results/**',
    ],
  },
  js.configs.recommended,
  ...tseslint.configs.recommended,
  {
    files: ['**/*.{ts,tsx}'],
    plugins: {react, 'react-hooks': reactHooks},
    languageOptions: {globals: {...globals.browser}},
    settings: {react: {version: 'detect'}},
    rules: {
      ...react.configs.recommended.rules,
      ...react.configs['jsx-runtime'].rules,
      ...reactHooks.configs.recommended.rules,
      // A missing import renders a blank screen instead of failing the build.
      'react/jsx-no-undef': 'error',
      'react/prop-types': 'off',
      '@typescript-eslint/no-unused-vars': ['error', {argsIgnorePattern: '^_'}],
    },
  },
  {
    files: ['*.{js,mjs,ts,mts}', 'scripts/**', 'e2e/**'],
    languageOptions: {globals: {...globals.node}},
  },
  ...fsd,
  ...google,
  {
    files: ['**/*.{ts,tsx}'],
    rules: {
      // openapi-fetch's methods are api.GET, api.POST…: calls, not constructors.
      'new-cap': [
        'error',
        {capIsNewExceptions: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']},
      ],
      // A component passed as a prop or destructured from one ({icon: Icon}) is PascalCase.
      '@typescript-eslint/naming-convention': [
        'error',
        {
          selector: 'default',
          format: ['camelCase'],
          leadingUnderscore: 'allow',
        },
        {
          selector: 'variable',
          format: ['camelCase', 'UPPER_CASE', 'PascalCase'],
          leadingUnderscore: 'allow',
        },
        {
          selector: 'parameter',
          format: ['camelCase', 'PascalCase'],
          leadingUnderscore: 'allow',
        },
        {selector: 'function', format: ['camelCase', 'PascalCase']},
        {selector: 'typeLike', format: ['PascalCase']},
        {selector: 'enumMember', format: ['PascalCase', 'UPPER_CASE']},
        {
          selector: ['property', 'parameterProperty', 'objectLiteralMethod'],
          format: null,
        },
        {selector: 'import', format: null},
      ],
    },
  },
  prettier,
);
