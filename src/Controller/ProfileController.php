<?php

namespace App\Controller;

use App\Repository\CourseRepository;
use App\Security\User;
use App\Service\BillingClient;
use App\Service\ConverterService;
use DateTime;
use DateTimeInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

/**
 * @Route("/profile")
 * @IsGranted("ROLE_USER")
 */
class ProfileController extends AbstractController
{
    private const TRANSACTION_TYPE_EN_RU = [
        'payment' => 'Оплата',
        'deposit' => 'Зачисление',
    ];

    private BillingClient $billingClient;
    private Security $security;

    public function __construct(BillingClient $billingClient, Security $security)
    {
        $this->billingClient = $billingClient;
        $this->security = $security;
    }

    /**
     * @Route("/", name="app_profile_show", methods={"GET"})
     */
    public function show(): Response
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $userDto = $this->billingClient->getCurrent($user->getApiToken());

        return $this->render('profile/show.html.twig', [
            'user' => $userDto,
        ]);
    }

    /**
     * @Route("/transactions", name="app_transactions_index", methods={"GET"})
     */
    public function getTransactions(CourseRepository $courseRepository): Response
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $transactions = $this->billingClient->getTransactions($user->getApiToken());

        foreach ($transactions as &$transaction) {
            $transaction['created_at'] = DateTime::createFromFormat(
                DateTimeInterface::ATOM,
                $transaction['created_at']
            );

            if ($transaction['type'] === 'payment') {
                if (isset($transaction['expires_at'])) {
                    $transaction['expires_at'] = ConverterService::reformatDateTime($transaction['expires_at']);
                }

                $transaction['course'] = $courseRepository->findOneBy([
                    'code' => $transaction['course_code']
                ]);
            }

            $transaction['type'] = self::TRANSACTION_TYPE_EN_RU[$transaction['type']];
        }

        usort($transactions, static function ($a, $b) {
            return $a['created_at'] <=> $b['created_at'];
        });

        return $this->render('profile/transactions_index.html.twig', [
            'transactions' => $transactions,
            'datetimeFormat' => ConverterService::SIMPLE_DATETIME_FORMAT,
        ]);
    }
}
