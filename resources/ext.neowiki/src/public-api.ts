// Side-effect import: keep the existing bootstrap behaviour (Vue app mount).
// Rollup preserves this because package.json has no sideEffects field, and
// neowiki.ts has top-level statements that qualify as side-effects.
import './neowiki';

// Runtime exports for MW-native extensions via require('ext.neowiki').
export { newStringValue, ValueType } from './domain/Value';
export { NeoWikiServices } from './NeoWikiServices';
export { default as NeoNestedField } from './components/common/NeoNestedField.vue';

// Type exports for TS-based extensions using tsconfig-paths.
export type { StringValue, Value } from './domain/Value';
export type { BasePropertyType, ValueValidationError } from './domain/PropertyType';
export type { PropertyDefinition } from './domain/PropertyDefinition';
