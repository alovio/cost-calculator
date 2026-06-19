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
			'construction-estimate' => [
				'title'       => __( 'Construction Cost Calculator', 'alovio-calculator' ),
				'description' => __( 'Per-square-meter build estimate by project type, floors and permits.', 'alovio-calculator' ),
				'config'      => [
					'schemaVersion' => 1,
					'fields'        => [
						[
							'id'            => 'area',
							'type'          => 'slider',
							'label'         => __( 'Floor area (m²)', 'alovio-calculator' ),
							'min'           => 50,
							'max'           => 1000,
							'step'          => 10,
							'default'       => 120,
							'showInSummary' => true,
						],
						[
							'id'            => 'project',
							'type'          => 'radio',
							'label'         => __( 'Project type', 'alovio-calculator' ),
							'showInSummary' => true,
							'options'       => [
								[
									'value' => 'opt_new',
									'label' => __( 'New build', 'alovio-calculator' ),
									'price' => 1200,
								],
								[
									'value' => 'opt_reno',
									'label' => __( 'Renovation', 'alovio-calculator' ),
									'price' => 650,
								],
								[
									'value' => 'opt_ext',
									'label' => __( 'Extension', 'alovio-calculator' ),
									'price' => 900,
								],
							],
						],
						[
							'id'            => 'floors',
							'type'          => 'quantity',
							'label'         => __( 'Floors', 'alovio-calculator' ),
							'min'           => 1,
							'max'           => 4,
							'default'       => 1,
							'showInSummary' => true,
						],
						[
							'id'            => 'permit',
							'type'          => 'toggle',
							'label'         => __( 'Permit handling', 'alovio-calculator' ),
							'price'         => 2500,
							'showInSummary' => true,
						],
						[
							'id'              => 'permit_note',
							'type'            => 'heading',
							'label'           => __( 'Permit handling includes drawings and council submission.', 'alovio-calculator' ),
							'conditions'      => [
								[
									'field'    => 'permit',
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
							'expression'    => 'round({area} * {project} * {floors} + {permit}, 0)',
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
			'solar-quote' => [
				'title'       => __( 'Solar Panel Quote Calculator', 'alovio-calculator' ),
				'description' => __( 'System size, panel tier, battery and roof type — instant install quote.', 'alovio-calculator' ),
				'config'      => [
					'schemaVersion' => 1,
					'fields'        => [
						[
							'id'            => 'size',
							'type'          => 'slider',
							'label'         => __( 'System size (kW)', 'alovio-calculator' ),
							'min'           => 2,
							'max'           => 20,
							'step'          => 0.5,
							'default'       => 6,
							'showInSummary' => true,
						],
						[
							'id'            => 'panel',
							'type'          => 'radio',
							'label'         => __( 'Panel tier', 'alovio-calculator' ),
							'showInSummary' => true,
							'options'       => [
								[
									'value' => 'opt_std',
									'label' => __( 'Standard', 'alovio-calculator' ),
									'price' => 900,
								],
								[
									'value' => 'opt_prem',
									'label' => __( 'Premium', 'alovio-calculator' ),
									'price' => 1300,
								],
							],
						],
						[
							'id'            => 'roof',
							'type'          => 'radio',
							'label'         => __( 'Roof type', 'alovio-calculator' ),
							'showInSummary' => true,
							'options'       => [
								[
									'value' => 'opt_tile',
									'label' => __( 'Tile', 'alovio-calculator' ),
									'price' => 0,
								],
								[
									'value' => 'opt_metal',
									'label' => __( 'Metal', 'alovio-calculator' ),
									'price' => 300,
								],
								[
									'value' => 'opt_flat',
									'label' => __( 'Flat', 'alovio-calculator' ),
									'price' => 600,
								],
							],
						],
						[
							'id'            => 'battery',
							'type'          => 'toggle',
							'label'         => __( 'Battery storage', 'alovio-calculator' ),
							'price'         => 4500,
							'showInSummary' => true,
						],
						[
							'id'              => 'battery_note',
							'type'            => 'heading',
							'label'           => __( 'Battery storage may qualify for additional local rebates.', 'alovio-calculator' ),
							'conditions'      => [
								[
									'field'    => 'battery',
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
							'label'         => __( 'Estimated install', 'alovio-calculator' ),
							'showInSummary' => true,
							'expression'    => 'round({size} * {panel} + {battery} + {roof}, 0)',
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
			'landscaping-quote' => [
				'title'       => __( 'Landscaping Quote Calculator', 'alovio-calculator' ),
				'description' => __( 'Garden area, service level, trees and irrigation — instant lawn-care quote.', 'alovio-calculator' ),
				'config'      => [
					'schemaVersion' => 1,
					'fields'        => [
						[
							'id'            => 'area',
							'type'          => 'slider',
							'label'         => __( 'Garden area (m²)', 'alovio-calculator' ),
							'min'           => 20,
							'max'           => 2000,
							'step'          => 10,
							'default'       => 200,
							'showInSummary' => true,
						],
						[
							'id'            => 'service',
							'type'          => 'radio',
							'label'         => __( 'Service level', 'alovio-calculator' ),
							'showInSummary' => true,
							'options'       => [
								[
									'value' => 'opt_mow',
									'label' => __( 'Mowing only', 'alovio-calculator' ),
									'price' => 0.5,
								],
								[
									'value' => 'opt_full',
									'label' => __( 'Full maintenance', 'alovio-calculator' ),
									'price' => 1.2,
								],
								[
									'value' => 'opt_design',
									'label' => __( 'Design & planting', 'alovio-calculator' ),
									'price' => 3,
								],
							],
						],
						[
							'id'            => 'trees',
							'type'          => 'quantity',
							'label'         => __( 'Trees to prune', 'alovio-calculator' ),
							'min'           => 0,
							'max'           => 50,
							'default'       => 0,
							'showInSummary' => true,
						],
						[
							'id'            => 'irrigation',
							'type'          => 'toggle',
							'label'         => __( 'Install irrigation', 'alovio-calculator' ),
							'price'         => 1200,
							'showInSummary' => true,
						],
						[
							'id'              => 'irrigation_note',
							'type'            => 'heading',
							'label'           => __( 'Irrigation install includes a timer and a 12-month warranty.', 'alovio-calculator' ),
							'conditions'      => [
								[
									'field'    => 'irrigation',
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
							'label'         => __( 'Estimated quote', 'alovio-calculator' ),
							'showInSummary' => true,
							'expression'    => 'round({area} * {service} + {trees} * 25 + {irrigation}, 0)',
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
			'catering-quote' => [
				'title'       => __( 'Catering Quote Calculator', 'alovio-calculator' ),
				'description' => __( 'Guests, menu tier, drinks and staff — instant catering quote per head.', 'alovio-calculator' ),
				'config'      => [
					'schemaVersion' => 1,
					'fields'        => [
						[
							'id'            => 'guests',
							'type'          => 'slider',
							'label'         => __( 'Guests', 'alovio-calculator' ),
							'min'           => 10,
							'max'           => 500,
							'step'          => 5,
							'default'       => 50,
							'showInSummary' => true,
						],
						[
							'id'            => 'menu',
							'type'          => 'radio',
							'label'         => __( 'Menu', 'alovio-calculator' ),
							'showInSummary' => true,
							'options'       => [
								[
									'value' => 'opt_buffet',
									'label' => __( 'Buffet', 'alovio-calculator' ),
									'price' => 25,
								],
								[
									'value' => 'opt_plated',
									'label' => __( 'Plated', 'alovio-calculator' ),
									'price' => 45,
								],
								[
									'value' => 'opt_prem',
									'label' => __( 'Premium', 'alovio-calculator' ),
									'price' => 75,
								],
							],
						],
						[
							'id'            => 'drinks',
							'type'          => 'toggle',
							'label'         => __( 'Open bar package', 'alovio-calculator' ),
							'price'         => 800,
							'showInSummary' => true,
						],
						[
							'id'            => 'staff',
							'type'          => 'quantity',
							'label'         => __( 'Serving staff', 'alovio-calculator' ),
							'min'           => 0,
							'max'           => 20,
							'default'       => 0,
							'showInSummary' => true,
						],
						[
							'id'              => 'drinks_note',
							'type'            => 'heading',
							'label'           => __( 'The open bar package covers a 4-hour service window.', 'alovio-calculator' ),
							'conditions'      => [
								[
									'field'    => 'drinks',
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
							'label'         => __( 'Estimated quote', 'alovio-calculator' ),
							'showInSummary' => true,
							'expression'    => 'round({guests} * {menu} + {drinks} + {staff} * 150, 0)',
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
			'flooring-cost' => [
				'title'       => __( 'Flooring Cost Calculator', 'alovio-calculator' ),
				'description' => __( 'Area, material, underlay and removal — instant flooring or tiling cost.', 'alovio-calculator' ),
				'config'      => [
					'schemaVersion' => 1,
					'fields'        => [
						[
							'id'            => 'area',
							'type'          => 'slider',
							'label'         => __( 'Area (m²)', 'alovio-calculator' ),
							'min'           => 5,
							'max'           => 300,
							'step'          => 1,
							'default'       => 30,
							'showInSummary' => true,
						],
						[
							'id'            => 'material',
							'type'          => 'radio',
							'label'         => __( 'Material', 'alovio-calculator' ),
							'showInSummary' => true,
							'options'       => [
								[
									'value' => 'opt_lam',
									'label' => __( 'Laminate', 'alovio-calculator' ),
									'price' => 22,
								],
								[
									'value' => 'opt_hard',
									'label' => __( 'Hardwood', 'alovio-calculator' ),
									'price' => 60,
								],
								[
									'value' => 'opt_tile',
									'label' => __( 'Tile', 'alovio-calculator' ),
									'price' => 40,
								],
							],
						],
						[
							'id'            => 'underlay',
							'type'          => 'toggle',
							'label'         => __( 'Premium underlay', 'alovio-calculator' ),
							'price'         => 200,
							'showInSummary' => true,
						],
						[
							'id'            => 'removal',
							'type'          => 'toggle',
							'label'         => __( 'Remove old floor', 'alovio-calculator' ),
							'price'         => 300,
							'showInSummary' => true,
						],
						[
							'id'              => 'removal_note',
							'type'            => 'heading',
							'label'           => __( 'Removal includes haul-away of the old floor and surface prep.', 'alovio-calculator' ),
							'conditions'      => [
								[
									'field'    => 'removal',
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
							'expression'    => 'round({area} * {material} + {underlay} + {removal}, 0)',
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
