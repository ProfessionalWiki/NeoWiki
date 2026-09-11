<template>
	<div
		class="ext-neowiki-subject-picker"
		:class="{ 'ext-neowiki-subject-picker--offers-create': creationOffered }"
	>
		<div class="ext-neowiki-subject-picker__row">
			<CdxLookup
				ref="lookupRef"
				v-model:input-value="inputText"
				:selected="selectedSubject"
				:menu-items="menuItems"
				:start-icon="props.startIcon"
				:placeholder="$i18n( 'neowiki-subject-picker-placeholder' ).text()"
				:status="effectiveStatus"
				:aria-label="props.ariaLabel"
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
import type { Subject } from '@/domain/Subject.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';

interface SubjectPickerProps {
	selected: string | null;
	targetSchema: string | null;
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

// A target Schema from another Source is not this wiki's to create a Subject of: what the host
// would mint is a local Subject under a bare local name, which is not the Schema referenced.
const creationOffered = computed( (): boolean =>
	subjectCreation !== undefined && props.targetSchema !== null
);

const selectedSubject = ref<string | null>( props.selected );
const inputText = ref<string | number>( '' );
const searchResults = ref<MenuItemData[]>( [] );
const lookupRef = ref<InstanceType<typeof CdxLookup> | null>( null );
// Idle until the user types, pending while the request is out, done once it has come back — which
// is what lets the menu say "nothing found" only when a search actually found nothing, rather than
// while it is still out.
const searchStatus = ref<'idle' | 'pending' | 'done'>( 'idle' );

const searching = computed( (): boolean => searchStatus.value !== 'idle' );
const hasUnmatchedText = ref( false );
let requestSequence = 0;
// Moved by each creation and by a real pick, and by nothing else: a creation that lands after either
// is abandoned, since it would otherwise overwrite what the user chose meanwhile.
let creationSequence = 0;

// The name the field is currently showing for its selection, so text the user typed can be told
// apart from the label Codex and the watcher below write there themselves.
const selectedName = ref( '' );

// Subjects this session invented, which no search can return. Read through the host on every
// evaluation, so a draft renamed in the editor is renamed here too.
//
// Named bare here and at the two sites below, without the generated-name marker every display uses:
// picking an item writes the string into this field's own text input, where the user can edit it and
// where it feeds the offer to create a Subject under the text they typed.
const draftItems = computed( (): MenuItemData[] =>
	props.targetSchema === null ?
		[] :
		( subjectCreation?.drafts( props.targetSchema ) ?? [] ).map( menuItemFor )
);

function menuItemFor( subject: Subject ): MenuItemData {
	return { value: subject.getId().text, label: subject.getDisplayName() };
}

function draftNameOf( id: string ): string | undefined {
	const item = draftItems.value.find( ( candidate ) => candidate.value === id );
	return item === undefined ? undefined : String( item.label ?? '' );
}

// Only what the user actually typed: the field otherwise holds the selected Subject's own name,
// which would offer to create a second Subject named after the one already chosen.
const typedText = computed( (): string => {
	const text = String( inputText.value ?? '' ).trim();

	return text === selectedName.value ? '' : text;
} );

// Read only where creation is offered, which is the only state in which targetSchema names a
// Schema this wiki can create a Subject of.
const createItem = computed( (): MenuItemData => ( {
	value: CREATE_SUBJECT,
	label: typedText.value === '' ?
		mw.msg( 'neowiki-subject-picker-create', props.targetSchema ?? '' ) :
		mw.msg( 'neowiki-subject-picker-create-named', typedText.value, props.targetSchema ?? '' ),
	icon: cdxIconAdd
} ) );

// Matched here rather than by the search: these Subjects exist only in the editor.
const matchingDraftItems = computed( (): MenuItemData[] => {
	const search = typedText.value.toLowerCase();

	return draftItems.value.filter(
		( item ) => search === '' || String( item.label ).toLowerCase().includes( search )
	);
} );

// The create option is present from the first render and never leaves, which is what opens the
// menu on focus before anything is typed: Codex expands an empty input's menu only when the
// Lookup was built with items, and collapses it again the moment the list runs empty.
const menuItems = computed( (): MenuItemData[] => {
	if ( !creationOffered.value ) {
		return searchResults.value;
	}

	const items = [ ...matchingDraftItems.value, ...searchResults.value ];

	// Codex's own no-results slot is shown only for an empty menu, which the create option rules
	// out, so that case carries the same message as an item nobody can pick.
	if ( searchStatus.value === 'done' && items.length === 0 ) {
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

	return ( await fetchSubject( id ) )?.getDisplayName() ?? id;
}

// Answers with nothing rather than throwing for an id the wiki does not hold, or holds on a page
// this user may not read.
async function fetchSubject( id: string ): Promise<Subject | null> {
	try {
		return await subjectStore.getOrFetchSubject( new SubjectId( id ) );
	} catch {
		return null;
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

	if ( newSelected !== null || searchStatus.value === 'idle' ) {
		showName( await resolveName( newSelected ) );
	}

	searchStatus.value = 'idle';
} );

// A draft renamed in the editor renames the field pointing at it.
watch( () => props.selected === null ? undefined : draftNameOf( props.selected ), ( name ) => {
	if ( name !== undefined ) {
		showName( name );
	}
} );

// The field's value rather than CdxLookup's `input` event, as in PagePicker: Codex also emits that
// event for text it writes itself, such as the label of an item just picked.
watch( inputText, ( value ) => {
	onFieldTextChanged( String( value ?? '' ) );
} );

async function onFieldTextChanged( value: string ): Promise<void> {
	hasUnmatchedText.value = false;

	// The selection's own name, written by Codex on a pick or by showName, is not the user typing.
	// An emptied field never is that name, and has its own answer below.
	if ( value !== '' && value.trim() === selectedName.value ) {
		return;
	}

	// What the field shows is the user's now, so the name it held excuses no later text of theirs:
	// typing the target's own name back has to search for it like any other.
	selectedName.value = '';

	if ( value === '' ) {
		// Abandons a lookup still in flight, whose answer would otherwise fill the menu of a field
		// the user has since emptied.
		++requestSequence;
		searchResults.value = [];
		searchStatus.value = 'idle';

		// Emptying a field the user typed over empties the relation, which Codex reports itself only
		// while it still holds a selection. Codex also blanks the field for a target its menu does
		// not list, such as one the host has just set; that target stands.
		if ( selectedSubject.value === null && props.selected !== null ) {
			emit( 'update:selected', null );
		}

		return;
	}

	searchStatus.value = 'pending';
	const currentSequence = ++requestSequence;

	const candidates = await candidatesFor( value );

	if ( currentSequence !== requestSequence ) {
		return;
	}

	searchResults.value = candidates;
	searchStatus.value = 'done';
}

// An id names one Subject, so it is read rather than searched for. That read is how a Subject with
// no label is reached at all, ADR 31 leaving such a Subject out of the label search. It does not
// replace the search, though: the shape of an id is also the shape of an ordinary fifteen-letter
// word, so text that names no usable Subject goes on to be searched for as a label.
async function candidatesFor( value: string ): Promise<MenuItemData[]> {
	// A target Schema from another Source names nothing here: neither the label search nor the
	// Schema an id-lookup would be matched against is this wiki's to answer with.
	if ( props.targetSchema === null ) {
		return [];
	}

	const text = value.trim();

	if ( SubjectId.isValid( text ) ) {
		const subject = await fetchSubject( text );

		// A Subject of another Schema cannot be this relation's target. One the user may not read
		// never reaches here at all: the read fails instead of answering.
		if ( subject !== null && subject.getSchemaName() === props.targetSchema ) {
			return [ menuItemFor( subject ) ];
		}
	}

	return searchLabels( text, props.targetSchema );
}

async function searchLabels( value: string, targetSchema: string ): Promise<MenuItemData[]> {
	try {
		const results = await subjectLabelSearch.searchSubjectLabels( value, targetSchema );

		return results.map( ( result ) => ( {
			label: result.label,
			value: result.id
		} ) );
	} catch {
		return [];
	}
}

// Codex's selection is bound one way so that neither sentinel ever becomes it. Codex writes the
// label of whatever it selects into the field, or nothing for an item its menu does not list: the
// create option's label would replace the typed name, and putting back a target the user typed
// over would blank the field.
function onSubjectSelected( subjectId: string | null ): void {
	if ( subjectId === CREATE_SUBJECT ) {
		createFromTypedText();
		return;
	}

	// Codex refuses to select a disabled item, but the picker does not rely on that to keep its
	// own sentinel out of a relation.
	if ( subjectId === NO_RESULTS ) {
		return;
	}

	selectedSubject.value = subjectId;

	// Codex drops its selection whenever the text changes. The relation keeps its target while the
	// user types over it, and the field watcher empties it once the field is empty.
	if ( subjectId === null ) {
		return;
	}

	// A pick outranks a creation still in flight.
	++creationSequence;

	// Recorded before the parent answers with a new props.selected: Codex writes the picked item's
	// label into the field, and until this catches up that label would read as text the user had
	// typed — which is what the create option is named after, and what starts a search.
	const picked = menuItems.value.find( ( item ) => item.value === subjectId );
	if ( picked !== undefined ) {
		selectedName.value = String( picked.label ?? '' );
	}

	searchStatus.value = 'idle';
	hasUnmatchedText.value = false;
	emit( 'update:selected', subjectId );
}

async function createFromTypedText(): Promise<void> {
	if ( subjectCreation === undefined || props.targetSchema === null ) {
		return;
	}

	const currentCreation = ++creationSequence;

	try {
		// What the user typed names the new Subject, which is the only way it becomes findable
		// again once saved: a Subject with no label is left out of the label search (ADR 31).
		const subject = await subjectCreation.create(
			props.targetSchema,
			typedText.value === '' ? null : typedText.value
		);

		if ( subject === null || currentCreation !== creationSequence ) {
			return;
		}

		// A search still out would answer for text the new Subject's name now replaces.
		++requestSequence;
		searchStatus.value = 'idle';
		hasUnmatchedText.value = false;
		showName( subject.getDisplayName() );
		selectedSubject.value = subject.getId().text;
		emit( 'update:selected', subject.getId().text );
	} catch ( error ) {
		// Only a host breaking its contract throws: one that fails reports it and answers null.
		console.error( 'Failed to create a Subject from the picker:', error );
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
