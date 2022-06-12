<?php

namespace App\Controller;

use App\Exception\BillingUnavailableException;
use App\Form\RegistrationFormType;
use App\Security\LoginAuthenticator;
use App\Security\SecurityUtils;
use App\Security\User;
use App\Service\BillingClient;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;

class RegistrationController extends AbstractController
{
    private BillingClient $billingClient;
    private Security $security;

    public function __construct(BillingClient $billingClient, Security $security)
    {
        $this->billingClient = $billingClient;
        $this->security = $security;
    }

    /**
     * @Route("/register", name="app_register")
     */
    public function register(
        Request                    $request,
        UserAuthenticatorInterface $userAuthenticator,
        LoginAuthenticator         $loginAuthenticator,
        AuthenticationUtils        $authenticationUtils
    ): Response {

        if ($this->security->getUser()) {
            return $this->redirectToRoute('app_profile_show', [], Response::HTTP_SEE_OTHER);
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $token = $this->billingClient->register([
                    'username' => $form->get('email')->getData(),
                    'password' => $form->get('password')->getData()
                ]);
            } catch (BillingUnavailableException|JsonException $e) {
                return $this->render('registration/register.html.twig', [
                    'registrationForm' => $form->createView(),
                    'error' => SecurityUtils::SERVICE_TEMPORARILY_UNAVAILABLE,
                ]);
            }
            $user->setApiToken($token);

            return $userAuthenticator->authenticateUser(
                $user,
                $loginAuthenticator,
                $request
            );
        }
        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }
}
