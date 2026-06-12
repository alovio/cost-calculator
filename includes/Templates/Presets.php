<?php
namespace Alovio\Calculator\Templates;

/**
 * Six vertical starter templates (spec §11). Option values are hand-authored
 * unique `opt_` slugs (valid per FieldSchema). Convention: the grand total is
 * the LAST formula field in field order.
 */
final class Presets {

	/** @return array<string, array{title: string, description: string, config: array}> */
	public static function all(): array {
		return [
			'cleaning-price'  => [
				'title'       => __( 'Cleaning Price Calculator', 'alovio-calculator' ),
				'description' => __( 'Per-square-meter cleaning quote with service level and extras.', 'alovio-calculator' ),
				'config'      => [
					'schemaVersion' => 1,
					'fields'        => [
						[
							'id'            => 'area',
							'type'          => 'slider',
							'label'         => __( 'Area (m²)', 'alovio-calculator' ),
							'min'           => 10,
							'max'           => 500,
							'step'          => 5,
							'default'       => 50,
							'showInSummary' => true,
						],
						[
							'id'            => 'service',
							'type'          => 'radio',
							'label'         => __( 'Service level', 'alovio-calculator' ),
							'showInSummary' => true,
							'options'       => [
								[
									'value' => 'opt_std',
									'label' => __( 'Standard', 'alovio-calculator' ),
									'price' => 2.5,
								],
								[
									'value' => 'opt_deep',
									'label' => __( 'Deep clean', 'alovio-calculator' ),
									'price' => 4,
								],
							],
						],
						[
							'id'            => 'windows',
							'type'          => 'quantity',
							'label'         => __( 'Windows', 'alovio-calculator' ),
							'min'           => 0,
							'max'           => 50,
							'default'       => 0,
							'showInSummary' => true,
						],
						[
							'id'            => 'express',
							'type'          => 'toggle',
							'label'         => __( 'Express (24h)', 'alovio-calculator' ),
							'price'         => 30,
							'showInSummary' => true,
						],
						[
							'id'              => 'express_note',
							'type'            => 'heading',
							'label'           => __( 'Express slots are limited on weekends.', 'alovio-calculator' ),
							'conditions'      => [
								[
									'field'    => 'express',
									'operator' => 'is',
									'value'    => '1',
								],
							],
							'conditionMatch'  => 'all',
							'conditionAction' => 'show',
						],
						[
							'id'            => 'total',
							'type'          => 'formula',
							'label'         => __( 'Estimated price', 'alovio-calculator' ),
							'showInSummary' => true,
							'expression'    => '{area} * {service} + {windows} * 6 + {express}',
						],
					],
					'settings'      => [
						'quoteForm' => [
							'enabled' => true,
							'fields'  => [ 'name', 'email', 'phone' ],
						],
					],
				],
			],
			'moving-cost'     => [
				'title'       => __( 'Moving Cost Calculator', 'alovio-calculator' ),
				'description' => __( 'Rooms, distance and extras — instant moving estimate.', 'alovio-calculator' ),
				'config'      => [
					'schemaVersion' => 1,
					'fields'        => [
						[
							'id'            => 'rooms',
							'type'          => 'quantity',
							'label'         => __( 'Rooms', 'alovio-calculator' ),
							'min'           => 1,
							'max'           => 12,
							'default'       => 2,
							'showInSummary' => true,
						],
						[
							'id'            => 'distance',
							'type'          => 'number',
							'label'         => __( 'Distance (km)', 'alovio-calculator' ),
							'min'           => 1,
							'max'           => 2000,
							'default'       => 10,
							'showInSummary' => true,
						],
						[
							'id'      => 'floor',
							'type'    => 'select',
							'label'   => __( 'Floor access', 'alovio-calculator' ),
							'options' => [
								[
									'value' => 'opt_ground',
									'label' => __( 'Ground floor', 'alovio-calculator' ),
									'price' => 0,
								],
								[
									'value' => 'opt_elev',
									'label' => __( 'Upper floor with elevator', 'alovio-calculator' ),
									'price' => 15,
								],
								[
									'value' => 'opt_stairs',
									'label' => __( 'Upper floor, stairs only', 'alovio-calculator' ),
									'price' => 40,
								],
							],
						],
						[
							'id'            => 'packing',
							'type'          => 'toggle',
							'label'         => __( 'Packing service', 'alovio-calculator' ),
							'price'         => 60,
							'showInSummary' => true,
						],
						[
							'id'              => 'fragile_note',
							'type'            => 'heading',
							'label'           => __( 'Our team brings packing material for fragile items.', 'alovio-calculator' ),
							'conditions'      => [
								[
									'field'    => 'packing',
									'operator' => 'is',
									'value'    => '1',
								],
							],
							'conditionMatch'  => 'all',
							'conditionAction' => 'show',
						],
						[
							'id'            => 'total',
							'type'          => 'formula',
							'label'         => __( 'Estimated cost', 'alovio-calculator' ),
							'showInSummary' => true,
							'expression'    => '{rooms} * 50 + {distance} * 1.2 + {floor} + {packing}',
						],
					],
					'settings'      => [
						'quoteForm' => [
							'enabled' => true,
							'fields'  => [ 'name', 'email', 'phone' ],
						],
					],
				],
			],
			'print-quote'     => [
				'title'       => __( 'Print Quote Calculator', 'alovio-calculator' ),
				'description' => __( 'Flyers, posters and stickers with quantity pricing and a minimum order.', 'alovio-calculator' ),
				'config'      => [
					'schemaVersion' => 1,
					'fields'        => [
						[
							'id'            => 'product',
							'type'          => 'radio',
							'label'         => __( 'Product', 'alovio-calculator' ),
							'showInSummary' => true,
							'options'       => [
								[
									'value' => 'opt_flyer',
									'label' => __( 'Flyer (A5)', 'alovio-calculator' ),
									'price' => 0.15,
								],
								[
									'value' => 'opt_poster',
									'label' => __( 'Poster (A2)', 'alovio-calculator' ),
									'price' => 1.2,
								],
								[
									'value' => 'opt_sticker',
									'label' => __( 'Sticker', 'alovio-calculator' ),
									'price' => 0.4,
								],
							],
						],
						[
							'id'            => 'qty',
							'type'          => 'number',
							'label'         => __( 'Quantity', 'alovio-calculator' ),
							'min'           => 50,
							'max'           => 100000,
							'default'       => 100,
							'showInSummary' => true,
						],
						// Price 1 = boolean flag: referenced ONLY inside if() conditions, never added to the total.
						[
							'id'    => 'double',
							'type'  => 'toggle',
							'label' => __( 'Double-sided', 'alovio-calculator' ),
							'price' => 1,
						],
						[
							'id'            => 'express',
							'type'          => 'toggle',
							'label'         => __( 'Express production', 'alovio-calculator' ),
							'price'         => 25,
							'showInSummary' => true,
						],
						[
							'id'              => 'bulk_note',
							'type'            => 'heading',
							'label'           => __( 'Orders above 500 pieces qualify for bulk shipping.', 'alovio-calculator' ),
							'conditions'      => [
								[
									'field'    => 'qty',
									'operator' => 'gt',
									'value'    => '500',
								],
							],
							'conditionMatch'  => 'all',
							'conditionAction' => 'show',
						],
						[
							'id'            => 'total',
							'type'          => 'formula',
							'label'         => __( 'Quote total', 'alovio-calculator' ),
							'showInSummary' => true,
							'expression'    => 'max({product} * {qty} + if({double} > 0, {qty} * 0.05, 0) + {express}, 25)',
						],
					],
					'settings'      => [
						'quoteForm' => [
							'enabled' => true,
							'fields'  => [ 'name', 'email' ],
						],
					],
				],
			],
			'agency-estimate' => [
				'title'       => __( 'Agency Project Estimate', 'alovio-calculator' ),
				'description' => __( 'Website project estimate by type, pages and add-on services.', 'alovio-calculator' ),
				'config'      => [
					'schemaVersion' => 1,
					'fields'        => [
						[
							'id'            => 'project',
							'type'          => 'radio',
							'label'         => __( 'Project type', 'alovio-calculator' ),
							'showInSummary' => true,
							'options'       => [
								[
									'value' => 'opt_landing',
									'label' => __( 'Landing page', 'alovio-calculator' ),
									'price' => 800,
								],
								[
									'value' => 'opt_corp',
									'label' => __( 'Corporate site', 'alovio-calculator' ),
									'price' => 2000,
								],
								[
									'value' => 'opt_shop',
									'label' => __( 'Online store', 'alovio-calculator' ),
									'price' => 3500,
								],
							],
						],
						[
							'id'            => 'pages',
							'type'          => 'slider',
							'label'         => __( 'Number of pages', 'alovio-calculator' ),
							'min'           => 1,
							'max'           => 50,
							'step'          => 1,
							'default'       => 5,
							'showInSummary' => true,
						],
						[
							'id'    => 'cms',
							'type'  => 'toggle',
							'label' => __( 'CMS setup & training', 'alovio-calculator' ),
							'price' => 400,
						],
						[
							'id'      => 'seo',
							'type'    => 'select',
							'label'   => __( 'SEO package', 'alovio-calculator' ),
							'options' => [
								[
									'value' => 'opt_seo_none',
									'label' => __( 'None', 'alovio-calculator' ),
									'price' => 0,
								],
								[
									'value' => 'opt_seo_basic',
									'label' => __( 'Basic on-page SEO', 'alovio-calculator' ),
									'price' => 350,
								],
								[
									'value' => 'opt_seo_full',
									'label' => __( 'Full SEO setup', 'alovio-calculator' ),
									'price' => 900,
								],
							],
						],
						[
							'id'              => 'care_note',
							'type'            => 'heading',
							'label'           => __( 'SEO packages include a monthly care-plan offer.', 'alovio-calculator' ),
							'conditions'      => [
								[
									'field'    => 'seo',
									'operator' => 'is_not',
									'value'    => 'opt_seo_none',
								],
							],
							'conditionMatch'  => 'all',
							'conditionAction' => 'show',
						],
						[
							'id'            => 'total',
							'type'          => 'formula',
							'label'         => __( 'Estimated budget', 'alovio-calculator' ),
							'showInSummary' => true,
							'expression'    => 'round({project} + {pages} * 90 + {cms} + {seo}, 0)',
						],
					],
					'settings'      => [
						'quoteForm' => [
							'enabled' => true,
							'fields'  => [ 'name', 'email', 'message' ],
						],
					],
				],
			],
			'salon-pricing'   => [
				'title'       => __( 'Salon Pricing Calculator', 'alovio-calculator' ),
				'description' => __( 'Treatments, hair length and add-ons with weekend pricing.', 'alovio-calculator' ),
				'config'      => [
					'schemaVersion' => 1,
					'fields'        => [
						[
							'id'            => 'treatment',
							'type'          => 'radio',
							'label'         => __( 'Treatment', 'alovio-calculator' ),
							'showInSummary' => true,
							'options'       => [
								[
									'value' => 'opt_cut',
									'label' => __( 'Cut & style', 'alovio-calculator' ),
									'price' => 25,
								],
								[
									'value' => 'opt_color',
									'label' => __( 'Full color', 'alovio-calculator' ),
									'price' => 70,
								],
								[
									'value' => 'opt_keratin',
									'label' => __( 'Keratin treatment', 'alovio-calculator' ),
									'price' => 120,
								],
							],
						],
						[
							'id'      => 'length',
							'type'    => 'select',
							'label'   => __( 'Hair length', 'alovio-calculator' ),
							'options' => [
								[
									'value' => 'opt_len_short',
									'label' => __( 'Short', 'alovio-calculator' ),
									'price' => 0,
								],
								[
									'value' => 'opt_len_med',
									'label' => __( 'Medium', 'alovio-calculator' ),
									'price' => 15,
								],
								[
									'value' => 'opt_len_long',
									'label' => __( 'Long', 'alovio-calculator' ),
									'price' => 30,
								],
							],
						],
						[
							'id'            => 'addons',
							'type'          => 'checkbox_group',
							'label'         => __( 'Add-ons', 'alovio-calculator' ),
							'showInSummary' => true,
							'options'       => [
								[
									'value' => 'opt_massage',
									'label' => __( 'Scalp massage', 'alovio-calculator' ),
									'price' => 20,
								],
								[
									'value' => 'opt_mask',
									'label' => __( 'Repair mask', 'alovio-calculator' ),
									'price' => 15,
								],
								[
									'value' => 'opt_styling',
									'label' => __( 'Event styling', 'alovio-calculator' ),
									'price' => 25,
								],
							],
						],
						[
							'id'    => 'weekend',
							'type'  => 'toggle',
							'label' => __( 'Weekend appointment', 'alovio-calculator' ),
							'price' => 10,
						],
						[
							'id'              => 'long_note',
							'type'            => 'heading',
							'label'           => __( 'Long hair may need an extended session — we will confirm by phone.', 'alovio-calculator' ),
							'conditions'      => [
								[
									'field'    => 'length',
									'operator' => 'is',
									'value'    => 'opt_len_long',
								],
							],
							'conditionMatch'  => 'all',
							'conditionAction' => 'show',
						],
						[
							'id'            => 'total',
							'type'          => 'formula',
							'label'         => __( 'Price', 'alovio-calculator' ),
							'showInSummary' => true,
							'expression'    => '{treatment} + {length} + {addons} + {weekend}',
						],
					],
					'settings'      => [
						'quoteForm' => [
							'enabled' => true,
							'fields'  => [ 'name', 'email', 'phone' ],
						],
					],
				],
			],
			'rental-cost'     => [
				'title'       => __( 'Rental Cost Calculator', 'alovio-calculator' ),
				'description' => __( 'Per-day rental pricing with insurance and delivery.', 'alovio-calculator' ),
				'config'      => [
					'schemaVersion' => 1,
					'fields'        => [
						[
							'id'            => 'unit',
							'type'          => 'radio',
							'label'         => __( 'Vehicle', 'alovio-calculator' ),
							'showInSummary' => true,
							'options'       => [
								[
									'value' => 'opt_compact',
									'label' => __( 'Compact', 'alovio-calculator' ),
									'price' => 35,
								],
								[
									'value' => 'opt_suv',
									'label' => __( 'SUV', 'alovio-calculator' ),
									'price' => 60,
								],
								[
									'value' => 'opt_van',
									'label' => __( 'Van', 'alovio-calculator' ),
									'price' => 80,
								],
							],
						],
						[
							'id'            => 'days',
							'type'          => 'quantity',
							'label'         => __( 'Days', 'alovio-calculator' ),
							'min'           => 1,
							'max'           => 90,
							'default'       => 1,
							'showInSummary' => true,
						],
						[
							'id'    => 'insurance',
							'type'  => 'toggle',
							'label' => __( 'Full insurance (per day)', 'alovio-calculator' ),
							'price' => 12,
						],
						[
							'id'            => 'delivery',
							'type'          => 'toggle',
							'label'         => __( 'Delivery to address', 'alovio-calculator' ),
							'price'         => 25,
							'showInSummary' => true,
						],
						[
							'id'              => 'long_note',
							'type'            => 'heading',
							'label'           => __( 'Rentals over 7 days get a free extra driver.', 'alovio-calculator' ),
							'conditions'      => [
								[
									'field'    => 'days',
									'operator' => 'gt',
									'value'    => '7',
								],
							],
							'conditionMatch'  => 'all',
							'conditionAction' => 'show',
						],
						[
							'id'            => 'total',
							'type'          => 'formula',
							'label'         => __( 'Rental total', 'alovio-calculator' ),
							'showInSummary' => true,
							'expression'    => '({unit} + if({insurance} > 0, 12, 0)) * {days} + {delivery}',
						],
					],
					'settings'      => [
						'quoteForm' => [
							'enabled' => true,
							'fields'  => [ 'name', 'email', 'phone' ],
						],
					],
				],
			],
		];
	}
}
