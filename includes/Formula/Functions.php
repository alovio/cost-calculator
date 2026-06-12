<?php
namespace Alovio\Calculator\Formula;

final class Functions {

	/** name => [minArity, maxArity]. Filterable copy is exposed via Formula::functions(). */
	public const SPECS = [
		'if'    => [ 3, 3 ],
		'min'   => [ 2, 8 ],
		'max'   => [ 2, 8 ],
		'round' => [ 1, 2 ],
		'ceil'  => [ 1, 1 ],
		'floor' => [ 1, 1 ],
		'abs'   => [ 1, 1 ],
	];
}
