<?php declare(strict_types = 1);

namespace App\Database\Entity\Attribute;

use Exception;

trait TId
{

	/**
	 * @ORM\Column(type="integer", nullable=FALSE)
	 * @ORM\Id
	 * @ORM\GeneratedValue
	 */
	private ?int $id = null;

	public function getId(): int
	{
		if ($this->id === null) {
			throw new Exception('Entity does not have id');
		}

		return $this->id;
	}

	public function __clone()
	{
		$this->id = null;
	}

}
