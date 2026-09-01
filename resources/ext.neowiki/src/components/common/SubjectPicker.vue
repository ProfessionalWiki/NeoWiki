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
				<template v-if="searchActive && !creationOffered" #no-results>
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
import { ref, computed, watch } from 'vue';
import { CdxLookup, CdxMessage } from '@wikimedia/codex';
import type { MenuItemData, ValidationStatusType } from '@wikimedia/codex';
import { cdxIconAdd } from '@wikimedia/codex-icons';
import type { Icon } from '@wikimedia/codex-icons';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import type { Subject } from '@/domain/Subject.ts';
import { SubjectId } from '@/domain/SubjectId.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';

interface SubjectPickerProps {
	selected: string | null;
	targetSchema: string;
	startIcon?: Icon;
	status?: ValidationStatusType | 'default';
	ariaLabel?: string;
	/**
	 * Supplied by a host that can create a Subject of targetSchema on the spot. It is given the
	 * text the user had typed, or null when they typed none, and resolves to the new Subject —
	 * or to null when the creation failed or was abandoned. Absent means no create option.
	 */
	createSubject?: ( label: string | null ) => Promise<Subject | null>;
}

const props = withDefaults(
	defineProps<SubjectPickerProps>(),
	{
		startIcon: undefined,
		status: 'default',
		ariaLabel: undefined,
		createSubject: undefined
	}
);

// Codex has no value of its own for "the user picked the create option", so the option is an
// ordinary menu item carrying a value no Subject id can take (they are 's' plus 14 base58
// characters). Same idiom as DataExportButton's back item.
const CREATE_SUBJECT = '__create__';

const emit = defineEmits<{
	'update:selected': [ value: string | null ];
	'blur': [ hasUnmatchedText: boolean ];
}>();

const subjectStore = useSubjectStore();
const subjectLabelSearch = NeoWikiServices.getSubjectLabelSearch();

const selectedSubject = ref<string | null>( props.selected );
const inputText = ref<string | number>( '' );
const searchResults = ref<MenuItemData[]>( [] );
const lookupRef = ref<InstanceType<typeof CdxLookup> | null>( null );
const searchActive = ref( false );
const hasUnmatchedText = ref( false );
let requestSequence = 0;

const creationOffered = computed( (): boolean => props.createSubject !== undefined );

// A Subject created here exists only in the host's editing session, so no lookup can name it.
// Kept for the life of this picker, since a target picked and then replaced can be picked again.
const createdDisplayNames = ref( new Map<string, string>() );

const typedText = computed( (): string => String( inputText.value ?? '' ).trim() );

const createItem = computed( (): MenuItemData => ( {
	value: CREATE_SUBJECT,
	label: typedText.value === '' ?
		mw.msg( 'neowiki-subject-picker-create', props.targetSchema ) :
		mw.msg( 'neowiki-subject-picker-create-named', typedText.value, props.targetSchema ),
	icon: cdxIconAdd
} ) );

// Codex blanks the input and reports an empty one whenever the selected value names no menu
// item, so a Subject created here has to be among them while it is the selection. Dropped again
// as soon as the user searches, which is when the menu belongs to the results.
const selectedCreatedItems = computed( (): MenuItemData[] => {
	const selected = props.selected;

	if ( searchActive.value || selected === null || !createdDisplayNames.value.has( selected ) ) {
		return [];
	}

	return [ { value: selected, label: createdDisplayNames.value.get( selected ) as string } ];
} );

// The create option is present from the first render and never leaves, which is what opens the
// menu on focus before anything is typed: Codex expands an empty input's menu only when the
// Lookup was built with items, and collapses it again the moment the list runs empty.
const menuItems = computed( (): MenuItemData[] => {
	if ( !creationOffered.value ) {
		return searchResults.value;
	}

	const items = [ ...selectedCreatedItems.value, ...searchResults.value ];

	// Codex's own no-results slot is shown only for an empty menu, which the create option
	// rules out, so the same message is carried as an item nobody can pick.
	if ( searchActive.value && items.length === 0 ) {
		items.push( {
			value: '__no_results__',
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

// A Subject created here answers from what the picker itself recorded: nothing can fetch a
// Subject the server has not been told about yet.
async function resolveLabel( id: string | null ): Promise<string> {
	if ( !id ) {
		return '';
	}

	const created = createdDisplayNames.value.get( id );
	if ( created !== undefined ) {
		return created;
	}

	try {
		const subject = await subjectStore.getOrFetchSubject( new SubjectId( id ) );
		return subject?.getDisplayName() ?? id;
	} catch {
		return id;
	}
}

resolveLabel( props.selected ).then( ( label ) => {
	inputText.value = label;
} );

watch( () => props.selected, async ( newSelected ) => {
	selectedSubject.value = newSelected;
	hasUnmatchedText.value = false;

	if ( newSelected !== null || !searchActive.value ) {
		inputText.value = await resolveLabel( newSelected );
	}

	searchActive.value = false;
} );

async function onLookupInput( value: string ): Promise<void> {
	hasUnmatchedText.value = false;

	if ( !value ) {
		searchResults.value = [];
		searchActive.value = false;
		return;
	}

	searchActive.value = true;
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
	}
}

function onSubjectSelected( subjectId: string | null ): void {
	if ( subjectId === CREATE_SUBJECT ) {
		// Before anything awaits: Codex reads the sentinel back as the selection and would
		// overwrite the input with the create option's own label.
		selectedSubject.value = null;
		createFromTypedText();
		return;
	}

	if ( subjectId !== null ) {
		searchActive.value = false;
		hasUnmatchedText.value = false;
		emit( 'update:selected', subjectId );
	} else if ( !inputText.value ) {
		emit( 'update:selected', null );
	}
}

async function createFromTypedText(): Promise<void> {
	if ( props.createSubject === undefined ) {
		return;
	}

	// What the user typed names the new Subject, which is the only way it becomes findable
	// again: a Subject with no label is left out of the label search (ADR 31).
	const subject = await props.createSubject( typedText.value === '' ? null : typedText.value );

	if ( subject === null ) {
		return;
	}

	const id = subject.getId().text;

	createdDisplayNames.value = new Map( createdDisplayNames.value ).set( id, subject.getDisplayName() );
	searchActive.value = false;
	hasUnmatchedText.value = false;
	inputText.value = subject.getDisplayName();
	selectedSubject.value = id;
	emit( 'update:selected', id );
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
