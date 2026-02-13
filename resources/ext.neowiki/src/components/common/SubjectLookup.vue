<template>
	<div class="ext-neowiki-subject-lookup">
		<CdxLookup
			ref="lookupRef"
			v-model:selected="selectedSubject"
			v-model:input-value="inputText"
			:menu-items="menuItems"
			:start-icon="props.startIcon"
			:placeholder="$i18n( 'neowiki-subject-lookup-placeholder' ).text()"
			:status="effectiveStatus"
			:aria-label="props.ariaLabel"
			@input="onLookupInput"
			@update:selected="onSubjectSelected"
			@blur="onBlur"
		>
			<template v-if="searchActive" #no-results>
				{{ $i18n( 'neowiki-subject-lookup-no-results' ).text() }}
			</template>
		</CdxLookup>
		<CdxMessage
			v-if="hasUnmatchedText"
			type="error"
			inline
		>
			{{ $i18n( 'neowiki-subject-lookup-no-match' ).text() }}
		</CdxMessage>
	</div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { CdxLookup, CdxMessage } from '@wikimedia/codex';
import type { MenuItemData, ValidationStatusType } from '@wikimedia/codex';
import type { Icon } from '@wikimedia/codex-icons';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { SubjectId } from '@/domain/SubjectId.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';

interface SubjectLookupProps {
	selected: string | null;
	targetSchema: string;
	startIcon?: Icon;
	status?: ValidationStatusType | 'default';
	ariaLabel?: string;
}

const props = withDefaults(
	defineProps<SubjectLookupProps>(),
	{
		startIcon: undefined,
		status: 'default',
		ariaLabel: undefined
	}
);

const emit = defineEmits<{
	'update:selected': [ value: string | null ];
	'blur': [ hasUnmatchedText: boolean ];
}>();

const subjectStore = useSubjectStore();
const subjectLabelSearch = NeoWikiServices.getSubjectLabelSearch();

async function resolveLabel( id: string | null ): Promise<string> {
	if ( !id ) {
		return '';
	}

	try {
		const subject = await subjectStore.getOrFetchSubject( new SubjectId( id ) );
		return subject?.getLabel() ?? id;
	} catch {
		return id;
	}
}

const selectedSubject = ref<string | null>( props.selected );
const inputText = ref<string | number>( '' );
const menuItems = ref<MenuItemData[]>( [] );
const lookupRef = ref<InstanceType<typeof CdxLookup> | null>( null );
const searchActive = ref( false );
const hasUnmatchedText = ref( false );
let requestSequence = 0;

const effectiveStatus = computed( (): ValidationStatusType | 'default' =>
	hasUnmatchedText.value ? 'error' : props.status
);

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
		menuItems.value = [];
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

		menuItems.value = results.map( ( result ) => ( {
			label: result.label,
			value: result.id
		} ) );
	} catch {
		if ( currentSequence !== requestSequence ) {
			return;
		}

		menuItems.value = [];
	}
}

function onSubjectSelected( subjectId: string | null ): void {
	if ( subjectId !== null ) {
		searchActive.value = false;
		hasUnmatchedText.value = false;
		emit( 'update:selected', subjectId );
	} else if ( !inputText.value ) {
		emit( 'update:selected', null );
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
