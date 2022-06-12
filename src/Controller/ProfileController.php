<?php

namespace App\Controller;

use App\Security\User;
use App\Service\BillingClient;
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
}
