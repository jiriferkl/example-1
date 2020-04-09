<?php declare(strict_types = 1);

namespace App\Logging;

final class Logger
{

	public function log(string $message): void
	{
		// sentry, email...
	}

	public function logException(\Throwable $throwable): void
	{
		// sentry, email...
	}

}
