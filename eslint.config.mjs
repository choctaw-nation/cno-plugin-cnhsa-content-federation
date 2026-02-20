import globals from 'globals';
import { fixupConfigRules, includeIgnoreFile } from '@eslint/compat';
import wordpressConfig from '@wordpress/eslint-plugin';
import { globalIgnores, defineConfig } from 'eslint/config';
import { FlatCompat } from '@eslint/eslintrc';
import path from 'path';
import { fileURLToPath, URL } from 'url';

const gitignorePath = fileURLToPath( new URL( '.gitignore', import.meta.url ) );

const __filename = fileURLToPath( import.meta.url );
const __dirname = path.dirname( __filename );

const compat = new FlatCompat( {
	baseDirectory: __dirname,
} );

export default defineConfig( [
	includeIgnoreFile( gitignorePath, 'Ignore .gitignore files' ),
	...fixupConfigRules(
		compat.config(
			wordpressConfig.configs[ 'recommended-with-formatting' ]
		)
	),
	{
		files: [ 'src/**/*.{js,ts,jsx,tsx}' ],
		languageOptions: {
			globals: globals.browser,
		},

		rules: {
			'jsdoc/require-jsdoc': 'off',
			'jsdoc/require-param': 'off',
			'jsdoc/require-param-type': 'off',
			'jsdoc/require-returns-type': 'off',
			'jsdoc/require-returns-check': 'off',
			'jsdoc/require-returns-description': 'off',
			'jsdoc/check-param-names': 'off',
			'no-console': 'warn',
			'no-duplicate-imports': 'off',
			'import/no-duplicates': 'error',
			'no-unused-vars': 'off',
			'no-undef': 'off',
			'no-shadow': 'off',
		},
		settings: {
			'import/resolver': {
				node: {
					extensions: [ '.js', '.jsx', '.ts', '.tsx', '.scss' ],
				},
				typescript: {
					alwaysTryTypes: true,
					project: './tsconfig.json',
				},
			},
		},
	},
] );
