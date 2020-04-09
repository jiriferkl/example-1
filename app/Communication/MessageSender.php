<?php declare(strict_types = 1);

namespace App\Communication;

use App\Communication\Message\AMessage;

final class MessageSender
{

	public function sentMessageToUser(AMessage $message): void
	{
		// rabbitMq, socket..
	}

	public function sentMessageToCustomerService(AMessage $message): void
	{
		// rabbitMq, socket..
	}

}
