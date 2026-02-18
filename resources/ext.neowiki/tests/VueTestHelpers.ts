import { mount, VueWrapper } from '@vue/test-utils';
import { Component, DefineComponent } from 'vue';
import { vi } from 'vitest';
import { NeoWikiTestServices } from './NeoWikiTestServices.ts';

export function createI18nMock(): ReturnType<typeof vi.fn> {
	return vi.fn().mockImplementation( ( key ) => ( {
		text: () => key,
	} ) );
}

export function createTestWrapper<TComponent extends DefineComponent<any, any, any>>(
	component: Component,
	props: InstanceType<TComponent>['$props'],
): VueWrapper<InstanceType<TComponent>> {
	return mount(
		component,
		{
			props: props,
			global: {
				provide: NeoWikiTestServices.getServices(),
				directives: {
					tooltip: {},
				},
				mocks: {
					$i18n: createI18nMock(),
				},
			},
		},
	) as VueWrapper<InstanceType<TComponent>>;
}

export interface MwMockOptions {
	messages?: Record<string, string | ( ( ...params: string[] ) => string )>;
	config?: Record<string, any>;
	functions?: (
		'config' | 'message' | 'msg' | 'notify' | 'util'
	)[];
}

export function setupMwMock(
	options: MwMockOptions = {},
): void {
	const {
		messages: customMessages = {},
		config: customConfig = {},
		functions = [
			'config',
			'message',
			'msg',
			'notify',
		],
	} = options;

	const mwMock: any = {};

	const resolveMessage = ( key: string, params: string[] ): string => {
		const message = customMessages[ key ];
		if ( typeof message === 'function' ) {
			return message( ...params );
		}
		if ( message !== undefined ) {
			return message;
		}
		return key + params.join( '' );
	};

	const implementations: Record<string, any> = {
		config: () => ( {
			get: vi.fn( ( key: string ) => customConfig[ key ] ),
		} ),
		message: () => vi.fn( ( key: string, ...params: string[] ) => ( {
			text: () => resolveMessage( key, params ),
			parse: () => resolveMessage( key, params ),
		} ) ),
		msg: () => vi.fn( ( key: string, ...params: string[] ) => resolveMessage( key, params ) ),
		notify: () => vi.fn(),
		util: () => ( {
			wikiScript: vi.fn( () => '/rest.php' ),
			getUrl: vi.fn( ( title: string ) => `/wiki/${ title }` ),
		} ),
	};

	functions.forEach( ( funcName ) => {
		if ( implementations[ funcName ] ) {
			mwMock[ funcName ] = implementations[ funcName ]();
		}
	} );

	vi.stubGlobal( 'mw', mwMock );
}
