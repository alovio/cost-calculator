/** Inline SVG type icons for the studio palette + settings panel (spec §2.5). */
const P = {
	viewBox: '0 0 24 24',
	width: 16,
	height: 16,
	fill: 'none',
	stroke: 'currentColor',
	strokeWidth: 1.8,
	strokeLinecap: 'round',
	strokeLinejoin: 'round',
	'aria-hidden': true,
};

export const ICONS = {
	number: <svg { ...P }><path d="M4 9h16M4 15h16M10 4 8 20M16 4l-2 16" /></svg>,
	slider: <svg { ...P }><path d="M3 12h18" /><circle cx="14" cy="12" r="3.2" /></svg>,
	quantity: <svg { ...P }><rect x="3" y="8" width="18" height="8" rx="2" /><path d="M6.5 12h3M15 10.5v3M13.5 12h3" /></svg>,
	text: <svg { ...P }><path d="M4 6h16M4 12h16M4 18h9" /></svg>,
	textarea: <svg { ...P }><rect x="3" y="5" width="18" height="14" rx="2" /><path d="M7 10h10M7 14h6" /></svg>,
	date: <svg { ...P }><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M3 10h18M8 3v4M16 3v4" /></svg>,
	email: <svg { ...P }><rect x="3" y="5" width="18" height="14" rx="2" /><path d="m3 7 9 6 9-6" /></svg>,
	phone: <svg { ...P }><path d="M6 3h4l2 5-3 2a12 12 0 0 0 5 5l2-3 5 2v4a2 2 0 0 1-2 2A17 17 0 0 1 4 5a2 2 0 0 1 2-2Z" /></svg>,
	url: <svg { ...P }><path d="M10 14a4 4 0 0 1 0-6l2-2a4 4 0 0 1 6 6l-1 1M14 10a4 4 0 0 1 0 6l-2 2a4 4 0 0 1-6-6l1-1" /></svg>,
	select: <svg { ...P }><rect x="3" y="6" width="18" height="12" rx="2" /><path d="m13.5 11 2.5 2.5L18.5 11" /></svg>,
	radio: <svg { ...P }><circle cx="12" cy="12" r="8" /><circle cx="12" cy="12" r="3" fill="currentColor" /></svg>,
	checkbox_group: <svg { ...P }><rect x="3" y="3" width="8" height="8" rx="2" /><path d="m5 7 1.5 1.5L9.5 5" /><rect x="13" y="13" width="8" height="8" rx="2" /></svg>,
	toggle: <svg { ...P }><rect x="3" y="8" width="18" height="8" rx="4" /><circle cx="15.5" cy="12" r="2.4" fill="currentColor" /></svg>,
	heading: <svg { ...P }><path d="M6 4v16M18 4v16M6 12h12" /></svg>,
	html: <svg { ...P }><path d="m9 8-4 4 4 4M15 8l4 4-4 4" /></svg>,
	step: <svg { ...P }><path d="M6 21V4h10l-2 3.5 2 3.5H6" /></svg>,
	formula: <svg { ...P }><path d="M18 5H8l5 7-5 7h10" /></svg>,
	repeater: <svg { ...P }><rect x="3" y="4" width="18" height="6" rx="2" /><rect x="3" y="14" width="12" height="6" rx="2" /><path d="M19 15v4M17 17h4" /></svg>,
};

/** Brand flame — the white mark on the flame-gradient square (shared with Pro's console). */
export const FLAME = (
	<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true">
		<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.07-2.14-.22-4.05 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.15.43-2.29 1-3a2.5 2.5 0 0 0 2.5 2.5z" />
	</svg>
);
