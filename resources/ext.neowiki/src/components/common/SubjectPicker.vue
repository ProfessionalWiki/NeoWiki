<template>
	<div
		class="ext-neowiki-subject-picker"
		:class="{ 'ext-neowiki-subject-picker--offers-create': creationOffered }"
	>
		<div class="ext-neowiki-subject-picker__row">
			<CdxLookup
				ref="lookupRef"
				v-model:selected="selectedSubject"
				v-model:input-value="inputText"
				:menu-items="menuItems"
				:start-icon="props.startIcon"
				:placeholder="$i18n( 'neowiki-subject-picker-placeholder' ).text()"
				:status="effectiveStatus"
				:aria-label="props.ariaLabel"
				@input="onLookupInput"
				@update:selected="onSubjectSelected"
				@blur="onBlur"
			>
				<!-- Codex shows this only for an empty menu. With the create option present the
					menu never is, so that case carries the same message as an unpickable item. -->
				<template v-if="searching && !creationOffered" #no-results>
					{{ $i18n( 'neowiki-subject-picker-no-results' ).text() }}
				</template>
			</CdxLookup>
			<slot
				name="suffix"
				:selected="selectedSubject"
			/>
		</div>
		<CdxMessage
			v-if="hasUnmatchedText"
			type="error"
			inline
		>
			{{ $i18n( 'neowiki-subject-picker-no-match' ).text() }}
		</CdxMessage>
	</div>
</template>

<script setup lang="ts">
import { ref, computed, inject, watch } from 'vue';
import { CdxLookup, CdxMessage } from '@wikimedia/codex';
import type { MenuItemData, ValidationStatusType } from '@wikimedia/codex';
import { cdxIconAdd } from '@wikimedia/codex-icons';
import type { Icon } from '@wikimedia/codex-icons';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { SubjectCreationKey } from '@/components/common/SubjectCreation.ts';
import { SubjectId } from '@/domain/SubjectId.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';

interface SubjectPickerProps {
	selected: string | null;
	targetSchema: string;
	startIcon?: Icon;
	status?: ValidationStatusType | 'default';
	ariaLabel?: string;
}

const props = withDefaults(
	defineProps<SubjectPickerProps>(),
	{
		startIcon: undefined,
		status: 'default',
		ariaLabel: undefined
	}
);

// Codex has no value of its own for either of these, so each is an ordinary menu item carrying a
// value no Subject id can take: an id is 's' followed by 14 base58 characters.
const CREATE_SUBJECT = '__create__';
const NO_RESULTS = '__no_results__';

const emit = defineEmits<{
	'update:selected': [ value: string | null ];
	'blur': [ hasUnmatchedText: boolean ];
}>();

const subjectStore = useSubjectStore();
const subjectLabelSearch = NeoWikiServices.getSubjectLabelSearch();

// Absent unless the host can carry a Subject the wiki does not hold yet, which is what leaves the
// picker offering only Subjects that already exist.
const subjectCreation = inject( SubjectCreationKey, undefined );

const creationOffered = computed( (): boolean => subjectCreation !== undefined );

const selectedSubject = ref<string | null>( props.selected );
const inputText = ref<string | number>( '' );
const searchResults = ref<MenuItemData[]>( [] );
const lookupRef = ref<InstanceType<typeof CdxLookup> | null>( null );
// Set once a search has come back holding nothing, so the menu says so only when it is true rather
// than while the request is still out.
const searchFoundNothing = ref( false );
const searching = ref( false );
const hasUnmatchedText = ref( false );
let requestSequence = 0;

// The name the field is currently showing for its selection, so text the user typed can be told
// apart from the label Codex and the watcher below write there themselves.
const selectedName = ref( '' );

// Subjects this session invented, which no search can return. Read through the host on every
// evaluation, so a draft renamed in the editor is renamed here too.
const drafts = computed( (): readonly { id: string; name: string }[] =>
	( subjectCreation?.drafts( props.targetSchema ) ?? [] )
		.map( ( subject ) => ( { id: subject.getId().text, name: subject.getDisplayName() } ) )
);

function draftNameOf( id: string ): string | undefined {
	return drafts.value.find( ( draft ) => draft.id === id )?.name;
}

// Only what the user actually typed: the field otherwise holds the selected Subject's own name,
// which would offer to create a second Subject named after the one already chosen.
const typedText = computed( (): string => {
	const text = String( inputText.value ?? '' ).trim();

	return text === selectedName.value ? '' : text;
} );

const createItem = computed( (): MenuItemData => ( {
	value: CREATE_SUBJECT,
	label: typedText.value === '' ?
		mw.msg( 'neowiki-subject-picker-create', props.targetSchema ) :
		mw.msg( 'neowiki-subject-picker-create-named', typedText.value, props.targetSchema ),
	icon: cdxIconAdd
} ) );

// Matched here rather than by the search: these Subjects exist only in the editor.
const draftItems = computed( (): MenuItemData[] => {
	const search = typedText.value.toLowerCase();

	return drafts.value
		.filter( ( draft ) => search === '' || draft.name.toLowerCase().includes( search ) )
		.map( ( draft ) => ( { value: draft.id, label: draft.name } ) );
} );

// The create option is present from the first render and never leaves, which is what opens the
// menu on focus before anything is typed: Codex expands an empty input's menu only when the
// Lookup was built with items, and collapses it again the moment the list runs empty.
const menuItems = computed( (): MenuItemData[] => {
	if ( !creationOffered.value ) {
		return searchResults.value;
	}

	const items = [ ...draftItems.value, ...searchResults.value ];

	// Codex's own no-results slot is shown only for an empty menu, which the create option rules
	// out, so that case carries the same message as an item nobody can pick.
	if ( searchFoundNothing.value && items.length === 0 ) {
		items.push( {
			value: NO_RESULTS,
			label: mw.msg( 'neowiki-subject-picker-no-results' ),
			disabled: true
		} );
	}

	items.push( createItem.value );

	return items;
} );

const effectiveStatus = computed( (): ValidationStatusType | 'default' =>
	hasUnmatchedText.value ? 'error' : props.status
);

// A Subject this session invented is named by the host: nothing can fetch one the server has
// never been told about.
async function resolveName( id: string | null ): Promise<string> {
	if ( !id ) {
		return '';
	}

	const draft = draftNameOf( id );
	if ( draft !== undefined ) {
		return draft;
	}

	try {
		const subject = await subjectStore.getOrFetchSubject( new SubjectId( id ) );
		return subject?.getDisplayName() ?? id;
	} catch {
		return id;
	}
}

function showName( name: string ): void {
	selectedName.value = name;
	inputText.value = name;
}

resolveName( props.selected ).then( showName );

watch( () => props.selected, async ( newSelected ) => {
	selectedSubject.value = newSelected;
	hasUnmatchedText.value = false;

	if ( newSelected !== null || !searching.value ) {
		showName( await resolveName( newSelected ) );
	}

	searching.value = false;
	searchFoundNothing.value = false;
} );

// A draft renamed in the editor renames the field pointing at it.
watch( () => props.selected === null ? undefined : draftNameOf( props.selected ), ( name ) => {
	if ( name !== undefined ) {
		showName( name );
	}
} );

async function onLookupInput( value: string ): Promise<void> {
	hasUnmatchedText.value = false;

	if ( !value ) {
		searchResults.value = [];
		searching.value = false;
		searchFoundNothing.value = false;
		return;
	}

	searching.value = true;
	const currentSequence = ++requestSequence;

	try {
		const results = await subjectLabelSearch.searchSubjectLabels( value, props.targetSchema );

		if ( currentSequence !== requestSequence ) {
			return;
		}

		searchResults.value = results.map( ( result ) => ( {
			label: result.label,
			value: result.id
		} ) );
	} catch {
		if ( currentSequence !== requestSequence ) {
			return;
		}

		searchResults.value = [];
	} finally {
		if ( currentSequence === requestSequence ) {
			searchFoundNothing.value = searchResults.value.length === 0;
		}
	}
}

function onSubjectSelected( subjectId: string | null ): void {
	if ( subjectId === CREATE_SUBJECT ) {
		// Put back what the field already held, before anything awaits: Codex has just reported the
		// sentinel as the selection, and a creation that fails must leave the old target standing.
		selectedSubject.value = props.selected;
		createFromTypedText();
		return;
	}

	// Codex refuses to select a disabled item, but the picker does not rely on that to keep its
	// own sentinel out of a relation.
	if ( subjectId === NO_RESULTS ) {
		selectedSubject.value = props.selected;
		return;
	}

	if ( subjectId !== null ) {
		searching.value = false;
		hasUnmatchedText.value = false;
		emit( 'update:selected', subjectId );
	} else if ( !inputText.value ) {
		emit( 'update:selected', null );
	}
}

async function createFromTypedText(): Promise<void> {
	if ( subjectCreation === undefined ) {
		return;
	}

	// Shared with the search, so a search started meanwhile abandons this creation rather than
	// overwriting whatever the user has picked by the time it lands.
	const currentSequence = ++requestSequence;

	try {
		// What the user typed names the new Subject, which is the only way it becomes findable
		// again once saved: a Subject with no label is left out of the label search (ADR 31).
		const subject = await subjectCreation.create(
			props.targetSchema,
			typedText.value === '' ? null : typedText.value
		);

		if ( subject === null || currentSequence !== requestSequence ) {
			return;
		}

		searching.value = false;
		searchFoundNothing.value = false;
		hasUnmatchedText.value = false;
		showName( subject.getDisplayName() );
		selectedSubject.value = subject.getId().text;
		emit( 'update:selected', subject.getId().text );
	} catch ( error ) {
		// The host reports its own failures; this only makes sure a throwing one leaves the field
		// holding the target it held before, rather than an empty selection under a red border.
		console.error( 'Failed to create a Subject from the picker:', error );
		selectedSubject.value = props.selected;
	}
}

function onBlur(): void {
	hasUnmatchedText.value = !!inputText.value && selectedSubject.value === null;
	emit( 'blur', hasUnmatchedText.value );
}

function focus(): void {
	const input = ( lookupRef.value?.$el as HTMLElement )?.querySelector( 'input' );
	input?.focus();
}

defineExpose( { focus } );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-subject-picker {
	&__row {
		display: flex;
		align-items: flex-start;
		gap: @spacing-25;

		.cdx-lookup {
			flex: 1;
			min-width: 0;
		}
	}

	/* The create option is the last item, and it offers an action rather than a result. Codex
		sets its own pinned footer item apart the same way, and skips the rule when that item is
		the only one — a line above a lone entry reads as a mistake. This menu cannot use that
		footer: it is a CdxMenu prop, and CdxLookup passes none of it through. */
	&--offers-create .cdx-menu__listbox > .cdx-menu-item:last-child:not( :first-child ) {
		border-top: @border-subtle;
	}
}
</style>
