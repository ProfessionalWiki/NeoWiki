<template>
	<div class="ext-neowiki-subject-lookup">
		<CdxLookup
			ref="lookupRef"
			v-model:selected="selectedSubject"
			v-model:input-value="inputText"
			:menu-items="menuItems"
			:start-icon="props.startIcon"
			:placeholder="$i18n( 'neowiki-subject-lookup-placeholder' ).text()"
			:status="props.status"
			:aria-label="props.ariaLabel"
			@input="onLookupInput"
			@update:selected="onSubjectSelected"
			@blur="emit( 'blur' )"
		>
			<template v-if="searchActive" #no-results>
				{{ $i18n( 'neowiki-subject-lookup-no-results' ).text() }}
			</template>
		</CdxLookup>
	</div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { CdxLookup } from '@wikimedia/codex';
import type { MenuItemData, ValidationStatusType } from '@wikimedia/codex';
import type { Icon } from '@wikimedia/codex-icons';

interface StubSubject {
	id: string;
	label: string;
	schema: string;
}

const STUB_SUBJECTS: StubSubject[] = [
	{ id: 's1demo1aaaaaaa1', label: 'ACME Inc.', schema: 'Company' },
	{ id: 's1demo5sssssss1', label: 'Professional Wiki GmbH', schema: 'Company' },
	{ id: 's1demo1aaaaaaa2', label: 'Foo', schema: 'Product' },
	{ id: 's1demo1aaaaaaa3', label: 'Bar', schema: 'Product' },
	{ id: 's1demo1aaaaaaa4', label: 'Baz', schema: 'Product' },
	{ id: 's1demo4sssssss1', label: 'NeoWiki', schema: 'Product' },
	{ id: 's1demo6sssssss1', label: 'ProWiki', schema: 'Product' },
	{ id: 's1demo2sssssss1', label: 'Berlin', schema: 'City' }
];

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
	'update:inputText': [ value: string ];
	'blur': [];
}>();

function resolveLabel( id: string | null ): string {
	if ( !id ) {
		return '';
	}
	const stub = STUB_SUBJECTS.find( ( s ) => s.id === id );
	return stub ? stub.label : id;
}

const selectedSubject = ref<string | null>( props.selected );
const inputText = ref<string | number>( resolveLabel( props.selected ) );
const menuItems = ref<MenuItemData[]>( [] );
const lookupRef = ref<InstanceType<typeof CdxLookup> | null>( null );
const searchActive = ref( false );

watch( () => props.selected, ( newSelected ) => {
	selectedSubject.value = newSelected;

	if ( newSelected !== null || !searchActive.value ) {
		inputText.value = resolveLabel( newSelected );
	}

	searchActive.value = false;
} );

function onLookupInput( value: string ): void {
	emit( 'update:inputText', value );

	if ( !value ) {
		menuItems.value = [];
		searchActive.value = false;
		return;
	}

	searchActive.value = true;

	const query = value.toLowerCase();
	menuItems.value = STUB_SUBJECTS
		.filter( ( subject ) =>
			subject.schema === props.targetSchema &&
			subject.label.toLowerCase().includes( query )
		)
		.map( ( subject ) => ( {
			label: subject.label,
			value: subject.id
		} ) );
}

function onSubjectSelected( subjectId: string | null ): void {
	if ( subjectId !== null ) {
		searchActive.value = false;
	}
	emit( 'update:selected', subjectId );
}

function focus(): void {
	const input = ( lookupRef.value?.$el as HTMLElement )?.querySelector( 'input' );
	input?.focus();
}

defineExpose( { focus } );
</script>
