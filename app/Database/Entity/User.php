<?php declare(strict_types = 1);

namespace App\Database\Entity;

use App\Database\Entity\Attribute\TId;
use Collectable;

/**
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks
 */
class User extends AEntity
{

	use TId;

	/** @ORM\Column(type="string", length=255, nullable=false) */
	private string $email;

	/** @ORM\OneToMany(targetEntity="Payment", mappedBy="user") */
	private Collectable $payments;

	public function __construct(string $email)
	{
		$this->email = $email;
		$this->payments = new ArrayCollection();
	}

	public function getEmail(): string
	{
		return $this->email;
	}

	public function setEmail(string $email): void
	{
		$this->email = $email;
	}

	public function getPayments(): Collectable
	{
		return $this->payments;
	}

}
