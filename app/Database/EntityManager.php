<?php declare(strict_types = 1);

namespace App\Database;

use App\Database\Entity\AEntity;

final class EntityManager
{

	public function persist(AEntity $entity): void
	{
		// entity handling..
	}

	public function flush(?AEntity $entity = null): void
	{
		// entity handling..
	}

}
