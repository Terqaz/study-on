<?php

namespace App\Controller;

use App\Dto\CourseDto;
use App\Entity\Course;
use App\Enum\PaymentStatus;
use App\Exception\BillingUnavailableException;
use App\Exception\CourseAlreadyPaidException;
use App\Exception\InsufficientFundsException;
use App\Exception\ResourceNotFoundException;
use App\Form\CourseType;
use App\Repository\CourseRepository;
use App\Security\User;
use App\Service\BillingClient;
use App\Service\ConverterService;
use JsonException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

/**
 * @Route("/courses")
 */
class CourseController extends AbstractController
{
    private BillingClient $billingClient;
    private Security $security;

    public function __construct(BillingClient $billingClient, Security $security)
    {
        $this->billingClient = $billingClient;
        $this->security = $security;
    }

    /**
     * @Route("/", name="app_course_index", methods={"GET"})
     */
    public function index(CourseRepository $courseRepository): Response
    {
        $transactionsByCode = [];

        if ($this->isGranted('ROLE_USER')) {
            /** @var User $user */
            $user = $this->security->getUser();

            $transactions = $this->billingClient->getTransactions(
                $user->getApiToken(),
                'payment',
                null,
                true
            );
            foreach ($transactions as $transaction) {
                $transactionsByCode[$transaction['course_code']] = $transaction;
            }
        }

        $billingCourses = $this->billingClient->getCourses();

        $coursesMessage = [];
        foreach ($billingCourses as $course) {
            if (isset($transactionsByCode[$course['code']])) { // Если куплен или аренда не истекла
                if ($course['type'] === 'rent') {
                    $expiresAt = ConverterService::reformatDateTime($transactionsByCode[$course['code']]['expires_at']);
                    $coursesMessage[$course['code']] = 'Арендовано до ' . $expiresAt;
                } elseif ($course['type'] === 'buy') {
                    $coursesMessage[$course['code']] = 'Куплено';
                }
            } else {
                if ($course['type'] === 'rent') {
                    $coursesMessage[$course['code']] = $course['price'] . '₽ в неделю';
                } elseif ($course['type'] === 'buy') {
                    $coursesMessage[$course['code']] = $course['price'] . '₽';
                } elseif ($course['type'] === 'free') {
                    $coursesMessage[$course['code']] = 'Бесплатный';
                }
            }
        }

        return $this->render('course/index.html.twig', [
            'courses' => $courseRepository->findAll(),
            'coursesMessage' => $coursesMessage,
        ]);
    }

    /**
     * @Route("/new", name="app_course_new", methods={"GET", "POST"})
     * @IsGranted("ROLE_SUPER_ADMIN")
     */
    public function new(Request $request, CourseRepository $courseRepository): Response
    {
        /** @var User $user */
        $user = $this->security->getUser();
        if (null === $user) {
            return $this->redirectToRoute('app_login', [], Response::HTTP_SEE_OTHER);
        }

        $course = new Course();
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($courseRepository->count(['code' => $course->getCode()]) > 0) {
                throw new BadRequestException('Курс с данным кодом уже существует');
            }

            $courseRequest = $this->createCourseRequest($form, $course);
            $this->billingClient->saveCourse($user->getApiToken(), $courseRequest);

            $courseRepository->add($course);
            return $this->redirectToRoute('app_course_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('course/new.html.twig', [
            'course' => $course,
            'form' => $form,
        ]);
    }

    /**
     * @Route("/{id}", name="app_course_show", methods={"GET"})
     */
    public function show(Request $request, Course $course): Response
    {
        /** @var User $user */
        $user = $this->security->getUser();
        if (null === $user) {
            return $this->render('course/show.html.twig', [
                'course' => $course,
                'billingCourse' => null,
                'billingUser' => null,
                'paymentStatus' => null,
            ]);
        }
        $billingUser = $this->billingClient->getCurrent($user->getApiToken());
        $billingCourse = $this->billingClient->getCourse($course->getCode());

        $billingCourse['isPaid'] = $this->billingClient->isCoursePaid($user->getApiToken(), $billingCourse);

        $paymentStatus = $request->query->get('payment_status');
        if (null !== $paymentStatus) {
            if ($paymentStatus <= 3) {
                $paymentStatus = PaymentStatus::MESSAGES[(int) $paymentStatus];
            } else {
                $paymentStatus = null;
            }
        }

        return $this->render('course/show.html.twig', [
            'course' => $course,
            'billingCourse' => $billingCourse,
            'billingUser' => $billingUser,
            'paymentStatus' => $paymentStatus,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="app_course_edit", methods={"GET", "POST"})
     * @IsGranted("ROLE_SUPER_ADMIN")
     */
    public function edit(Request $request, Course $course, CourseRepository $courseRepository): Response
    {
        /** @var User $user */
        $user = $this->security->getUser();
        if (null === $user) {
            return $this->redirectToRoute('app_login', [], Response::HTTP_SEE_OTHER);
        }

        $oldCode = $course->getCode();
        $billingCourse = $this->billingClient->getCourse($course->getCode());

        $form = $this->createForm(CourseType::class, $course, [
            'price' => $billingCourse['price'] ?? 0,
            'type' => $billingCourse['type'],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($oldCode !== $course->getCode() && $courseRepository->count(['code' => $course->getCode()]) > 0) {
                throw new BadRequestException('Курс с новым кодом уже существует');
            }

            $courseRequest = $this->createCourseRequest($form, $course);
            $this->billingClient->saveCourse($user->getApiToken(), $courseRequest, $oldCode);

            $courseRepository->add($course);
            return $this->redirectToRoute('app_course_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('course/edit.html.twig', [
            'course' => $course,
            'form' => $form,
        ]);
    }

    /**
     * @param FormInterface $form
     * @param Course $course
     * @return CourseDto
     */
    protected function createCourseRequest(FormInterface $form, Course $course): CourseDto
    {
        $price = $form->get('price')->getData();
        $type = $form->get('type')->getData();

        return (new CourseDto())
            ->setCode($course->getCode())
            ->setName($course->getName())
            ->setType($type)
            ->setPrice($price);
    }

    /**
     * @Route("/{id}", name="app_course_delete", methods={"POST"})
     * @IsGranted("ROLE_SUPER_ADMIN")
     */
    public function delete(Request $request, Course $course, CourseRepository $courseRepository): Response
    {
        if ($this->isCsrfTokenValid('delete'.$course->getId(), $request->request->get('_token'))) {
            $courseRepository->remove($course);
        }

        return $this->redirectToRoute('app_course_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * @Route("/{id}/pay", name="app_course_pay", methods={"POST"})
     * @IsGranted("ROLE_USER")
     */
    public function pay(Request $request, Course $course): Response
    {
        if (!$this->isCsrfTokenValid('pay-course'.$course->getId(), $request->request->get('_token'))) {
            return $this->redirectToRoute('app_course_index', [], Response::HTTP_SEE_OTHER);
        }

        /** @var User $user */
        $user = $this->security->getUser();

        $paymentStatus = PaymentStatus::FAILED;

        try {
            $payInfo = $this->billingClient->payCourse($user->getApiToken(), $course->getCode());
        } catch (InsufficientFundsException $e) {
            $paymentStatus = PaymentStatus::INSUFFICIENT_FUNDS;
        } catch (CourseAlreadyPaidException $e) {
            $paymentStatus = PaymentStatus::ALREADY_PAID;
        } catch (ResourceNotFoundException|BillingUnavailableException|JsonException $_) {
        }

        if (isset($payInfo['success']) && $payInfo['success']) {
            $paymentStatus = PaymentStatus::SUCCEEDED;
        }

        return $this->redirectToRoute('app_course_show', [
            'id' => $course->getId(),
            'payment_status' => $paymentStatus
        ], Response::HTTP_SEE_OTHER);
    }
}
