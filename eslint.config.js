import js from '@eslint/js';

export default [
	js.configs.recommended,
	{
		files: ['styles/all/template/js/src/**/*.js'],
		languageOptions: {
			ecmaVersion: 2022,
			sourceType: 'module',
			globals: {
				window: 'readonly',
				document: 'readonly',
				console: 'readonly',
				fetch: 'readonly',
				FormData: 'readonly',
				HTMLElement: 'readonly',
				HTMLInputElement: 'readonly',
				HTMLTextAreaElement: 'readonly',
				Event: 'readonly',
				CustomEvent: 'readonly',
				setTimeout: 'readonly',
				clearTimeout: 'readonly',
			},
		},
		rules: {
			'no-unused-vars': ['warn', { argsIgnorePattern: '^_' }],
			'no-undef': 'error',
			'semi': ['error', 'always'],
			'quotes': ['warn', 'single', { avoidEscape: true }],
		},
	},
	{
		ignores: [
			'node_modules/**',
			'styles/all/template/js/tiptap-simple.js',
		],
	},
];
