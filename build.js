const fs = require( 'fs' );
const path = require( 'path' );
const { execFile } = require( 'child_process' );
const { parse } = require( '@vue/compiler-sfc' );
const { transform } = require( '@babel/core' );

const inputDir = 'resources/ext.neowiki.addButton/ts';
const outputDir = 'resources/ext.neowiki.addButton/dist';

function runTypeCheck() {
	return new Promise((resolve, reject) => {
		const vueTscPath = path.resolve(__dirname, 'node_modules', '.bin', 'vue-tsc');
		const args = ['--noEmit', '--project', path.resolve(__dirname, 'tsconfig.json')];

		execFile(vueTscPath, args, (error, stdout, stderr) => {
			if (error) {
				console.error(`TypeScript compilation failed:\n${stdout}\n${stderr}`);
				reject(error);
			} else {
				console.log('TypeScript compilation successful');
				resolve();
			}
		});
	});
}


function processFile( inputFile, outputFile ) {
	const ext = path.extname( inputFile );

	// Ensure the output directory exists
	fs.mkdirSync( path.dirname( outputFile ), { recursive: true } );

	if( ext === '.vue' ) {
		const vueSource = fs.readFileSync( inputFile, 'utf-8' );
		const { descriptor } = parse( vueSource );

		// Extract the script content
		const scriptContent = descriptor.script.content;

		// Transform JavaScript/TypeScript to ES5 and replace .ts imports with .js
		const { code: transformedScript } = transform( scriptContent, {
			filename: inputFile,
			presets: [
				[ '@babel/preset-env', { targets: { ie: '11' } } ],
				[ '@babel/preset-typescript', { isTSX: false, allExtensions: true } ]
			],
			plugins: [
				'@babel/plugin-transform-modules-commonjs',
				// Add a custom plugin to replace .ts with .js in import statements
				function() {
					return {
						visitor: {
							ImportDeclaration( path ) {
								const source = path.node.source;
								if( source.value.endsWith( '.ts' ) ) {
									source.value = source.value.replace( /\.ts$/, '.js' );
								}
							}
						}
					};
				}
			]
		} );

		// Create the new Vue component with transformed script
		const compiledComponent = `
<template>
${ descriptor.template.content.trim() }
</template>

<script>
${ transformedScript.trim() }
</script>

<style>
${ descriptor.styles[0]?.content.trim() || '' }
</style>
`.trim();

		fs.writeFileSync( outputFile, compiledComponent );
	} else if( ext === '.ts' ) {
		const tsSource = fs.readFileSync( inputFile, 'utf-8' );

		const { code: compiledJs } = transform( tsSource, {
			filename: inputFile,
			presets: [
				[ '@babel/preset-env', { targets: { ie: '11' } } ],
				[ '@babel/preset-typescript', { isTSX: false, allExtensions: true } ]
			],
			plugins: [
				'@babel/plugin-transform-modules-commonjs',
				// Add a custom plugin to replace .ts with .js in import statements
				function() {
					return {
						visitor: {
							ImportDeclaration( path ) {
								const source = path.node.source;
								if( source.value.endsWith( '.ts' ) ) {
									source.value = source.value.replace( /\.ts$/, '.js' );
								}
							}
						}
					};
				}
			]
		} );

		fs.writeFileSync( outputFile, compiledJs );
	} else {
		// For other file types, just copy them
		fs.copyFileSync( inputFile, outputFile );
	}

	console.log( `Processed ${ inputFile } to ${ outputFile }` );
}

// Function to recursively process all files in a directory
function processDirectory( inputDir, outputDir ) {
	const entries = fs.readdirSync( inputDir, { withFileTypes: true } );

	for( const entry of entries ) {
		const inputPath = path.join( inputDir, entry.name );
		const outputPath = path.join( outputDir, entry.name );

		if( entry.isDirectory() ) {
			processDirectory( inputPath, outputPath );
		} else {
			const ext = path.extname( entry.name );
			if( ext === '.ts' ) {
				processFile( inputPath, outputPath.replace( /\.ts$/, '.js' ) );
			} else {
				processFile( inputPath, outputPath );
			}
		}
	}
}

async function main() {
	try {
		await runTypeCheck();
		processDirectory( inputDir, outputDir );
	} catch( error ) {
		console.error( 'Build failed due to TypeScript errors' );
		process.exit( 1 );
	}
}

main();
