<?php declare(strict_types = 1);

namespace App\Console;

use App\Communication\Message\PaymentFailed;
use App\Communication\Message\PaymentSucceeded;
use App\Communication\Message\WholePaymentCanceled;
use App\Communication\MessageSender;
use App\Database\Entity\Payment;
use App\Database\Entity\PaymentAttempt;
use App\Database\Entity\User;
use App\Database\EntityManager;
use App\Database\Repository\PaymentRepository;
use App\Database\Repository\UserRepository;
use App\Logging\Logger;
use App\Payment\Client;

final class ScheduledPaymentCommand
{

	private const MAX_PAYMENT_ATTEMPTS = 5;

	private UserRepository $userRepository;

	private PaymentRepository $paymentRepository;

	private EntityManager $entityManager;

	private Logger $logger;

	private MessageSender $messageSender;

	private Client $paymentClient;

	public function __construct(
		UserRepository $userRepository,
		PaymentRepository $paymentRepository,
		EntityManager $entityManager,
		Logger $logger,
		MessageSender $messageSender,
		Client $paymentClient
	)
	{
		$this->userRepository = $userRepository;
		$this->paymentRepository = $paymentRepository;
		$this->entityManager = $entityManager;
		$this->logger = $logger;
		$this->messageSender = $messageSender;
		$this->paymentClient = $paymentClient;
	}

	/**
	 * --------------- OBECNÁ ÚVAHA O CHYBÁCH ---------------
	 *
	 * 1. Pokud nastane kritická chyba (chyba db, sítě) tak se musí:
	 *       - chyba zalogovat do Sentry
	 *       - cron zastavit
	 *       - zajistit že data budou vždy konzistentní a to buď:
	 *             - databázovou transakcí
	 *                   - ta ale není vždy možná
	 *                   - v případě cronu který by furt padal taky může zbytečně čerpat autoincrement idček databáze
	 *             - nějakou jinou kontrolou (stav unfinished/finished, datetime, boolean..)
	 *       - pokud bude nějaký takto nevalidní záznam v db, měl by se přeskakovat aby neblokoval další platby
	 *
	 * 2. Pokud nastane nekritická chyba typu "nedostatek pěnez na účtu" tak:
	 *        - by se měl upozornit uživatel
	 *        - umožnit více pokusů platby než to bude řešit zákaznický centrum
	 *
	 * --------------- DATA BY TEDY MOHLA VYPADAT NAPŘÍKLAD TAKTO ---------------
	 *
	 * - Uživatel Jirka
	 *     - Payment 08.02.2020
	 *         - Attempt: 08.02.2020 09:37.4456, Chyba, nedostatek peněz na účtu
	 *         - Attempt: 08.02.2020 12:37.3395, Chyba, nedostatek peněz na účtu
	 *         - Attempt: 08.02.2020 18:37.7488, OK, zaplaceno
	 *     - Payment 08.03.2020
	 *         - Attempt: 08.03.2020 09:37.7488 OK, zaplaceno
	 *     - Payment 08.04.2020
	 *         - Attempt: NEVALIDNÍ STAV V DB -> přeskakovat a upozonit přes Sentry aby se šlo manuálně řešit
	 *
	 * --------------- ÚVAHA O VÝKONU ---------------
	 *
	 * Může se stát že bude potřeba naráz řešit hodně plateb
	 *     - data bych tahal po dávkách (třeba 1000 plateb naráz) nebo partialem
	 *     - možná bych místo entity napsal custom sql
	 *     - může se stát že by se to zpracovávalo moc dlouho a přeteklo až do dalšího dne, nevím jestli řešit nebo ne
	 *     - může se stát že bude potřeba více plateb zároveň - kdyby se třeba script nezapl celý dva měsíce
	 *
	 * Z toho důvodu bych se asi i zamyslel zda to dělat jako CLI command ale jestli to spíš neudělat jako consumer,
	 * který by konzumoval frontu s idčky uživatelů. Jednuduše by to pak šlo škálovat.
	 *
	 *
	 * --------------- OBECNÝ POSTUP ŘEŠENÍ ---------------
	 *
	 * 1. Vytáhneme potřebné uživatele v dávkách
	 * 2. Vytvoříme záznam o potřebné platbě pokud už neexistuje, bude obsahovat vazbu na uživatele, konečný stav, cenu,
	 *    případně měnu a kurz, DPH, fakturu a podobně
	 * 3. Vytvoříme záznam o pokusu o platbu ve STAVU UNFINISHED
	 * 4. Na základě záznamu o potřebné platbě provedeme platbu přes platební bránu
	 * 5. Záznam o pokusu se přepne ze stavu unfinished do finished a zároveň se platba označí jako zpracovaná
	 * 6. Pokud failne bod 4. nebo 5. tak má platba pořád unfinished, problém zaloguje a půjde se řešit ručně aby
	 *    nedošlo k dvojímu odečtení platby. Pokud se cron spustí znovu, pak se takto problémový stav přeskočí a znovu zaloguje.
	 *
	 */
	public function execute(): void
	{
		try {
			// tady by se mělo načítat po dávkách ideálně přes nějaký doctrine lazy iterator, ale musí se dát pozor na
			// to aby se data uvolňovala z paměti
			$users = $this->userRepository->getUsersWithRequiredPayment();

			foreach ($users as $user) {
				$payment = $this->getUnfinishedPaymentOrCreateNew($user);

				if ($payment->hasUnfinishedPaymentAttempt()){
					$this->logger->log(sprintf(
						'Payment client failed during request or database failed during write. Manual check needed. (paymentId = %d)',
						$payment->getId()
					));

					continue;
				}

				if (count($payment->getAttempts()) >= self::MAX_PAYMENT_ATTEMPTS) {
					// ideálně by se měl použít nějaký enum ale pro jednoduchost nechám takto
					$payment->setState('canceled');
					$this->entityManager->flush();

					$this->messageSender->sentMessageToUser(new WholePaymentCanceled($user));
					$this->messageSender->sentMessageToCustomerService(new WholePaymentCanceled($user));
					continue;
				}

				$paymentAttempt = $this->createAndSavePaymentAttempt($payment);

				// předpoklad že exception se hodí jen když jde o fatal chybu - např chyba sítě -> nechám vyletět a sentry
				// pokud jde třeba o nedostatek peněz tak to exception nehodí ale hodí to responseStatus !== Ok
				$response = $this->paymentClient->pay($payment);

				$paymentAttempt->setResponse($response->getResponse());

				// unfinished -> finished
				if ($response->isSuccessType()) {
					$paymentAttempt->setState('ok');
					$payment->setState('processed');
					$this->entityManager->flush();

					$this->messageSender->sentMessageToUser(new PaymentSucceeded($user));
				} else {
					$paymentAttempt->setState('failed');
					$this->entityManager->flush();

					$this->messageSender->sentMessageToUser(new PaymentFailed($user));
				}
			}
		} catch (\Throwable $t) {
			$this->logger->logException($t);

			throw $t;
		}
	}

	private function getUnfinishedPaymentOrCreateNew(User $user): Payment
	{
		$payment = $this->paymentRepository->getUnfinishedPayment($user);

		if ($payment === null) {
			$payment = new Payment($user, 'created');
			$this->entityManager->persist($payment);
			$this->entityManager->flush();
		}

		return $payment;
	}

	private function createAndSavePaymentAttempt(Payment $payment): PaymentAttempt
	{
		$paymentAttempt = new PaymentAttempt(
			$payment,
			'unfinished'
		);

		$this->entityManager->persist($payment);
		$this->entityManager->flush();

		return $paymentAttempt;
	}

}
