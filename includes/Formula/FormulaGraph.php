<?php
namespace Alovio\Calculator\Formula;

final class FormulaGraph {

	/**
	 * @param array<string, string[]> $idToRefs Formula field id => referenced field ids.
	 *                                          Refs that are not formula ids are ignored.
	 * @return array{order: string[], cycles: string[]}
	 */
	public static function order( array $idToRefs ): array {
		$ids      = array_keys( $idToRefs );
		$idSet    = array_flip( $ids );
		$indegree = array_fill_keys( $ids, 0 );
		$edges    = []; // Map from each dependency id to the ids that depend on it.

		foreach ( $idToRefs as $id => $refs ) {
			foreach ( array_unique( $refs ) as $ref ) {
				if ( isset( $idSet[ $ref ] ) && $ref !== $id ) {
					$edges[ $ref ][] = $id;
					++$indegree[ $id ];
				}
				if ( $ref === $id ) { // Self-reference is a cycle of one.
					++$indegree[ $id ];
				}
			}
		}

		$queue = [];
		foreach ( $indegree as $id => $deg ) {
			if ( 0 === $deg ) {
				$queue[] = $id;
			}
		}

		$order = [];
		while ( $queue ) {
			$id      = array_shift( $queue );
			$order[] = $id;
			foreach ( $edges[ $id ] ?? [] as $dependent ) {
				if ( 0 === --$indegree[ $dependent ] ) {
					$queue[] = $dependent;
				}
			}
		}

		$cycles = array_values( array_diff( $ids, $order ) );

		return [
			'order'  => $order,
			'cycles' => $cycles,
		];
	}
}
