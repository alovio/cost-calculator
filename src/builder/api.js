import apiFetch from '@wordpress/api-fetch';

apiFetch.use( apiFetch.createRootURLMiddleware( window.ALOVIO_CALC_BUILDER ? window.ALOVIO_CALC_BUILDER.root : '/' ) );
apiFetch.use( apiFetch.createNonceMiddleware( window.ALOVIO_CALC_BUILDER ? window.ALOVIO_CALC_BUILDER.nonce : '' ) );

export const listCalculators = () => apiFetch( { path: 'alovio-calc/v1/calculators' } );
export const createCalculator = ( body ) => apiFetch( { path: 'alovio-calc/v1/calculators', method: 'POST', data: body } );
export const getCalculator = ( id ) => apiFetch( { path: `alovio-calc/v1/calculators/${ id }` } );
export const saveCalculator = ( id, body ) => apiFetch( { path: `alovio-calc/v1/calculators/${ id }`, method: 'PUT', data: body } );
export const deleteCalculator = ( id ) => apiFetch( { path: `alovio-calc/v1/calculators/${ id }`, method: 'DELETE' } );
export const listEntries = ( query ) => apiFetch( { path: `alovio-calc/v1/entries?${ new URLSearchParams( query ) }` } );
export const updateEntry = ( id, body ) => apiFetch( { path: `alovio-calc/v1/entries/${ id }`, method: 'PUT', data: body } );
export const deleteEntry = ( id ) => apiFetch( { path: `alovio-calc/v1/entries/${ id }`, method: 'DELETE' } );
export const getSettings = () => apiFetch( { path: 'alovio-calc/v1/settings' } );
export const saveSettings = ( body ) => apiFetch( { path: 'alovio-calc/v1/settings', method: 'PUT', data: body } );
export const previewCalculator = ( body ) => apiFetch( { path: 'alovio-calc/v1/preview', method: 'POST', data: body } );
export const renderCalculator = ( body ) => apiFetch( { path: 'alovio-calc/v1/render', method: 'POST', data: body } );
