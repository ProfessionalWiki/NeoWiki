import { mount, VueWrapper, DOMWrapper } from '@vue/test-utils';
import { Component, DefineComponent, nextTick } from 'vue';
import { expect, onTestFinished, vi } from 'vitest';
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
 * Clicks the element as a reader does, with the left button and whatever else init adds, and tells
 * whether the browser was left to follow the link.
 */
export function clickIsFollowed( element: Element, init: MouseEventInit = {} ): boolean {
	const click = new MouseEvent( 'click', { bubbles: true, cancelable: true, button: 0, ...init } );
	element.dispatchEvent( click );

	return !click.defaultPrevented;
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
 * The language a LanguageInput shows, which is also what a screen reader announces: the list's name
 * for it. The labels that only size the list are hidden from both, and the list's menu and the
 * search panel are closed.
 */
export function shownLanguage( languageInput: Element ): string {
	const shown = languageInput.cloneNode( true ) as Element;

	shown.querySelectorAll(
		'[aria-hidden="true"], .ext-neowiki-language-input__width, .cdx-menu, .ext-neowiki-language-picker__panel',
	).forEach( ( hidden ) => hidden.remove() );

	return shown.textContent?.trim() ?? '';
}

export async function chooseFromLanguageList( languageInput: VueWrapper | DOMWrapper<Element>, language: string ): Promise<void> {
	await languageInput.find( '.ext-neowiki-language-input [role="combobox"]' ).trigger( 'click' );
	await languageInput.findAll( '.ext-neowiki-language-input__select .cdx-menu-item' )
		.find( ( item ) => item.text() === language )
		?.trigger( 'click' );
}

/**
 * The name assistive technology announces for an element: its `aria-label`, else the text of the elements its
 * `aria-labelledby` names, else the text of its `<label>`, or of its `<caption>` for a table, else, for a button or a
 * link, its own text. Text hidden from assistive technology counts for nothing, and an element with an `aria-label`
 * of its own counts by that.
 */
export function accessibleName( element: Element ): string {
	const label = element.getAttribute( 'aria-label' );

	if ( label !== null ) {
		return label.trim();
	}

	const labelledBy = element.getAttribute( 'aria-labelledby' );

	if ( labelledBy !== null ) {
		const root = element.getRootNode() as ParentNode;

		return collapsed( labelledBy.split( ' ' ).filter( ( id ) => id !== '' )
			.map( ( id ) => root.querySelector( `[id="${ id }"]` ) )
			.map( ( labelling ) => labelling === null ? '' : textAlternative( labelling ) )
			.join( ' ' ) );
	}

	const labels = ( element as HTMLInputElement ).labels;

	if ( labels !== undefined && labels !== null && labels.length > 0 ) {
		return collapsed( [ ...labels ].map( textAlternative ).join( ' ' ) );
	}

	if ( element instanceof HTMLTableElement ) {
		return element.caption === null ? '' : collapsed( textAlternative( element.caption ) );
	}

	return element.matches( 'button, a' ) ? collapsed( textAlternative( element ) ) : '';
}

function textAlternative( node: Node ): string {
	if ( !( node instanceof HTMLElement || node instanceof SVGElement ) ) {
		return node.nodeType === Node.TEXT_NODE ? node.textContent ?? '' : '';
	}

	if ( node.getAttribute( 'aria-hidden' ) === 'true' || node.style.display === 'none' ) {
		return '';
	}

	return node.getAttribute( 'aria-label' ) ?? [ ...node.childNodes ].map( textAlternative ).join( ' ' );
}

function collapsed( text: string ): string {
	return text.replace( /\s+/g, ' ' ).trim();
}

/** The texts of the elements an element's `aria-describedby` names, which a screen reader reads after its name. */
export function describedAs( wrapper: VueWrapper, element: DOMWrapper<Element> ): string[] {
	return ( element.attributes( 'aria-describedby' ) ?? '' ).split( ' ' ).filter( ( id ) => id !== '' )
		.map( ( id ) => wrapper.find( `[id="${ id }"]` ).text() );
}

/** The buttons and menu items in an element, in order, each by its accessible name or, lacking one, its text. */
export function offeredActions( element: VueWrapper | DOMWrapper<Element> ): string[] {
	return element.findAll( 'button, .cdx-menu-item' )
		.map( ( control ) => control.attributes( 'aria-label' ) ?? control.text() );
}

export async function chooseFromMenu( element: VueWrapper | DOMWrapper<Element>, buttonLabel: string, itemText: string ): Promise<void> {
	await element.find( `button[aria-label="${ buttonLabel }"]` ).trigger( 'click' );
	const item = element.findAll( '.cdx-menu-item' ).find( ( menuItem ) => menuItem.text() === itemText );

	expect( item, `menu item ${ itemText }` ).toBeDefined();
	await item!.trigger( 'click' );
}

// Codex gives its "no results" element the menu-item class too, so it is excluded here.
export async function pickInLanguageSearch( languagePicker: VueWrapper | DOMWrapper<Element>, text: string ): Promise<void> {
	await languagePicker.find( '.ext-neowiki-language-picker__search input' ).setValue( text );
	await languagePicker.find( '.ext-neowiki-language-picker__menu .cdx-menu-item:not( .cdx-menu__no-results )' )
		.trigger( 'click' );
}

export function emittedTags( languageControl: VueWrapper ): string[] {
	return ( languageControl.emitted( 'update:modelValue' ) ?? [] ).map( ( event ) => event[ 0 ] as string );
}

// The languages a LanguageListInput lists, by the names it shows them under.
export function listedLanguages( languageList: VueWrapper ): string[] {
	return languageList.findAll( '.ext-neowiki-language-list-input__name' ).map( ( name ) => name.text() );
}

// Dispatched rather than trigger()'d, because what the test asserts on is the event itself.
export function press( element: Element ): MouseEvent {
	const event = new MouseEvent( 'mousedown', { bubbles: true, cancelable: true } );

	element.dispatchEvent( event );

	return event;
}

// Keys go where the focus is, which is what the handlers move: each press follows it.
export async function pressKey( key: string ): Promise<void> {
	for ( const type of [ 'keydown', 'keyup' ] ) {
		document.activeElement?.dispatchEvent( new KeyboardEvent( type, { key: key, bubbles: true, cancelable: true } ) );
		await nextTick();
	}
}

// Where a Codex dialog listens: it closes on the Escape keyup that reaches it.
export function listenForDialogKeys(): ReturnType<typeof vi.fn> {
	const dialogKeyup = vi.fn();

	document.addEventListener( 'keyup', dialogKeyup );
	onTestFinished( () => document.removeEventListener( 'keyup', dialogKeyup ) );

	return dialogKeyup;
}

export function findPropertyNameInput( wrapper: VueWrapper ): DOMWrapper<HTMLInputElement> {
	return wrapper.find<HTMLInputElement>( '.ext-neowiki-property-editor__name input' );
}

export function selectedText( input: HTMLInputElement ): string {
	return input.value.slice( input.selectionStart ?? 0, input.selectionEnd ?? 0 );
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

/**
 * The open dialogs, named by title, from the bottom of the stack to the top. Codex teleports every
 * dialog to the document and gives each backdrop the same z-index, so the order they stand in the
 * document is the order they stack in, and the last one named is the one the user is looking at.
 */
export function openDialogTitles(): string[] {
	return Array.from(
		document.querySelectorAll( '.cdx-dialog__header__title' ),
		( title ) => title.textContent?.trim() ?? '',
	);
}

/**
 * CdxDialog as a stub that still renders its slots. shallowMount's auto-stubs render none, and a
 * dialog's controls live in its #footer slot, so a test driving one needs them in the tree.
 */
export const CdxDialogStub = {
	template: '<div v-if="open" class="cdx-dialog-stub"><slot /><slot name="footer" /></div>',
	props: [ 'open', 'title', 'useCloseButton', 'defaultAction' ],
	emits: [ 'update:open' ],
};

export interface ScrollStub {
	/** Tells whoever observes the element that it is now in view, or out of it. */
	setInView( element: Element, inView: boolean ): void;
}

/**
 * Stands in for the browser's IntersectionObserver, which jsdom lacks, so a spec decides when an element
 * scrolls into view. Codex's useIntersectionObserver looks for the entry type as well before it observes.
 */
export function stubIntersectionObserver(): ScrollStub {
	const observers: { callback: IntersectionObserverCallback; elements: Set<Element> }[] = [];

	vi.stubGlobal( 'IntersectionObserver', class {

		private readonly observer: { callback: IntersectionObserverCallback; elements: Set<Element> };

		public constructor( callback: IntersectionObserverCallback ) {
			this.observer = { callback, elements: new Set() };
			observers.push( this.observer );
		}

		public observe( element: Element ): void {
			this.observer.elements.add( element );
		}

		public unobserve( element: Element ): void {
			this.observer.elements.delete( element );
		}

		public disconnect(): void {
			this.observer.elements.clear();
		}

	} );

	vi.stubGlobal( 'IntersectionObserverEntry', class {

		public get intersectionRatio(): number {
			return 0;
		}

	} );

	return {
		setInView( element: Element, inView: boolean ): void {
			for ( const { callback, elements } of observers ) {
				if ( elements.has( element ) ) {
					const entry = { target: element, isIntersecting: inView } as IntersectionObserverEntry;
					callback( [ entry ], {} as IntersectionObserver );
				}
			}
		},
	};
}

/**
 * Stands in for MediaWiki's `$.fn.updateTooltipAccessKeys`, naming the key in the title as core does
 * for Chromium: "Save [alt-shift-s]". Like core's, it adds no title and replaces a name it added before.
 */
export function stubUpdateTooltipAccessKeys(): void {
	vi.stubGlobal( '$', ( element: HTMLElement ) => ( {
		updateTooltipAccessKeys(): void {
			if ( element.title === '' ) {
				return;
			}

			const title = element.title.replace( / \[[^\]]*\]$/, '' );
			element.title = element.accessKey === '' ? title : `${ title } [alt-shift-${ element.accessKey }]`;
		},
	} ) );
}

/**
 * What MediaWiki's own bcp47() translates, from its own table: a MediaWiki code that is not a BCP 47
 * tag, and in `be-x-old`'s case one that lands on a tag another code already carries. A few are
 * enough for a spec to tell a translated code from one passed straight through, which a pass-through
 * fake hides; every other code passes through.
 */
const BCP_47_TAGS: Record<string, string> = {
	als: 'gsw',
	'be-x-old': 'be-tarask',
	simple: 'en-simple',
};

export interface MwMockOptions {
	messages?: Record<string, string | ( ( ...params: string[] ) => string )>;
	config?: Record<string, any>;
	/** The languages mw.language.getData reports names for, keyed by language code. */
	languageNames?: Record<string, string>;
	functions?: (
		'config' | 'message' | 'msg' | 'notify' | 'storage' | 'util' | 'language'
	)[];
}

export function setupMwMock(
	options: MwMockOptions = {},
): void {
	const {
		messages: customMessages = {},
		config: customConfig = {},
		languageNames: customLanguageNames = {},
		functions = [
			'config',
			'message',
			'msg',
			'notify',
			'language',
		],
	} = options;

	const mwMock: any = {};

	const resolveMessage = ( key: string, params: any[] ): string => {
		// Rendered by real MediaWiki everywhere a Subject nobody named is shown, so the fake carries
		// it rather than each spec restating the marker's shape.
		if ( key === 'neowiki-subject-generated-name' && customMessages[ key ] === undefined ) {
			return `(${ params[ 0 ] })`;
		}

		// And so is the name of a Dependent Subject nobody named, also as told apart by its first value.
		if ( key === 'neowiki-dependent-subject-name' && customMessages[ key ] === undefined ) {
			return `${ params[ 0 ] } of ${ params[ 1 ] }`;
		}

		if ( key === 'neowiki-dependent-subject-identified-name' && customMessages[ key ] === undefined ) {
			return `${ params[ 0 ] } (${ params[ 1 ] }: ${ params[ 2 ] })`;
		}

		// Core's own wrapper, whose brackets a locale can change. Carried here for the same reason.
		if ( key === 'parentheses' && customMessages[ key ] === undefined ) {
			return `(${ params[ 0 ] })`;
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
		message: () => vi.fn( ( key: string, ...params: any[] ) => ( {
			text: () => resolveMessage( key, params ),
			parse: () => resolveMessage( key, params ),
			// jqueryMsg's parseDom yields nodes rather than a string, which is what lets a message
			// carry a link. The fake keeps that: a node parameter stays a node, everything else
			// reads as the key-plus-parameters text the other formats produce.
			parseDom: () => {
				const container = document.createElement( 'span' );
				container.append(
					key,
					...params.map( ( param ) => param instanceof Node ? param : String( param ) ),
				);
				return container;
			},
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
		language: () => ( {
			bcp47: vi.fn( ( code: string ) => BCP_47_TAGS[ code.toLowerCase() ] ?? code ),
			getData: vi.fn(
				( _langCode: string, dataKey: string ) => dataKey === 'languageNames' ? customLanguageNames : undefined,
			),
			// MediaWiki's own chain starts at the interface language and ends at English, which every
			// wiki falls back to; the fake carries just that, so a spec setting a user language gets a
			// chain of its own without configuring one.
			getFallbackLanguageChain: vi.fn( () => [ customConfig.wgUserLanguage, 'en' ] ),
			// MediaWiki formats a number the way the interface language writes it; the fake groups its digits the
			// English way, so a spec can tell a formatted number from a bare one.
			convertNumber: vi.fn( ( number: number ) => number.toLocaleString( 'en-US' ) ),
		} ),
		util: () => ( {
			wikiScript: vi.fn( () => '/rest.php' ),
			// Query parameters are part of the URL a caller asked for - a Data tab is the page's own
			// URL plus action=subjects - so the fake carries them rather than dropping them.
			getUrl: vi.fn( ( title: string, params?: Record<string, string> ) =>
				`/wiki/${ title }` + ( params === undefined ? '' : `?${ new URLSearchParams( params ) }` ) ),
		} ),
	};

	functions.forEach( ( funcName ) => {
		if ( implementations[ funcName ] ) {
			mwMock[ funcName ] = implementations[ funcName ]();
		}
	} );

	vi.stubGlobal( 'mw', mwMock );
}
