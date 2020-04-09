<?php declare(strict_types = 1);

namespace App\Database\Entity;

use App\Database\Entity\Attribute\TCreatedAt;
use App\Database\Entity\Attribute\TId;
use App\Database\Entity\Attribute\TUpdatedAt;

/**
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks
 */
class PaymentAttempt extends AEntity
{

	use TId;
	use TCreatedAt;
	use TUpdatedAt;

	/** @ORM\ManyToOne(targetEntity="Payment", inversedBy="attempts") */
	private Payment $payment;

	/** @ORM\Column(type="text", nullable=true) */
	private ?string $response;

	/** @ORM\Column(type="string", length=32, nullable=false) */
	private string $state;

	public function __construct(Payment $payment, string $state)
	{
		$this->payment = $payment;
		$this->state = $state;
	}

	public function getPayment(): Payment
	{
		return $this->payment;
	}

	public function setPayment(Payment $payment): void
	{
		$this->payment = $payment;
	}

	public function getResponse(): ?string
	{
		return $this->response;
	}

	public function setResponse(?string $response): void
	{
		$this->response = $response;
	}

	public function getState(): string
	{
		return $this->state;
	}

	public function setState(string $state): void
	{
		$this->state = $state;
	}

}
