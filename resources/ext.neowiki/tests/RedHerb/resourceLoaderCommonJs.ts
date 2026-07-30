import type { Plugin } from 'vite';

export interface ResourceLoaderCommonJsOptions {
	/** Directory holding the ResourceLoader package files to transform. */
	sourceDir: string;
	/**
	 * What ResourceLoader hands those package files, keyed by the module name they require.
	 * Module names absent from this map are left alone and resolve as siblings in sourceDir.
	 */
	providedModules: Record<string, string>;
	/**
	 * File to resolve the provided modules as if it had imported them. Resolving from
	 * sourceDir instead would miss ext.neowiki's node_modules, since RedHerb's package files
	 * live outside it and have no dependencies of their own. It also keeps the package files
	 * on the vue, Codex and ext.neowiki instances the rest of the suite uses.
	 */
	resolveFrom: string;
}

/**
 * Lets Vite load ResourceLoader package files, so the RedHerb example extension can be
 * tested with the same toolchain as ext.neowiki itself.
 *
 * ResourceLoader wraps each package file in a CommonJS closure, so RedHerb's modules pull
 * their dependencies in with `require()` and expose themselves through `module.exports`.
 * Vite only understands ES modules, so this rewrites those two constructs.
 *
 * A namespace whose only export is `default` is a rewritten package file, and gets unwrapped
 * to whatever it assigned to `module.exports`. The modules ResourceLoader provides have no
 * default export, so they stay namespaces.
 */
export function resourceLoaderCommonJs( options: ResourceLoaderCommonJsOptions ): Plugin {
	return {
		name: 'resource-loader-commonjs',
		enforce: 'pre',
		async transform( code: string, id: string ): Promise<{ code: string; map: null } | undefined > {
			const path = id.split( '?' )[ 0 ];
			if ( !path.startsWith( options.sourceDir ) ) {
				return undefined;
			}

			const resolveModule = async ( name: string ): Promise<string> => {
				const target = options.providedModules[ name ];
				if ( target === undefined ) {
					return name;
				}
				const resolved = await this.resolve( target, options.resolveFrom );
				if ( resolved === null ) {
					throw new Error( `Cannot resolve ${ target }, provided to ResourceLoader package files as ${ name }` );
				}
				return resolved.id;
			};

			if ( path.endsWith( '.vue' ) ) {
				return { code: await transformScriptBlock( code, resolveModule ), map: null };
			}

			if ( path.endsWith( '.js' ) ) {
				return { code: await toEsModule( code, resolveModule ), map: null };
			}

			return undefined;
		},
	};
}

type ModuleResolver = ( name: string ) => Promise<string>;

const SCRIPT_BLOCK = /(<script[^>]*>)([\s\S]*?)(<\/script>)/;

async function transformScriptBlock( sfc: string, resolveModule: ModuleResolver ): Promise<string> {
	const match = SCRIPT_BLOCK.exec( sfc );
	if ( match === null ) {
		return sfc;
	}

	const [ block, open, script, close ] = match;

	return sfc.replace( block, open + await toEsModule( script, resolveModule ) + close );
}

const REQUIRE_CALL = /require\(\s*(['"])(.+?)\1\s*\)/g;

async function toEsModule( code: string, resolveModule: ModuleResolver ): Promise<string> {
	const moduleNames: string[] = [];

	const body = code.replace( REQUIRE_CALL, ( _match, _quote: string, name: string ) => {
		if ( !moduleNames.includes( name ) ) {
			moduleNames.push( name );
		}
		return interopName( moduleNames.indexOf( name ) );
	} );

	const imports = await Promise.all( moduleNames.map( async ( name, index ) =>
		`import * as ${ namespaceName( index ) } from '${ await resolveModule( name ) }';\n` +
		`const ${ interopName( index ) } = ${ namespaceName( index ) }.default ?? ${ namespaceName( index ) };`,
	) );

	return [
		...imports,
		'const module = { exports: {} };',
		'let exports = module.exports;',
		body,
		'export default module.exports;',
	].join( '\n' );
}

function namespaceName( index: number ): string {
	return `__rlNamespace${ index }`;
}

function interopName( index: number ): string {
	return `__rlModule${ index }`;
}
