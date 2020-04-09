<?php declare(strict_types = 1);

namespace App\Database\Entity;

use App\Database\Entity\Attribute\TCreatedAt;
use App\Database\Entity\Attribute\TId;
use App\Database\Entity\Attribute\TUpdatedAt;
use Collectable;

/**
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks
 */
class Payment extends AEntity
{

	use TId;
	use TCreatedAt;
	use TUpdatedAt;

	/** @ORM\ManyToOne(targetEntity="User", inversedBy="payments") */
	private User $user;

	/** @ORM\Column(type="string", length=32, nullable=false) */
	private string $state;

	/** @ORM\OneToMany(targetEntity="PaymentAttempt", mappedBy="payment") */
	private Collectable $attempts;

	public function __construct(User $user, string $state)
	{
		$this->user = $user;
		$this->state = $state;
		$this->attempts = new ArrayCollection();
	}

	public function getUser(): User
	{
		return $this->user;
	}

	public function setUser(User $user): void
	{
		$this->user = $user;
	}

	public function getState(): string
	{
		return $this->state;
	}

	public function setState(string $state): void
	{
		$this->state = $state;
	}

	public function getAttempts(): \Countable
	{
		return $this->attempts;
	}

	public function hasUnfinishedPaymentAttempt(): bool
	{
		// logic
	}

}
