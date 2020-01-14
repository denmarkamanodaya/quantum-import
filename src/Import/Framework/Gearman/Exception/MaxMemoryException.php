<?php


namespace Import\Framework\Gearman\Exception;

class MaxMemoryException extends \RuntimeException
{
    public $maxAllowed;

    public $current;

    /**
     * MaxMemoryException constructor.
     * @param string $message
     * @param int $maxAllowed
     * @param int $currentUsage
     * @param int $code
     * @param \Exception|null $previous
     */
    public function __construct($message, $maxAllowed, $currentUsage, $code = 0, \Exception $previous = null)
    {
        $this->maxAllowed = $maxAllowed;
        $this->current    = $currentUsage;

        parent::__construct(
            $message,
            $code,
            $previous
        );
    }
}
