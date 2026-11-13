<template>
	<div class="ext-neowiki-edit-conflict-editor">
		<div class="ext-neowiki-edit-conflict-editor__intro">
			<h3
				ref="title"
				class="ext-neowiki-edit-conflict-editor__title"
				tabindex="-1"
			>
				{{ $i18n( 'neowiki-subject-editor-edit-conflict-title', props.subjectName ).text() }}
			</h3>
			<p>{{ $i18n( 'neowiki-subject-editor-edit-conflict' ).text() }}</p>
		</div>

		<fieldset
			v-for="change in props.conflict.bothChanged"
			:key="change.key"
			class="ext-neowiki-edit-conflict-editor__field"
		>
			<legend class="ext-neowiki-edit-conflict-editor__legend">
				{{ fieldName( change ) }}
			</legend>
			<div class="ext-neowiki-edit-conflict-editor__options">
				<label
					v-for="option in optionsFor( change )"
					:key="option.choice"
					class="ext-neowiki-edit-conflict-editor__option"
					:class="{
						'ext-neowiki-edit-conflict-editor__option--selected': props.choices.get( change.key ) === option.choice,
						'ext-neowiki-edit-conflict-editor__option--wide': option.choice === 'both'
					}"
					@click="chooseInsteadOfFollowingALink( $event, change.key, option.choice )"
				>
					<input
						class="ext-neowiki-edit-conflict-editor__radio"
						type="radio"
						:name="change.key"
						:value="option.choice"
						:checked="props.choices.get( change.key ) === option.choice"
						@change="emit( 'choose', change.key, option.choice )"
					>
					<span class="ext-neowiki-edit-conflict-editor__option-content">
						<span class="ext-neowiki-edit-conflict-editor__side">{{ option.caption }}</span>
						<EditConflictValueDisplay
							:change="change"
							:side="option.choice"
							:property="propertyOf( change )"
						/>
					</span>
					<CdxIcon
						v-if="props.choices.get( change.key ) === option.choice"
						class="ext-neowiki-edit-conflict-editor__check"
						:icon="cdxIconCheck"
						size="small"
					/>
				</label>
			</div>
			<div
				v-if="change.kind !== 'subject'"
				class="ext-neowiki-edit-conflict-editor__before"
			>
				<span class="ext-neowiki-edit-conflict-editor__before-label">
					{{ $i18n( 'neowiki-subject-editor-edit-conflict-before' ).text() }}
				</span>
				<div class="ext-neowiki-edit-conflict-editor__before-value">
					<EditConflictValueDisplay
						:change="change"
						side="base"
						:property="propertyOf( change )"
					/>
				</div>
			</div>
		</fieldset>
	</div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { CdxIcon } from '@wikimedia/codex';
import { cdxIconCheck } from '@wikimedia/codex-icons';
import EditConflictValueDisplay from '@/components/SubjectEditor/EditConflictValueDisplay.vue';
import { holdsLists, takesSeveralValues, type Change, type Choice, type EditConflict, type StatementChange } from '@/domain/EditConflict';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { propertyDisplayName } from '@/presentation/propertyDisplayName';

type NameOf = ( subjectId: string ) => string;
type PropertyDefinitionOf = ( subjectId: string, propertyName: string ) => PropertyDefinition | undefined;

const props = defineProps<{
	conflict: EditConflict;
	subjectName: string;
	choices: ReadonlyMap<string, Choice>;
	/** The name of the Host Subject or one of its Dependent Subjects. */
	nameOf: NameOf;
	propertyDefinitionOf: PropertyDefinitionOf;
}>();

const emit = defineEmits<{
	choose: [ key: string, choice: Choice ];
}>();

const title = ref<HTMLElement | null>( null );

// The list takes the place of the form the focus was in.
onMounted( () => title.value?.focus() );

// A value can hold links, and following one would leave the editor and lose the edit.
function chooseInsteadOfFollowingALink( event: MouseEvent, key: string, choice: Choice ): void {
	if ( event.target instanceof Element && event.target.closest( 'a' ) !== null ) {
		event.preventDefault();
		emit( 'choose', key, choice );
	}
}

function propertyOf( change: Change ): PropertyDefinition | undefined {
	return change.kind === 'statement' ? props.propertyDefinitionOf( change.subjectId, change.propertyName ) : undefined;
}

function fieldName( change: Change ): string {
	if ( change.kind === 'subject' ) {
		return props.nameOf( change.subjectId );
	}

	const field = change.kind === 'label' ?
		mw.msg( 'neowiki-subject-editor-label' ) :
		propertyDisplayNameOf( change );

	return change.subjectId === props.conflict.hostId ? field : `${ props.nameOf( change.subjectId ) } · ${ field }`;
}

function propertyDisplayNameOf( change: StatementChange ): string {
	const property = propertyOf( change );
	return property === undefined ? change.propertyName : propertyDisplayName( property );
}

function optionsFor( change: Change ): { choice: Choice; caption: string }[] {
	const options: { choice: Choice; caption: string }[] = [
		{ choice: 'theirs', caption: mw.msg( 'neowiki-subject-editor-edit-conflict-theirs' ) },
		{ choice: 'yours', caption: mw.msg( 'neowiki-subject-editor-edit-conflict-yours' ) }
	];

	if ( holdsLists( change ) && takesSeveralValues( propertyOf( change ) ) ) {
		options.push( { choice: 'both', caption: mw.msg( 'neowiki-subject-editor-edit-conflict-both-lists' ) } );
	}

	return options;
}

</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';
@import ( reference ) '@/assets/mixins.less';

.ext-neowiki-edit-conflict-editor {
	display: flex;
	flex-direction: column;
	gap: @spacing-150;

	&__intro {
		display: flex;
		flex-direction: column;
		gap: @spacing-25;

		p {
			margin: 0;
		}
	}

	/* Sized like a pane's Subject name, which it stands in for. */
	&__title {
		margin: 0;
		font-size: @font-size-medium;
	}

	&__field {
		margin: 0;
		padding: 0;
		border: 0;
		min-width: 0;
		display: flex;
		flex-direction: column;
		gap: @spacing-50;
	}

	&__legend {
		padding: 0 0 @spacing-50;
		font-weight: @font-weight-bold;
	}

	&__options {
		display: grid;
		grid-template-columns: repeat( 2, minmax( 0, 1fr ) );
		gap: @spacing-50;
	}

	&__option {
		position: relative;
		display: flex;
		justify-content: space-between;
		align-items: flex-start;
		gap: @spacing-50;
		padding: @spacing-50 @spacing-75;
		border: @border-width-base solid @border-color-base;
		border-radius: @border-radius-base;
		background-color: @background-color-base;
		cursor: pointer;
		overflow-wrap: anywhere;
		transition-property: @transition-property-base;
		transition-duration: @transition-duration-base;

		&:hover {
			border-color: @border-color-input-binary--hover;
			background-color: @background-color-button-quiet--hover;
		}

		&:active {
			border-color: @border-color-input-binary--active;
			background-color: @background-color-button-quiet--active;
		}

		&--selected,
		&--selected:hover,
		&--selected:active {
			border-color: @border-color-progressive;
			background-color: @background-color-progressive-subtle;
			box-shadow: @box-shadow-inset-small @border-color-progressive;
		}

		&:has( :focus-visible ) {
			border-color: @border-color-progressive--focus;
			box-shadow: @box-shadow-inset-small @box-shadow-color-progressive--focus;
		}

		&--wide {
			grid-column: 1 / -1;
		}
	}

	&__radio {
		.ext-neowiki-visually-hidden();
	}

	&__option-content {
		display: flex;
		flex-direction: column;
		min-width: 0;
	}

	&__side {
		color: @color-subtle;
	}

	&__check {
		flex: none;
		color: @border-color-progressive;
	}

	/* The value keeps the layout of its type's display, a list one per line, beside the label. */
	&__before {
		display: flex;
		gap: @spacing-25;
		color: @color-subtle;
	}

	&__before-label {
		flex: none;
	}

	&__before-value {
		min-width: 0;
		overflow-wrap: anywhere;
	}
}
</style>
