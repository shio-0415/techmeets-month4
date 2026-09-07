import js from '@eslint/js';
import globals from 'globals';

export default [
    js.configs.recommended,
    {
        files: ['resources/js/**/*.js'],
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: {
                ...globals.browser,
                Alpine: 'writable',
                axios: 'writable',
            },
        },
        rules: {
            'no-unused-vars': 'warn',
            'no-console': 'warn',
        },
    },
    {
        // ビルド成果物・vendorは対象外
        ignores: ['public/build/**', 'vendor/**', 'node_modules/**', 'storage/**'],
    },
];
