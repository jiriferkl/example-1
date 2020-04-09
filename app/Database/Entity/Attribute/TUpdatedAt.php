<?php declare(strict_types = 1);

namespace App\Database\Entity\Attribute;

use DateTime;

trait TUpdatedAt
{

	/** @ORM\Column(type="datetime", nullable=true) */
	protected ?DateTime $updatedAt = null;

	public function getUpdatedAt(): ?DateTime
	{
		return $this->updatedAt;
	}

	/**
	 * @internal
	 * @ORM\PreUpdate
	 */
	public function setUpdatedAt(): void
	{
		$this->updatedAt = new DateTime();
	}

}
