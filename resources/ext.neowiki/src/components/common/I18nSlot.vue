<template>
	<span>
		<template v-for="( item, index ) in items">
			<span
				v-if="typeof item === 'string'"
				:key="index + '-text'"
				:class="textClass"
			>
				{{ item }}
			</span>
			<slot
				v-else
				:key="index + '-slot'"
			/>
		</template>
	</span>
</template>

<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
	messageKey: string;
	textClass?: string;
}>();

const items = computed( () => {
	const placeholder = '<SLOT>';
	const parts = mw.message( props.messageKey, placeholder ).text().split( placeholder );
	const result: ( string | { isSlot: true } )[] = [];

	parts.forEach( ( part, index ) => {
		if ( part ) {
			result.push( part );
		}

		if ( index < parts.length - 1 ) {
			result.push( { isSlot: true } );
		}
	} );

	return result;
} );
</script>
