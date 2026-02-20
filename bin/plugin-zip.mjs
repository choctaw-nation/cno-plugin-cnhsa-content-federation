import { execFileSync } from 'node:child_process';
import { rmSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname( fileURLToPath( import.meta.url ) );

const pluginSlug = 'cno-plugin-cnhsa-content-federation'; // folder name inside the zip
const pluginDir = path.resolve( __dirname, '..' ); // repo root (adjust if needed)
const outZip = path.join( pluginDir, `${ pluginSlug }.zip` );

// What to exclude from the zip
const excludes = [
	'.DS_Store',
	'.git/*',
	'.github/*',
	'.vscode/*',
	'.wp-tests/*',
	'bin/*',
	'tests/*',
	'vendor/*',
	'node_modules/*',
	'.editorconfig',
	'.gitignore',
	'composer.lock',
	'*.dist',
	'*.json',
];

// Run zip from parent of plugin dir so paths are correct
// -r recursive, -q quiet, -X omit extra file attributes for reproducibility-ish
const args = [
	'-r',
	'-q',
	'-X',
	outZip,
	pluginSlug, // zip the plugin folder
	...excludes.flatMap( ( p ) => [ '-x', `${ pluginSlug }/${ p }` ] ),
];

execFileSync( 'zip', args, { cwd: path.dirname( pluginDir ) } );

console.log( `Created: ${ outZip }` );
