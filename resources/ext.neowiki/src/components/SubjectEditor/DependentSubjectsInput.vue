<template>
	<CdxField
		:is-fieldset="true"
		:messages="firstMessages"
		:status="violationStatus( firstMessages )"
		:optional="props.property.required === false"
	>
		<template #label>
			{{ props.label }}
			<CdxIcon
				v-if="props.property.description"
				v-tooltip="props.property.description"
				:icon="cdxIconInfo"
				class="ext-neowiki-value-input__description-icon"
				size="small"
			/>
		</template>

		<div class="ext-neowiki-dependent-subjects-input">
			<!-- Kept alive, so that Undo brings a removed group back with what was typed into it. -->
			<KeepAlive
				v-for="id in shownIds"
				:key="id"
			>
				<DependentSubjectEditor
					v-if="!removedIds.has( id )"
					:ref="( instance ) => setGroupRef( id, instance )"
					:subject="editing?.subject( id )"
					:schema="schemaOf( id )"
					:is-new="editing?.isNew( id ) ?? false"
					:name="groupName( id )"
					:shows-schema-name="targetSchemaNames.length > 1"
					@remove="remove( id )"
					@change="emit( 'dependent-subject-change', $event )"
					@edit-relation-target="emit( 'edit-relation-target', $event )"
				/>
				<div
					v-else
					:ref="( element ) => setCardRef( id, element )"
					class="ext-neowiki-dependent-subjects-input__removed"
				>
					<span :id="cardTextId( id )">
						{{ removedText( id ) }}
					</span>
					<CdxButton
						weight="quiet"
						action="progressive"
						:aria-describedby="cardTextId( id )"
						@click="undo( id )"
					>
						{{ $i18n( 'neowiki-dependent-subject-undo' ).text() }}
					</CdxButton>
				</div>
			</KeepAlive>

			<!-- While it adds, the control stays enabled, so that the focus stays on it; add() ignores it meanwhile. -->
			<div
				v-if="addable"
				class="ext-neowiki-dependent-subjects-input__add"
			>
				<!-- Quiet, as Codex makes the menu button below. -->
				<CdxButton
					v-if="addableSchemaNames.length === 1"
					weight="quiet"
					:aria-disabled="adding || undefined"
					@click="add( addableSchemaNames[ 0 ] )"
				>
					<CdxIcon :icon="cdxIconAdd" />
					{{ $i18n( 'neowiki-dependent-subject-add', addableSchemaNames[ 0 ] ).text() }}
				</CdxButton>

				<CdxMenuButton
					v-else
					v-model:selected="menuSelection"
					:menu-items="addMenuItems"
					:aria-disabled="adding || undefined"
					@update:selected="onSchemaChosen"
				>
					<CdxIcon :icon="cdxIconAdd" />
					{{ $i18n( 'neowiki-dependent-subject-add-choose' ).text() }}
				</CdxMenuButton>
			</div>
		</div>
	</CdxField>
</template>

<script lang="ts">
import type { ValueInputEmits, ValueInputExposes } from '@/components/Value/ValueInputContract.ts';
import type { Subject } from '@/domain/Subject.ts';

export interface DependentSubjectsInputExposes extends ValueInputExposes {
	/**
	 * As their fields hold them, each followed by those inside it.
	 */
	dependentSubjects(): Subject[];
}

export type DependentSubjectsInputEmits = ValueInputEmits & {
	/**
	 * A change inside one of its Dependent Subjects; true when to a relation, which can change which
	 * Subjects the save reaches.
	 */
	'dependent-subject-change': [ relationChanged: boolean ];
};
</script>

<script setup lang="ts">
import { computed, inject, nextTick, ref, shallowReactive, shallowRef, toRef, watch } from 'vue';
import { CdxButton, CdxField, CdxIcon, CdxMenuButton, useGeneratedId } from '@wikimedia/codex';
import type { MenuButtonItemData } from '@wikimedia/codex';
import { cdxIconAdd, cdxIconInfo } from '@wikimedia/codex-icons';
import DependentSubjectEditor from '@/components/SubjectEditor/DependentSubjectEditor.vue';
import type { DependentSubjectEditorExposes } from '@/components/SubjectEditor/DependentSubjectEditor.vue';
import { DependentSubjectEditingKey, PaneWritesKey } from '@/components/SubjectEditor/DependentSubjectEditing.ts';
import type { ValueInputProps } from '@/components/Value/ValueInputContract.ts';
import { localTargetSchemaNames, type RelationProperty } from '@/domain/propertyTypes/Relation.ts';
import { RelationValue, relationValueWithTargets, type Value, ValueType } from '@/domain/Value.ts';
import type { Schema } from '@/domain/Schema.ts';
import { useServerViolations, violationStatus } from '@/composables/useServerViolations.ts';

const props = withDefaults(
	defineProps<ValueInputProps<RelationProperty>>(),
	{
		modelValue: undefined,
		label: ''
	}
);

const emit = defineEmits<DependentSubjectsInputEmits>();

const editing = inject( DependentSubjectEditingKey, undefined );

const { firstMessages, emitClears } = useServerViolations(
	toRef( props, 'property' ),
	toRef( props, 'serverViolations' ),
	emit
);

// One Relation per group, in group order, removed groups included; a Relation keeps its id.
const shownValue = shallowRef<RelationValue | undefined>( undefined );

// Until the Subject is written: Undo brings a removed group back in its place.
const removedIds = shallowRef<ReadonlySet<string>>( new Set() );

const shownIds = computed( (): string[] =>
	shownValue.value?.relations.map( ( relation ) => relation.target.text ) ?? [] );

const targetIds = computed( (): string[] => shownIds.value.filter( ( id ) => !removedIds.value.has( id ) ) );

const currentValue = computed( (): RelationValue | undefined => {
	const relations = shownValue.value?.relations.filter( ( relation ) => !removedIds.value.has( relation.target.text ) ) ?? [];

	return relations.length === 0 ? undefined : new RelationValue( relations );
} );

function startFrom( value: Value | undefined ): void {
	shownValue.value = value?.type === ValueType.Relation && ( value as RelationValue ).relations.length > 0 ?
		value as RelationValue :
		undefined;
	removedIds.value = new Set();
}

startFrom( props.modelValue );

watch( () => props.modelValue, startFrom );

const paneWrites = inject( PaneWritesKey, undefined );

if ( paneWrites !== undefined ) {
	watch( paneWrites, () => {
		shownValue.value = currentValue.value;
		removedIds.value = new Set();
	} );
}

const targetSchemaNames = computed( (): string[] => localTargetSchemaNames( props.property ) );

const addableSchemaNames = computed( (): string[] =>
	targetSchemaNames.value.filter( ( name ) => editing?.schema( name ) !== undefined ) );

const adding = ref( false );

const addable = computed( (): boolean =>
	addableSchemaNames.value.length > 0 && ( props.property.multiple === true || targetIds.value.length === 0 ) );

const addMenuItems = computed( (): MenuButtonItemData[] =>
	addableSchemaNames.value.map( ( name ) => ( { value: name, label: name } ) ) );

const menuSelection = ref<string | number | null>( null );

const groupRefs = shallowReactive( new Map<string, DependentSubjectEditorExposes>() );

// KeepAlive unsets the ref of a removed group, and Undo reads the group before it is back.
function setGroupRef( id: string, instance: unknown ): void {
	if ( instance ) {
		groupRefs.set( id, instance as DependentSubjectEditorExposes );
	} else if ( !shownIds.value.includes( id ) ) {
		groupRefs.delete( id );
	}
}

const cardRefs = new Map<string, HTMLElement>();

function setCardRef( id: string, element: unknown ): void {
	if ( element instanceof HTMLElement ) {
		cardRefs.set( id, element );
	} else {
		cardRefs.delete( id );
	}
}

const cardIdPrefix = useGeneratedId( 'ext-neowiki-dependent-subjects-input' );

function cardTextId( id: string ): string {
	return `${ cardIdPrefix }-${ id }`;
}

function schemaOf( id: string ): Schema | undefined {
	const subject = editing?.subject( id );

	return subject === undefined ? undefined : editing?.schema( subject.getSchemaName() );
}

function schemaNameOf( id: string ): string {
	return editing?.subject( id )?.getSchemaName() ?? props.label;
}

// Numbered among those of its Schema where the field shows several, so that each remove control names its own group.
function groupName( id: string ): string {
	const schemaName = schemaNameOf( id );
	const sameSchemaIds = shownIds.value.filter( ( shownId ) => schemaNameOf( shownId ) === schemaName );

	return sameSchemaIds.length > 1 ?
		mw.message( 'neowiki-dependent-subject-numbered', schemaName, String( sameSchemaIds.indexOf( id ) + 1 ) ).text() :
		schemaName;
}

function removedText( id: string ): string {
	return mw.message( 'neowiki-dependent-subject-removed', groupName( id ) ).text();
}

function emitValue(): void {
	emit( 'update:modelValue', currentValue.value );
	emitClears( 'all' );
}

async function add( schemaName: string ): Promise<void> {
	if ( editing === undefined || adding.value ) {
		return;
	}

	adding.value = true;

	try {
		const subject = await editing.add( schemaName );

		if ( subject === null ) {
			return;
		}

		// A single-valued property can hold only the added one, so a removal there is not to be undone any more.
		const keptIds = props.property.multiple === true ? shownIds.value : [];
		const id = subject.getId().text;

		shownValue.value = relationValueWithTargets( shownValue.value, [ ...keptIds, id ] );
		removedIds.value = new Set( keptIds.filter( ( keptId ) => removedIds.value.has( keptId ) ) );
		emitValue();

		await nextTick();
		groupRefs.get( id )?.focus();
	} finally {
		adding.value = false;
	}
}

function onSchemaChosen( schemaName: string | number | null ): void {
	menuSelection.value = null;

	if ( typeof schemaName === 'string' ) {
		add( schemaName );
	}
}

// The card's Undo button, which takes the focus, is described by what the card says was removed.
async function remove( id: string ): Promise<void> {
	removedIds.value = new Set( [ ...removedIds.value, id ] );
	emitValue();

	await nextTick();
	cardRefs.get( id )?.querySelector( 'button' )?.focus();
}

async function undo( id: string ): Promise<void> {
	removedIds.value = new Set( [ ...removedIds.value ].filter( ( removedId ) => removedId !== id ) );
	emitValue();

	await nextTick();
	groupRefs.get( id )?.focus();
}

defineExpose<DependentSubjectsInputExposes>( {
	getCurrentValue: (): Value | undefined => currentValue.value,
	unparseableInputMessage: (): string | null => {
		for ( const id of targetIds.value ) {
			const blocker = groupRefs.get( id )?.saveBlocker() ?? null;

			if ( blocker !== null ) {
				return blocker.message;
			}
		}

		return null;
	},
	dependentSubjects: (): Subject[] => {
		const subjects: Subject[] = [];

		for ( const id of targetIds.value ) {
			const group = groupRefs.get( id );
			const subject = group?.buildSubject() ?? null;

			if ( group !== undefined && subject !== null ) {
				subjects.push( subject, ...group.dependentSubjects() );
			}
		}

		return subjects;
	}
} );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

/* A gap rather than margins: groups, cards and the add control follow one another in any order, and the add
	control alone sits flush with the label. */
.ext-neowiki-dependent-subjects-input {
	display: flex;
	flex-direction: column;
	gap: @spacing-75;

	&__removed {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: @spacing-50;
		padding-inline: @spacing-75 @spacing-25;
		border: @border-width-base @border-style-dashed @border-color-subtle;
		border-radius: @border-radius-base;
		color: @color-subtle;
	}
}
</style>
