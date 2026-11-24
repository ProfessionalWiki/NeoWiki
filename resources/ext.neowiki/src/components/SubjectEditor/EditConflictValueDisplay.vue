<template>
	<StatementDisplay
		v-if="shown.statement !== undefined && props.property !== undefined"
		:value="shown.statement"
		:property="props.property"
	/>
	<span v-else>{{ shown.text }}</span>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import StatementDisplay from '@/components/Value/StatementDisplay.vue';
import { bothListsOf, type Change, type Choice, type SubjectChange } from '@/domain/EditConflict';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { valueToJson, type Value } from '@/domain/Value';
import { formatRawValue } from '@/presentation/formatRawValue';

const props = defineProps<{
	change: Change;
	side: Choice | 'base';
	property: PropertyDefinition | undefined;
}>();

const shown = computed( (): { statement?: Value; text: string } => {
	const { change, side } = props;
	const none = mw.msg( 'neowiki-subject-editor-edit-conflict-none' );

	if ( change.kind === 'label' ) {
		return { text: ( side === 'both' ? change.yours : change[ side ] ) ?? none };
	}

	if ( change.kind === 'subject' ) {
		return { text: presence( change, side === 'both' ? 'yours' : side ) };
	}

	const statement = side === 'both' ? bothListsOf( change ) : change[ side ];
	return { statement: statement?.value, text: statement?.value === undefined ? none : formatRawValue( valueToJson( statement.value ) ) };
} );

// A Dependent Subject one side removed and the other changed: the only kind of Subject the list asks about.
function presence( change: SubjectChange, side: 'base' | 'theirs' | 'yours' ): string {
	return mw.msg( change[ side ] === undefined ?
		'neowiki-subject-editor-edit-conflict-removed' :
		'neowiki-subject-editor-edit-conflict-kept' );
}
</script>
