<?php declare(strict_types = 1);

namespace App\Database\Repository;

use App\Database\Entity\Payment;
use App\Database\Entity\User;

final class PaymentRepository extends ARepository
{

	public function getUnfinishedPayment(User $user): ?Payment
	{
		// logic
	}

}
