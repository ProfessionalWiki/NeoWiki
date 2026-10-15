<template>
	<!-- cdx-field class is used for spacing -->
	<div class="select-attributes cdx-field">
		<CdxField
			class="select-attributes__multiple ext-neowiki-severity-row"
			:hide-label="true"
		>
			<CdxCheckbox
				:model-value="property.multiple"
				@update:model-value="updateMultiple"
			>
				{{ $i18n( 'neowiki-property-editor-multiple' ).text() }}
			</CdxCheckbox>
			<SeverityInput
				v-if="!property.multiple"
				:constraint="$i18n( 'neowiki-property-editor-single-value' ).text()"
				:model-value="property.constraintSeverities?.multiple"
				@update:model-value="updateSeverity( 'multiple', $event )"
			/>
		</CdxField>

		<CdxField
			class="select-attributes__options ext-neowiki-severity-field"
			:is-fieldset="true"
		>
			<template #label>
				{{ $i18n( 'neowiki-property-editor-options' ).text() }}
			</template>
			<ol
				v-if="rows.length > 0"
				ref="listRef"
				class="ext-neowiki-select-attributes__option-list"
			>
				<li
					v-for="( row, index ) in rows"
					:key="row.id"
					class="ext-neowiki-select-attributes__option"
				>
					<div class="ext-neowiki-select-attributes__option-controls">
						<CdxTextInput
							class="ext-neowiki-select-attributes__option-label"
							:model-value="row.label"
							:status="row.shownProblem === null ? 'default' : 'error'"
							:aria-label="row.labelInputName"
							@update:model-value="relabel( index, String( $event ) )"
							@keydown.enter="addAfter( index, $event )"
							@blur="touchedOptionIds.add( row.id )"
						/>
						<CdxButton
							class="ext-neowiki-select-attributes__move-up"
							weight="quiet"
							:disabled="index === 0"
							:aria-label="row.moveUpLabel"
							:title="row.moveUpLabel"
							@click="move( index, index - 1 )"
						>
							<CdxIcon :icon="cdxIconUpTriangle" />
						</CdxButton>
						<CdxButton
							class="ext-neowiki-select-attributes__move-down"
							weight="quiet"
							:disabled="index === rows.length - 1"
							:aria-label="row.moveDownLabel"
							:title="row.moveDownLabel"
							@click="move( index, index + 1 )"
						>
							<CdxIcon :icon="cdxIconDownTriangle" />
						</CdxButton>
						<CdxButton
							class="ext-neowiki-select-attributes__remove"
							weight="quiet"
							action="destructive"
							:aria-label="row.removeLabel"
							:title="row.removeLabel"
							@click="remove( index )"
						>
							<CdxIcon :icon="cdxIconTrash" />
						</CdxButton>
					</div>
					<CdxMessage
						v-if="row.shownProblem !== null"
						type="error"
						:inline="true"
					>
						{{ row.shownProblem }}
					</CdxMessage>
				</li>
			</ol>
			<CdxButton
				ref="addButtonRef"
				class="ext-neowiki-select-attributes__add-option"
				@click="insertOption( property.options.length )"
			>
				<CdxIcon :icon="cdxIconAdd" />
				{{ $i18n( 'neowiki-property-editor-add-option' ).text() }}
			</CdxButton>
			<SeverityInput
				v-if="property.options.length > 0"
				:constraint="$i18n( 'neowiki-property-editor-options' ).text()"
				:model-value="property.constraintSeverities?.options"
				@update:model-value="updateSeverity( 'options', $event )"
			/>
			<span
				class="ext-neowiki-select-attributes__announcement"
				aria-live="polite"
			>{{ announcement }}</span>
		</CdxField>
	</div>
</template>

<script setup lang="ts">
import { computed, inject, nextTick, ref } from 'vue';
import { CdxButton, CdxCheckbox, CdxField, CdxIcon, CdxMessage, CdxTextInput } from '@wikimedia/codex';
import { cdxIconAdd, cdxIconDownTriangle, cdxIconTrash, cdxIconUpTriangle } from '@wikimedia/codex-icons';
import { newSelectOptionId, SelectProperty } from '@/domain/propertyTypes/Select.ts';
import { withConstraintSeverity } from '@/domain/PropertyDefinition.ts';
import type { Severity } from '@/domain/Severity.ts';
import { newStringValue, type StringValue, type Value, ValueType } from '@/domain/Value.ts';
import { AttributesEditorEmits, AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';
import { selectOptionProblems } from '@/components/SchemaEditor/Property/selectOptionProblems.ts';
import { ErrorsRevealedKey } from '@/components/SchemaEditor/ErrorsRevealed.ts';
import SeverityInput from '@/components/SchemaEditor/Property/SeverityInput.vue';

interface OptionRow {
	id: string;
	label: string;
	labelInputName: string;
	moveUpLabel: string;
	moveDownLabel: string;
	removeLabel: string;
	/** What is wrong with the option, once the user has left its field or a save was refused. */
	shownProblem: string | null;
}

const props = defineProps<AttributesEditorProps<SelectProperty>>();
const emit = defineEmits<AttributesEditorEmits<SelectProperty>>();

const listRef = ref<HTMLElement | null>( null );
const addButtonRef = ref<InstanceType<typeof CdxButton> | null>( null );

const touchedOptionIds = ref( new Set<string>() );
const errorsRevealed = inject( ErrorsRevealedKey, ref( false ) );

const announcement = ref( '' );

const rows = computed( (): OptionRow[] => {
	const problems = selectOptionProblems( props.property.options );

	return props.property.options.map( ( option, index ): OptionRow => {
		const position = index + 1;
		const problem = problems.get( option.id );
		const showsProblem = touchedOptionIds.value.has( option.id ) || errorsRevealed.value;

		return {
			id: option.id,
			label: option.label,
			labelInputName: mw.message( 'neowiki-property-editor-option', position ).text(),
			moveUpLabel: mw.message( 'neowiki-property-editor-move-option-up', position ).text(),
			moveDownLabel: mw.message( 'neowiki-property-editor-move-option-down', position ).text(),
			removeLabel: mw.message( 'neowiki-property-editor-remove-option', position ).text(),
			shownProblem: problem !== undefined && showsProblem ? mw.message( problem ).text() : null
		};
	} );
} );

function relabel( index: number, label: string ): void {
	const options = props.property.options.map(
		( option, optionIndex ) => optionIndex === index ? { ...option, label } : option
	);

	emit( 'update:property', { options } );
}

async function insertOption( at: number ): Promise<void> {
	const options = [ ...props.property.options ];
	options.splice( at, 0, { id: newSelectOptionId(), label: '' } );

	emit( 'update:property', { options } );
	await nextTick();

	listRef.value?.children[ at ]?.querySelector<HTMLElement>( 'input' )?.focus();
}

// The Enter that ends an input method composition confirms the composed text.
function addAfter( index: number, event: KeyboardEvent ): void {
	if ( !event.isComposing && props.property.options[ index ].label.trim() !== '' ) {
		insertOption( index + 1 );
	}
}

/**
 * The focus stays on the button that moved the option, unless that button cannot move it any
 * further, which hands it to the one that can.
 */
async function move( from: number, to: number ): Promise<void> {
	const options = [ ...props.property.options ];
	const [ moved ] = options.splice( from, 1 );
	options.splice( to, 0, moved );

	emit( 'update:property', { options } );
	await announce( mw.message( 'neowiki-property-editor-option-moved', to + 1, options.length ).text() );

	const movedUp = to < from;
	const sameButton = movedUp ? 'move-up' : 'move-down';
	const otherButton = movedUp ? 'move-down' : 'move-up';
	const atTheEnd = movedUp ? to === 0 : to === options.length - 1;

	focusButtonOf( to, atTheEnd ? otherButton : sameButton );
}

/**
 * The focus goes to the option that took the removed one's place, or else the one before it, or else,
 * when none is left, to the button that adds one.
 */
async function remove( index: number ): Promise<void> {
	const removed = props.property.options[ index ];
	const options = props.property.options.filter( ( option ) => option !== removed );

	emit( 'update:property', { options, default: defaultWithout( removed.id ) } );
	await announce( mw.message( 'neowiki-property-editor-option-removed' ).text() );

	if ( options.length === 0 ) {
		( addButtonRef.value?.$el as HTMLElement | undefined )?.focus();
	} else {
		focusButtonOf( Math.min( index, options.length - 1 ), 'remove' );
	}
}

// An initial value holding a removed option would give every new Subject a value its options rule out.
function defaultWithout( optionId: string ): Value | undefined {
	const value = props.property.default;

	if ( value?.type !== ValueType.String ) {
		return value;
	}

	const optionIds = ( value as StringValue ).parts.filter( ( part ) => part !== optionId );

	return optionIds.length > 0 ? newStringValue( optionIds ) : undefined;
}

// A live region speaks when its text changes, so it is emptied first: the next message may read as the last.
async function announce( message: string ): Promise<void> {
	announcement.value = '';
	await nextTick();
	announcement.value = message;
}

function focusButtonOf( index: number, button: 'move-up' | 'move-down' | 'remove' ): void {
	listRef.value?.children[ index ]
		?.querySelector<HTMLElement>( `.ext-neowiki-select-attributes__${ button }` )
		?.focus();
}

const updateMultiple = ( value: boolean ): void => {
	emit( 'update:property', { multiple: value } );
};

const updateSeverity = ( constraint: 'options' | 'multiple', severity: Severity ): void => {
	emit( 'update:property', withConstraintSeverity( props.property, constraint, severity ) );
};
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';
@import ( reference ) '@/assets/mixins.less';

.ext-neowiki-select-attributes {
	&__option-list {
		margin: 0 0 @spacing-50;
		padding: 0;
		list-style: none;
	}

	&__option {
		margin: 0;

		& + & {
			margin-top: @spacing-50;
		}
	}

	&__option-controls {
		display: flex;
		align-items: center;
		gap: @spacing-25;
	}

	&__option-label {
		flex-grow: 1;
		min-width: 0;
	}

	&__announcement {
		.ext-neowiki-visually-hidden();
	}
}
</style>
