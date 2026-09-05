import { mount, VueWrapper, DOMWrapper } from '@vue/test-utils';
import { Component, DefineComponent } from 'vue';
import { expect, vi } from 'vitest';
import { ValidationMessages, ValidationStatusType } from '@wikimedia/codex';
import { NeoWikiTestServices } from './NeoWikiTestServices.ts';

export interface FieldProps {
	status: ValidationStatusType;
	messages: ValidationMessages;
}

/**
 * Stands in for MediaWiki's `$i18n`, rendering a message as its key with the parameters
 * concatenated — the same shape setupMwMock's `mw.message` produces, so a test can assert
 * on a rendered parameter instead of the component having to expose it as a data attribute.
 */
export function createI18nMock(): ReturnType<typeof vi.fn> {
	return vi.fn().mockImplementation( ( key, ...params: unknown[] ) => ( {
		text: () => key + params.join( '' ),
	} ) );
}

/**
 * Locates the CdxTable pager's "Next page" button, asserting it exists so a selector that matches
 * nothing fails loudly here. A missed find() returns an empty DOMWrapper whose
 * attributes( 'disabled' ) is undefined, which would silently satisfy a toBeUndefined()
 * enabled-state assertion against a button that is not in the DOM.
 */
export function findNextPageButton( wrapper: VueWrapper ): DOMWrapper<Element> {
	const button = wrapper.find( '.cdx-table-pager button[aria-label="Next page"]' );
	expect( button.exists() ).toBe( true );
	return button;
}

/**
 * Puts a native number input into the state a browser reports for text it cannot
 * parse, such as "5foo": the characters stay visible in the widget, the value
 * reads as empty, and validity.badInput is set. jsdom never sets that flag from
 * typing, so the browser's report is stubbed here.
 */
export async function reportUnparseableNumber( input: DOMWrapper<Element> ): Promise<void> {
	Object.defineProperty( input.element, 'validity', {
		configurable: true,
		get: () => ( { badInput: true } ),
	} );

	await input.setValue( '' );
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
		'config' | 'message' | 'msg' | 'notify' | 'storage' | 'util'
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
		// Rendered by real MediaWiki everywhere a Subject nobody named is shown, so the fake carries
		// it rather than each spec restating the marker's shape.
		if ( key === 'neowiki-subject-generated-name' && customMessages[ key ] === undefined ) {
			return `(unnamed ${ params[ 0 ] })`;
		}

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
		storage: () => ( {
			session: {
				get: vi.fn(),
				set: vi.fn(),
				remove: vi.fn(),
			},
		} ),
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
