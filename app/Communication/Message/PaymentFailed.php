<?php declare(strict_types = 1);

namespace App\Communication\Message;

use App\Database\Entity\User;

final class PaymentFailed extends AMessage
{

	public function __construct(User $user)
	{
	}

}
