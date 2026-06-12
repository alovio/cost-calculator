import apiFetch from '@wordpress/api-fetch';

apiFetch.use( apiFetch.createRootURLMiddleware( window.ALC_BUILDER ? window.ALC_BUILDER.root : '/' ) );
apiFetch.use( apiFetch.createNonceMiddleware( window.ALC_BUILDER ? window.ALC_BUILDER.nonce : '' ) );

export const listCalculators = () => apiFetch( { path: 'alc/v1/calculators' } );
export const createCalculator = ( body ) => apiFetch( { path: 'alc/v1/calculators', method: 'POST', data: body } );
export const getCalculator = ( id ) => apiFetch( { path: `alc/v1/calculators/${ id }` } );
export const saveCalculator = ( id, body ) => apiFetch( { path: `alc/v1/calculators/${ id }`, method: 'PUT', data: body } );
export const deleteCalculator = ( id ) => apiFetch( { path: `alc/v1/calculators/${ id }`, method: 'DELETE' } );
export const listEntries = ( query ) => apiFetch( { path: `alc/v1/entries?${ new URLSearchParams( query ) }` } );
export const updateEntry = ( id, body ) => apiFetch( { path: `alc/v1/entries/${ id }`, method: 'PUT', data: body } );
export const deleteEntry = ( id ) => apiFetch( { path: `alc/v1/entries/${ id }`, method: 'DELETE' } );
export const getSettings = () => apiFetch( { path: 'alc/v1/settings' } );
export const saveSettings = ( body ) => apiFetch( { path: 'alc/v1/settings', method: 'PUT', data: body } );
