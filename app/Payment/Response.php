<?php declare(strict_types = 1);

namespace App\Payment;

final class Response
{

	private string $response;

	private string $state;

	public function __construct(string $response, string $state)
	{
		$this->response = $response;
		$this->state = $state;
	}

	public function getResponse(): string
	{
		return $this->response;
	}

	public function isSuccessType(): bool
	{
		// another logic
	}

}
