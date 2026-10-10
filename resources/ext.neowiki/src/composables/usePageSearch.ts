import { ref, computed } from 'vue';
import type { Ref, ComputedRef } from 'vue';
import type { MenuItemData } from '@wikimedia/codex';
import { cdxIconAdd } from '@wikimedia/codex-icons';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import type { PageChoice } from '@/components/common/PageChoice.ts';

// Menu values are page ids as text, so no sentinel can collide with a result: a page id is
// always digits.
export const CREATE_PAGE = '__create__';
export const NO_RESULTS = '__no_results__';
export const PINNED_PAGE = '__pinned__';

const RESULT_LIMIT = 10;

/** Idle until the user types, pending while the request is out, done once it has come back. */
export type PageSearchStatus = 'idle' | 'pending' | 'done';

export interface PinnedPage {
	label: string;
	/** What picking it will do, where the label alone does not say. */
	description?: string;
	/** Shown but unpickable, the description saying why. */
	disabled?: boolean;
	page: PageChoice;
}

export interface PageSearchOptions {
	/** What the user has typed, empty while they have typed nothing. */
	typedText: Ref<string>;
	/** Leave out the option to use the typed text as a page to create. */
	existingPagesOnly?: Ref<boolean>;
	/**
	 * Offer nothing but that option: no pages are searched for and none are listed, so the typed
	 * text can only name a page to create. For a host whose destination is fixed at a new page and
	 * open only as to what it is called.
	 */
	newPageOnly?: Ref<boolean>;
	/**
	 * Offered above the results while nothing has been typed, worded by whoever supplied them.
	 * Left out by a picker that has no shortcuts to offer.
	 */
	pinnedPages?: Ref<PinnedPage[]>;
	/** Left out of the results, such as the page a Subject is already on. */
	excludedPageId: Ref<number | undefined>;
}

export interface PageSearch {
	/** The shortcuts and the results. Not the create option, which belongs below them. */
	menuItems: ComputedRef<MenuItemData[]>;
	/**
	 * Offering the typed text as a page to create, or null where no page may be created. Kept apart
	 * from the list so a presentation that can pin it does not make it the last thing scrolled to.
	 */
	createItem: ComputedRef<MenuItemData | null>;
	status: Ref<PageSearchStatus>;
	/** Runs a search for the given text, discarding an answer a newer search has overtaken. */
	search( text: string ): Promise<void>;
	/** Back to having searched for nothing, which is not the same as having found nothing. */
	clear(): void;
	/** The page a menu value stands for, or null where it stands for no page at all. */
	pageFor( value: string ): PageChoice | null;
	/** Whether a value names the option below the results, or their absence, rather than a result. */
	isSentinel( value: string ): boolean;
}

/**
 * The page search two pickers share: one presenting it as a Codex lookup, the other as a field
 * over a menu. What differs between them is how a choice is reported back to the field, which is
 * each picker's own business; what is here is the searching, the menu that comes of it, and what
 * each entry in that menu means.
 */
export function usePageSearch( options: PageSearchOptions ): PageSearch {
	const pageTitleSearch = NeoWikiServices.getPageTitleSearch();

	const results = ref<MenuItemData[]>( [] );
	const status = ref<PageSearchStatus>( 'idle' );
	let requestSequence = 0;

	// Shortcuts past searching, so searching retires them.
	const pinnedItems = computed( (): MenuItemData[] => {
		if ( options.typedText.value !== '' ) {
			return [];
		}

		return ( options.pinnedPages?.value ?? [] ).map( ( pinned, index ) => ( {
			value: `${ PINNED_PAGE }${ index }`,
			label: pinned.label,
			description: pinned.description,
			disabled: pinned.disabled,
		} ) );
	} );

	const createItem = computed( (): MenuItemData | null => {
		if ( options.existingPagesOnly?.value === true ) {
			return null;
		}

		return {
			value: CREATE_PAGE,
			label: options.typedText.value === '' ?
				mw.msg( 'neowiki-page-picker-create-hint' ) :
				mw.msg( 'neowiki-page-picker-create-named', options.typedText.value ),
			icon: cdxIconAdd,
			disabled: options.typedText.value === '',
		};
	} );

	const menuItems = computed( (): MenuItemData[] => {
		// Nothing to list: the only answer is the page to create, which rides below the list.
		if ( options.newPageOnly?.value === true ) {
			return [];
		}

		// A picker with no create option below the list has a slot of its own for saying that a
		// search found nothing, so the list is left to the results and says nothing itself.
		if ( options.existingPagesOnly?.value === true ) {
			return [ ...pinnedItems.value, ...results.value ];
		}

		const items = [ ...pinnedItems.value, ...results.value ];

		// Codex's own no-results slot is shown only for an empty menu, which the create option rules
		// out wherever it sits, so that case carries the same message as an item nobody can pick.
		if ( status.value === 'done' && items.length === 0 ) {
			items.push( {
				value: NO_RESULTS,
				label: mw.msg( 'neowiki-page-picker-no-results' ),
				disabled: true,
			} );
		}

		return items;
	} );

	function clear(): void {
		// Retires whatever is in flight as well: an answer to a search nobody is waiting for any
		// more would otherwise pass its own staleness check and fill the list back in.
		requestSequence++;
		results.value = [];
		status.value = 'idle';
	}

	async function search( text: string ): Promise<void> {
		if ( options.newPageOnly?.value === true ) {
			return;
		}

		status.value = 'pending';
		const currentSequence = ++requestSequence;

		try {
			const found = await pageTitleSearch.searchPageTitles( text, RESULT_LIMIT );

			if ( currentSequence !== requestSequence ) {
				return;
			}

			results.value = found
				.filter( ( result ) => result.pageId !== options.excludedPageId.value )
				.map( ( result ) => ( {
					label: result.title,
					value: String( result.pageId ),
				} ) );
		} catch {
			if ( currentSequence !== requestSequence ) {
				return;
			}

			results.value = [];
		} finally {
			if ( currentSequence === requestSequence ) {
				status.value = 'done';
			}
		}
	}

	function isSentinel( value: string ): boolean {
		return value === CREATE_PAGE || value === NO_RESULTS;
	}

	function pageFor( value: string ): PageChoice | null {
		if ( value.startsWith( PINNED_PAGE ) ) {
			const pinned = options.pinnedPages?.value ?? [];
			const chosen = pinned[ Number( value.slice( PINNED_PAGE.length ) ) ];

			// Codex refuses a disabled item; nothing here relies on that to keep one out of a choice.
			return chosen === undefined || chosen.disabled === true ? null : chosen.page;
		}

		if ( value === CREATE_PAGE ) {
			const title = options.typedText.value;

			return title === '' ? null : { pageId: null, title };
		}

		if ( value === NO_RESULTS ) {
			return null;
		}

		const picked = results.value.find( ( item ) => item.value === value );

		return { pageId: Number( value ), title: String( picked?.label ?? '' ) };
	}

	return { menuItems, createItem, status, search, clear, pageFor, isSentinel };
}
