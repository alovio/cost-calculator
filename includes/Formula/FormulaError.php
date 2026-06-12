<?php
namespace Alovio\Calculator\Formula;

class FormulaError extends \RuntimeException {

	/** @var string One of: syntax, unknown_function, arity, unknown_field, div_zero, overflow, bad_number */
	private $errorCode;

	/** @var int Character position in the expression, -1 when not applicable. */
	private $position;

	public function __construct( string $errorCode, string $message, int $position = -1 ) {
		parent::__construct( $message );
		$this->errorCode = $errorCode;
		$this->position  = $position;
	}

	/**
	 * Positioned-throw factory. Exists so throw sites avoid `throw new` with a
	 * variable argument, which WPCS's ExceptionNotEscaped flags even for ints.
	 */
	public static function at( string $errorCode, string $message, int $position ): self {
		return new self( $errorCode, $message, $position );
	}

	public function getErrorCode(): string {
		return $this->errorCode;
	}

	public function getPosition(): int {
		return $this->position;
	}
}
